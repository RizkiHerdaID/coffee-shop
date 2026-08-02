<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['opened_at', 'closed_at', 'opening_cash', 'closing_cash', 'expected_total', 'admin_id'])]
class Shift extends Model
{
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
            'expected_total' => 'integer',
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
     * Orders that count towards the report: paid or served (not pending).
     */
    public function paidOrders(): HasMany
    {
        return $this->orders()->where('status', '!=', OrderStatus::Pending);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Sum of paid-order totals in the shift (IDR, integer).
     */
    public function salesTotal(): int
    {
        return (int) $this->paidOrders()->sum('total');
    }

    /**
     * Number of paid orders in the shift.
     */
    public function paidOrdersCount(): int
    {
        return $this->paidOrders()->count();
    }

    /**
     * Payment amounts by method for the shift's paid orders.
     *
     * @return array<string, int>
     */
    public function paymentsByMethod(): array
    {
        $rows = $this->paidOrders()
            ->join('payments', 'payments.order_id', '=', 'orders.id')
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
     * Cash returned to customers (negative cash payment rows; none exist yet).
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
     * Cash the drawer should hold: opening cash + cash taken in − cash refunds.
     */
    public function expectedCash(): int
    {
        return $this->opening_cash + $this->cashPaid() + $this->cashRefunds();
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
