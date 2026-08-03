<?php

namespace Database\Factories;

use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
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
            'unit' => fake()->randomElement(['gram', 'kg', 'liter', 'ml', 'pcs', 'pack']),
            'cost' => fake()->numberBetween(1_000, 200_000),
            'quantity' => fake()->numberBetween(0, 500),
            'min_threshold' => fake()->numberBetween(0, 50),
            'note' => fake()->optional()->sentence(),
        ];
    }

    /**
     * An item at or below its reorder threshold.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'min_threshold' => 10,
        ]);
    }
}
