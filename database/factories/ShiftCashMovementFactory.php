<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\ShiftCashMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftCashMovement>
 */
class ShiftCashMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => ShiftFactory::new(),
            'type' => fake()->randomElement(['in', 'out']),
            'amount' => fake()->numberBetween(10_000, 1_000_000),
            'note' => fake()->optional()->sentence(),
            'admin_id' => Admin::factory(),
        ];
    }

    /**
     * A deposit into the drawer (setoran).
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'in']);
    }

    /**
     * A petty-cash withdrawal from the drawer.
     */
    public function pettyOut(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'out']);
    }
}
