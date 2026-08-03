<?php

namespace Database\Factories;

use App\Enums\WasteReason;
use App\Models\Admin;
use App\Models\Wastage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wastage>
 */
class WastageFactory extends Factory
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
            'quantity' => fake()->numberBetween(1, 100),
            'reason' => fake()->randomElement(WasteReason::cases()),
            'note' => fake()->optional()->sentence(),
            'admin_id' => Admin::factory(),
            'recorded_at' => now(),
        ];
    }
}
