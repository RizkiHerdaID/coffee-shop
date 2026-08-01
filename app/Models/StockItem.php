<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'unit', 'quantity', 'min_threshold', 'note'])]
class StockItem extends Model
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
            'min_threshold' => 'integer',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'min_threshold');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_threshold;
    }

    public function stockIn(int $quantity, ?string $note = null): bool
    {
        if ($quantity < 1) {
            return false;
        }

        return DB::transaction(function () use ($quantity, $note) {
            $item = static::query()->lockForUpdate()->find($this->getKey());

            if ($item === null) {
                return false;
            }

            $item->movements()->create([
                'type' => 'in',
                'quantity' => $quantity,
                'note' => $note,
            ]);

            $item->increment('quantity', $quantity);

            return true;
        });
    }

    public function stockOut(int $quantity, ?string $note = null): bool
    {
        if ($quantity < 1) {
            return false;
        }

        return DB::transaction(function () use ($quantity, $note) {
            $item = static::query()->lockForUpdate()->find($this->getKey());

            if ($item === null || $item->quantity - $quantity < 0) {
                return false;
            }

            $item->movements()->create([
                'type' => 'out',
                'quantity' => $quantity,
                'note' => $note,
            ]);

            $item->decrement('quantity', $quantity);

            return true;
        });
    }
}
