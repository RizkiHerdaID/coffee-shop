<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'unit', 'cost', 'quantity', 'min_threshold', 'note'])]
class StockItem extends Model
{
    protected static function booted(): void
    {
        // Deleting a stock item would cascade-destroy its movements (the
        // inventory audit trail) or silently orphan a non-zero quantity.
        // Only a zero-quantity item that was never moved is deletable.
        static::deleting(function (StockItem $stockItem) {
            if ($stockItem->movements()->exists() || (int) $stockItem->quantity !== 0) {
                throw new \RuntimeException('Stock items with movements or a non-zero quantity cannot be deleted: the inventory audit trail would be destroyed.');
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
            'cost' => 'integer',
            'quantity' => 'integer',
            'min_threshold' => 'integer',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function wastages(): HasMany
    {
        return $this->hasMany(Wastage::class);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'min_threshold');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_threshold;
    }

    public function stockIn(int $quantity, ?string $note = null, ?int $orderItemId = null): bool
    {
        if ($quantity < 1) {
            return false;
        }

        return DB::transaction(function () use ($quantity, $note, $orderItemId) {
            $item = static::query()->lockForUpdate()->find($this->getKey());

            if ($item === null) {
                return false;
            }

            $item->movements()->create([
                'type' => 'in',
                'quantity' => $quantity,
                'note' => $note,
                'order_item_id' => $orderItemId,
            ]);

            $item->increment('quantity', $quantity);

            return true;
        });
    }

    public function stockOut(int $quantity, ?string $note = null, ?int $orderItemId = null): bool
    {
        if ($quantity < 1) {
            return false;
        }

        return DB::transaction(function () use ($quantity, $note, $orderItemId) {
            $item = static::query()->lockForUpdate()->find($this->getKey());

            if ($item === null || $item->quantity - $quantity < 0) {
                return false;
            }

            $item->movements()->create([
                'type' => 'out',
                'quantity' => $quantity,
                'note' => $note,
                'order_item_id' => $orderItemId,
            ]);

            $item->decrement('quantity', $quantity);

            return true;
        });
    }
}
