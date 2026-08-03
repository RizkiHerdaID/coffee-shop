<?php

namespace App\Models;

use App\Enums\WasteReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_item_id', 'quantity', 'reason', 'note', 'admin_id', 'recorded_at'])]
class Wastage extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reason' => WasteReason::class,
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (Wastage $wastage): void {
            $wastage->restoreStock();
        });
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Restore the stock quantity deducted when this wastage was recorded.
     * Skips gracefully when the stock item was itself deleted (nullOnDelete).
     */
    public function restoreStock(): bool
    {
        if ($this->stock_item_id === null || $this->stockItem === null) {
            return false;
        }

        return $this->stockItem->stockIn(
            $this->quantity,
            __('wastage.notifications.movement_restore_note', ['id' => $this->getKey()]),
        );
    }
}
