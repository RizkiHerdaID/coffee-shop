<?php

namespace App\Models;

use App\Enums\CashRegisterStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['opened_at', 'closed_at', 'opening_float', 'expected_amount', 'counted_amount', 'discrepancy', 'status', 'admin_id'])]
class CashRegisterSession extends Model
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
     * Revenue: SUM of orders.total for orders where
     * orders.created_at >= opened_at AND (closed_at IS NULL OR orders.created_at <= closed_at).
     * Returns 0 when opened_at is null.
     */
    public function revenue(): int
    {
        if ($this->opened_at === null) {
            return 0;
        }

        $query = Order::query()->where('created_at', '>=', $this->opened_at)
            ->where('created_at', '<=', $this->closed_at ?? now());

        return (int) $query->sum('total');
    }

    /**
     * Expected cash: opening_float + revenue().
     */
    public function expectedAmount(): int
    {
        return $this->opening_float + $this->revenue();
    }
}
