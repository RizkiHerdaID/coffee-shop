<?php

namespace Database\Factories;

use App\Models\LoyaltyCard;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyCard>
 */
class LoyaltyCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => Phone::normalize(fake()->unique()->numerify('08##########')),
            'stamps' => fake()->numberBetween(0, 20),
            'redeemed' => fake()->numberBetween(0, 5),
        ];
    }
}
