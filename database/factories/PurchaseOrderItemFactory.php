<?php

namespace Database\Factories;

use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrderFactory::new(),
            'stock_item_id' => StockItemFactory::new(),
            'description' => fake()->words(2, true),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_price' => fake()->numberBetween(1_000, 200_000),
        ];
    }
}
