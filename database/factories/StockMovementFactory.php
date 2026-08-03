<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_item_id' => StockItemFactory::new(),
            'order_item_id' => null,
            'type' => fake()->randomElement(['in', 'out']),
            'quantity' => fake()->numberBetween(1, 100),
            'note' => fake()->optional()->sentence(),
        ];
    }

    /**
     * A stock-in movement.
     */
    public function stockIn(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'in']);
    }

    /**
     * A stock-out movement (sale or wastage).
     */
    public function stockOut(?OrderItem $orderItem = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'out',
            'order_item_id' => $orderItem?->id ?? OrderItemFactory::new(),
        ]);
    }
}
