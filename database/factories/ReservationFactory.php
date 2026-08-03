<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('0812########'),
            'party_size' => fake()->numberBetween(1, 12),
            'date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'time' => fake()->time('H:i'),
            'status' => ReservationStatus::Pending,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
