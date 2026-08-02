<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Jobs\PrintKitchenTicket;
use App\Jobs\PrintReceipt;
use App\Jobs\SendOrderConfirmation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_number', 'customer_phone', 'status', 'total', 'shift_id', 'created_by'])]
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
