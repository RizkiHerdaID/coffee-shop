<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(10_000, 500_000);

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_phone' => fake()->optional()->numerify('0812########'),
            'notes' => fake()->optional()->sentence(),
            'status' => OrderStatus::Pending,
            'total' => $total,
            'discount_type' => null,
            'discount_amount' => null,
            'shift_id' => null,
            'created_by' => Admin::factory(),
        ];
    }

    /**
     * Attach the order to a shift: the given shift, the currently active
     * one, or a freshly created open shift when none is running.
     */
    public function onShift(?Shift $shift = null): static
    {
        return $this->state(fn (array $attributes) => [
            'shift_id' => $shift?->id ?? Shift::active()?->id ?? ShiftFactory::new(),
        ]);
    }

    /**
     * A paid order: paid status with a matching cash payment.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $total = (int) ($attributes['total'] ?? fake()->numberBetween(10_000, 500_000));

            return ['status' => OrderStatus::Paid, 'total' => $total];
        })->afterCreating(function (Order $order): void {
            $order->payments()->create([
                'method' => PaymentMethod::Cash,
                'amount' => $order->net_total,
                'change' => 0,
                'paid_at' => now(),
                'admin_id' => $order->created_by,
            ]);
        });
    }
}
