<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => SupplierFactory::new(),
            'ordered_at' => fake()->optional()->dateTimeThisMonth(),
            'expected_at' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
            'received_at' => null,
            'status' => PurchaseOrderStatus::Pending,
            'total' => fake()->numberBetween(50_000, 2_000_000),
            'note' => fake()->optional()->sentence(),
        ];
    }

    /**
     * A purchase order that has been received (stocked in).
     */
    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::Received,
            'received_at' => now(),
        ]);
    }
}
