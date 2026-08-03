<?php

namespace App\Models;

use App\Enums\CashRegisterStatus;
use App\Enums\OrderStatus;
use Database\Factories\CashRegisterSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['opened_at', 'closed_at', 'opening_float', 'expected_amount', 'counted_amount', 'discrepancy', 'status', 'admin_id'])]
class CashRegisterSession extends Model
{
    /** @use HasFactory<CashRegisterSessionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'integer',
            'expected_amount' => 'integer',
            'counted_amount' => 'integer',
            'discrepancy' => 'integer',
            'status' => CashRegisterStatus::class,
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Revenue: SUM of paid/served orders' NET totals (gross minus discount)
     * with orders.created_at >= opened_at AND (closed_at IS NULL OR
     * orders.created_at <= closed_at). Pending, refunded and cancelled
     * orders are excluded — same definition as Shift::salesTotal() so the
     * session report reconciles with shift reports. Returns 0 when
     * opened_at is null.
     */
    public function revenue(): int
    {
        if ($this->opened_at === null) {
            return 0;
        }

        return (int) Order::query()
            ->where('created_at', '>=', $this->opened_at)
            ->where('created_at', '<=', $this->closed_at ?? now())
            ->whereNotIn('status', [
                OrderStatus::Pending,
                OrderStatus::Refunded,
                OrderStatus::Cancelled,
            ])
            ->get()
            ->sum(fn (Order $order): int => $order->net_total);
    }

    /**
     * Expected cash: opening_float + revenue().
     */
    public function expectedAmount(): int
    {
        return $this->opening_float + $this->revenue();
    }
}
