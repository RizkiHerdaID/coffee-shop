<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\PrintKitchenTicket;
use App\Jobs\PrintReceipt;
use App\Jobs\SendOrderConfirmation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_number', 'customer_phone', 'notes', 'status', 'total', 'shift_id', 'created_by'])]
class Order extends Model
{
    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (config('whatsapp.enabled') && filled($order->customer_phone)) {
                SendOrderConfirmation::dispatch($order);
            }
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
     * Amount still owed; zero once paid_total covers the total.
     */
    public function getRemainingAttribute(): int
    {
        return max($this->total - $this->paid_total, 0);
    }

    /**
     * Transition pending → paid once payments cover the total. Dispatches the
     * receipt/kitchen print jobs exactly once, on the transition itself.
     */
    public function markPaidIfCovered(): bool
    {
        if ($this->status !== OrderStatus::Pending || $this->paid_total < $this->total) {
            return false;
        }

        $this->update(['status' => OrderStatus::Paid]);

        PrintReceipt::dispatch($this);
        PrintKitchenTicket::dispatch($this);

        return true;
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
     * amount is invalid (zero, negative, or above the net paid).
     */
    public function refund(int $amount, PaymentMethod|string $method = PaymentMethod::Cash, ?string $reason = null): bool
    {
        if (! $method instanceof PaymentMethod) {
            $method = PaymentMethod::from($method);
        }

        if (! $this->canBeRefunded() || $amount <= 0 || $amount > $this->paid_total) {
            return false;
        }

        $this->payments()->create([
            'method' => $method,
            'amount' => -$amount,
            'reference' => $reason,
            'paid_at' => now(),
            'admin_id' => auth('admin')->id(),
        ]);

        if ($this->paid_total <= 0) {
            $this->update(['status' => OrderStatus::Refunded]);
        }

        return true;
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
