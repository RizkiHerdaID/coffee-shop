<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'price', 'note', 'sort_order', 'photo', 'category', 'available'])]
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
        ];
    }
}
