<?php

namespace App\Models;

use Database\Factories\PromoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'subtitle', 'badge', 'cta_text', 'cta_url', 'starts_at', 'ends_at', 'active', 'sort_order'])]
class Promo extends Model
{
    /** @use HasFactory<PromoFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Scope to promos currently visible on the public site: active and within
     * the scheduled window (starts_at <= now, ends_at null or >= now).
     */
    public function scopeVisible(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $now = $at ?? now();

        return $query
            ->where('active', true)
            ->where('starts_at', '<=', $now)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}
