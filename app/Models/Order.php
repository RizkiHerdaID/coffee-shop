<?php

namespace App\Models;

use App\Enums\OrderStatus;
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
