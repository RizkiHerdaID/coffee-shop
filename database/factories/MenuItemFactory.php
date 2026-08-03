<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => fake()->numberBetween(10_000, 150_000),
            'note' => fake()->sentence(),
            'badges' => fake()->randomElements(['bestseller', 'new', 'halal'], fake()->numberBetween(0, 2)),
            'sort_order' => 0,
            'photo' => null,
            'category' => fake()->randomElement(['coffee', 'non-coffee', 'food', 'dessert']),
            'available' => true,
        ];
    }
}
