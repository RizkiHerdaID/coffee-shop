<?php

namespace Database\Factories;

use App\Enums\CashRegisterStatus;
use App\Models\Admin;
use App\Models\CashRegisterSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegisterSession>
 */
class CashRegisterSessionFactory extends Factory
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
            'opening_float' => fake()->numberBetween(100_000, 1_000_000),
            'expected_amount' => 0,
            'counted_amount' => null,
            'discrepancy' => null,
            'status' => CashRegisterStatus::Open,
            'admin_id' => Admin::factory(),
        ];
    }

    /**
     * A closed session with a counted drawer and derived discrepancy.
     */
    public function closed(): static
    {
        return $this->state(function (array $attributes) {
            $counted = fake()->numberBetween(100_000, 2_000_000);

            return [
                'closed_at' => now(),
                'counted_amount' => $counted,
                'discrepancy' => $counted - (int) ($attributes['opening_float'] ?? 0),
                'status' => CashRegisterStatus::Closed,
            ];
        });
    }
}
