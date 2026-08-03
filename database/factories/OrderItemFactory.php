<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(10_000, 150_000);
        $qty = fake()->numberBetween(1, 5);

        return [
            'order_id' => OrderFactory::new(),
            'menu_item_id' => MenuItemFactory::new(),
            'name' => fake()->words(2, true),
            'price' => $price,
            'qty' => $qty,
            'subtotal' => $price * $qty,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
