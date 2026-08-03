<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Admin;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => OrderFactory::new(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(10_000, 500_000),
            'change' => 0,
            'reference' => fake()->optional()->words(2, true),
            'paid_at' => now(),
            'admin_id' => Admin::factory(),
        ];
    }

    /**
     * A refund row (negative amount).
     */
    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => -abs((int) ($attributes['amount'] ?? fake()->numberBetween(10_000, 500_000))),
        ]);
    }
}
