<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opened_at' => now()->subHours(8),
            'closed_at' => null,
            'opening_cash' => fake()->numberBetween(100_000, 1_000_000),
            'closing_cash' => null,
            'admin_id' => Admin::factory(),
        ];
    }

    /**
     * A closed shift with a counted closing drawer.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
            'closing_cash' => (int) ($attributes['opening_cash'] ?? 0) + fake()->numberBetween(100_000, 2_000_000),
        ]);
    }
}
