<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'price', 'note', 'badges', 'sort_order', 'photo', 'category', 'available'])]
class MenuItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'photo' => 'string',
            'category' => 'string',
            'available' => 'boolean',
            'badges' => 'array',
        ];
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(StockItem::class)->withPivot('quantity');
    }

    public function cogs(): int
    {
        return $this->ingredients()
            ->get()
            ->sum(fn (StockItem $item): int => (int) $item->cost * (int) $item->pivot->quantity);
    }
}
