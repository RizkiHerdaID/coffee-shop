<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['opened_at', 'closed_at', 'opening_cash', 'closing_cash', 'admin_id'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
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
            'opening_cash' => 'integer',
            'closing_cash' => 'integer',
        ];
    }

    /**
     * The single open shift, or null when none is running.
     */
    public static function active(): ?self
    {
        return static::query()->whereNull('closed_at')->latest('id')->first();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Orders that count towards the report: paid or served. Pending,
     * cancelled and fully refunded orders are excluded.
     */
    public function paidOrders(): HasMany
    {
        return $this->orders()->whereNotIn('status', [
            OrderStatus::Pending,
            OrderStatus::Refunded,
            OrderStatus::Cancelled,
        ]);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(ShiftCashMovement::class);
    }

    /**
     * Total mid-shift deposits into the drawer (type "in").
     */
    public function deposits(): int
    {
        return (int) $this->cashMovements()->where('type', 'in')->sum('amount');
    }

    /**
     * Total petty-cash withdrawals from the drawer (type "out").
     */
    public function pettyOut(): int
    {
        return (int) $this->cashMovements()->where('type', 'out')->sum('amount');
    }

    /**
     * Sum of paid-order NET totals in the shift (gross minus discount, IDR
     * integer) — consistent with the payment-derived expectedCash().
     */
    public function salesTotal(): int
    {
        return (int) $this->paidOrders()->get()->sum(fn (Order $order) => $order->net_total);
    }

    /**
     * Number of paid orders in the shift.
     */
    public function paidOrdersCount(): int
    {
        return $this->paidOrders()->count();
    }

    /**
     * Payment intake by method for the shift's paid orders (positive rows
     * only — refunds are negative rows and are reported separately).
     *
     * @return array<string, int>
     */
    public function paymentsByMethod(): array
    {
        $rows = $this->paidOrders()
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('payments.amount', '>', 0)
            ->selectRaw('payments.method, SUM(payments.amount) as total')
            ->groupBy('payments.method')
            ->pluck('total', 'method');

        $methods = ['cash' => 0, 'qris' => 0, 'ewallet' => 0];

        foreach ($methods as $method => $value) {
            $methods[$method] = (int) ($rows[$method] ?? 0);
        }

        return $methods;
    }

    /**
     * Cash received via cash payments on the shift's paid orders.
     */
    public function cashPaid(): int
    {
        return $this->paidOrders()
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('payments.method', PaymentMethod::Cash)
            ->where('payments.amount', '>', 0)
            ->sum('payments.amount');
    }

    /**
     * Cash returned to customers (negative cash payment rows).
     */
    public function cashRefunds(): int
    {
        return $this->paidOrders()
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('payments.method', PaymentMethod::Cash)
            ->where('payments.amount', '<', 0)
            ->sum('payments.amount');
    }

    /**
     * Cash the drawer should hold: opening cash + cash taken in − cash
     * refunds + mid-shift deposits − petty-cash withdrawals.
     */
    public function expectedCash(): int
    {
        return $this->opening_cash + $this->cashPaid() + $this->cashRefunds() + $this->deposits() - $this->pettyOut();
    }

    /**
     * Counted minus expected drawer cash; 0 while the shift is still open.
     */
    public function discrepancy(): int
    {
        if ($this->closed_at === null || $this->closing_cash === null) {
            return 0;
        }

        return $this->closing_cash - $this->expectedCash();
    }
}
