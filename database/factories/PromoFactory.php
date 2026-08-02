<?php

namespace Database\Factories;

use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promo>
 */
class PromoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(),
            'badge' => fake()->word(),
            'cta_text' => fake()->words(2, true),
            'cta_url' => fake()->url(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
