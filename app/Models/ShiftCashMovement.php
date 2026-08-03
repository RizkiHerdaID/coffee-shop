<?php

namespace App\Models;

use Database\Factories\ShiftCashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mid-shift cash movement: a deposit (setoran, type "in") into the drawer
 * or a petty-cash withdrawal (type "out"). Amounts are integer IDR.
 */
#[Fillable(['shift_id', 'type', 'amount', 'note', 'admin_id'])]
class ShiftCashMovement extends Model
{
    /** @use HasFactory<ShiftCashMovementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function isDeposit(): bool
    {
        return $this->type === 'in';
    }

    public function isPettyOut(): bool
    {
        return $this->type === 'out';
    }
}
