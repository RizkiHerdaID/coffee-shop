<?php

namespace App\Models;

use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'rating', 'text', 'visible', 'sort_order'])]
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Scope to testimonials visible on the public site.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }
}
