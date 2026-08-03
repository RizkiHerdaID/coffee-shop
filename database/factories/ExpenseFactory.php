<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(5_000, 5_000_000),
            'spent_at' => fake()->date(),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
