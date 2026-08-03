<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\PrintKitchenTicket;
use App\Jobs\PrintReceipt;
use App\Jobs\SendOrderConfirmation;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['order_number', 'customer_phone', 'notes', 'status', 'total', 'discount_type', 'discount_amount', 'shift_id', 'created_by'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (config('whatsapp.enabled') && filled($order->customer_phone)) {
                // Dispatch after commit so a sync queue never sends a
                // confirmation before the order row is visible.
                DB::afterCommit(fn () => SendOrderConfirmation::dispatch($order, app()->getLocale()));
            }
        });

        // Orders are immutable audit records: a hard delete would cascade
        // order_items/payments, destroy the history that Z-reports are
        // built from, and leave stock movements orphaned.
        static::deleting(function (Order $order) {
            throw new \RuntimeException('Orders cannot be deleted: they are immutable audit records.');
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => 'integer',
            'loyalty_credited_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Sum of accepted payment rows (IDR, integer).
     */
    public function getPaidTotalAttribute(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    /**
     * Effective discount in IDR; 0 when the order has none. Fixed discounts
     * are the stored amount (capped at the gross total); percent discounts
     * are rounded from the gross total.
     */
    public function getDiscountValueAttribute(): int
    {
        if ($this->discount_type === null || $this->discount_amount === null || $this->discount_amount < 1) {
            return 0;
        }

        if ($this->discount_type === 'fixed') {
            return min($this->discount_amount, $this->total);
        }

        if ($this->discount_type === 'percent') {
            return min((int) round($this->total * $this->discount_amount / 100), $this->total);
        }

        return 0;
    }

    /**
     * Gross total minus the effective discount; what the customer actually
     * pays and what payment coverage is measured against.
     */
    public function getNetTotalAttribute(): int
    {
        return $this->total - $this->discount_value;
    }

    /**
     * Amount still owed; zero once paid_total covers the net total.
     */
    public function getRemainingAttribute(): int
    {
        return max($this->net_total - $this->paid_total, 0);
    }

    /**
     * Transition pending → paid once payments cover the net total. Dispatches
     * the receipt/kitchen print jobs exactly once, on the transition itself.
     * The dispatches are deferred until after the surrounding transaction
     * commits so sync-queue mode never prints against uncommitted data.
     *
     * The transition is claimed atomically: the row is locked before the
     * status flip, so concurrent callers (cashier payment flow and the
     * admin Orders table action) serialize and only one can capture the
     * pending → paid transition and dispatch prints.
     */
    public function markPaidIfCovered(): bool
    {
        if ($this->status !== OrderStatus::Pending || $this->paid_total < $this->net_total) {
            return false;
        }

        if ($this->shift_id !== null && $this->shift?->closed_at !== null) {
            return false;
        }

        DB::transaction(function (): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== OrderStatus::Pending || $locked->paid_total < $locked->net_total) {
                return;
            }

            if ($locked->shift_id !== null && $locked->shift?->closed_at !== null) {
                return;
            }

            $locked->update(['status' => OrderStatus::Paid]);

            $this->setRawAttributes($locked->getAttributes(), true);
        });

        if ($this->status !== OrderStatus::Paid) {
            return false;
        }

        DB::afterCommit(function (): void {
            PrintReceipt::dispatch($this);
            PrintKitchenTicket::dispatch($this);
        });

        return true;
    }

    /**
     * Transition paid → served. Atomic: the row is locked inside the
     * transaction and the status/shift are re-read under the lock, so a
     * stale caller (rendered while the order was still paid) can never
     * resurrect a refunded/cancelled order or mutate one on a closed shift.
     * Returns false when the order is not paid, or its shift has closed.
     */
    public function markServedIfPaid(): bool
    {
        if ($this->status !== OrderStatus::Paid) {
            return false;
        }

        if ($this->shift_id !== null && $this->shift?->closed_at !== null) {
            return false;
        }

        DB::transaction(function (): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== OrderStatus::Paid) {
                return;
            }

            if ($locked->shift_id !== null && $locked->shift?->closed_at !== null) {
                return;
            }

            $locked->update(['status' => OrderStatus::Served]);

            $this->setRawAttributes($locked->getAttributes(), true);
        });

        return $this->status === OrderStatus::Served;
    }

    /**
     * Whether the order can be refunded: paid or served, and its shift is
     * still open (or unattached). Closed shifts keep the Z-report stable.
     */
    public function canBeRefunded(): bool
    {
        if (! in_array($this->status, [OrderStatus::Paid, OrderStatus::Served], true)) {
            return false;
        }

        return $this->shift_id === null || $this->shift?->closed_at === null;
    }

    /**
     * Record a refund as a negative payment row. Full refunds (net paid
     * drops to zero) flip the status to Refunded; partial refunds keep the
     * current status. Returns false when the order is not refundable or the
     * amount is invalid (zero, negative, or above the net paid). Invalid
     * method strings are rejected instead of throwing a ValueError.
     *
     * The refund is claimed atomically: the order row is locked inside the
     * transaction and the paid total is re-summed under the lock, so two
     * concurrent refunds cannot both pass the amount check and push the
     * paid total below zero. attempts: 5 retries deadlocks from the row
     * lock contending with concurrent captures or shifts.
     */
    public function refund(int $amount, PaymentMethod|string $method = PaymentMethod::Cash, ?string $reason = null): bool
    {
        if (! $method instanceof PaymentMethod) {
            $method = PaymentMethod::tryFrom($method);
        }

        if (! $method instanceof PaymentMethod) {
            return false;
        }

        if (! $this->canBeRefunded() || $amount <= 0 || $amount > $this->paid_total) {
            return false;
        }

        return DB::transaction(function () use ($amount, $method, $reason): bool {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->canBeRefunded() || $amount > $locked->paid_total) {
                return false;
            }

            $locked->payments()->create([
                'method' => $method,
                'amount' => -$amount,
                'reference' => $reason,
                'paid_at' => now(),
                'admin_id' => auth('admin')->id(),
            ]);

            if ($locked->paid_total <= 0) {
                $locked->update(['status' => OrderStatus::Refunded]);
            }

            $this->setRawAttributes($locked->getAttributes(), true);

            return true;
        }, attempts: 5);
    }

    /**
     * Whether the order can be voided: still pending, and its shift is open
     * (or unattached).
     */
    public function canBeVoided(): bool
    {
        if ($this->status !== OrderStatus::Pending) {
            return false;
        }

        return $this->shift_id === null || $this->shift?->closed_at === null;
    }

    /**
     * Cancel a pending order that was never paid. Returns false when the
     * order is not voidable.
     */
    public function void(): bool
    {
        if (! $this->canBeVoided()) {
            return false;
        }

        $this->update(['status' => OrderStatus::Cancelled]);

        return true;
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
