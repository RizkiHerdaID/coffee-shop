<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Admin;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order::refund() input guards (Vikunja 123, part): zero, negative and
 * oversized amounts are rejected with false — never a ValueError — and
 * orders without any payment rows cannot be refunded. Invalid method
 * strings are ignored instead of throwing. Refund rows stay audit-safe:
 * negative Payment rows with the reason preserved, full refunds flip the
 * order to Refunded.
 *
 * Complements RefundsVoidsTest (owned by the parallel fix-pos-cash
 * branch), which covers the status-transition and closed-shift behavior.
 */
class OrderRefundGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    /**
     * A paid order with a full cash payment; the acting admin is the one
     * the refund rows will be attributed to.
     */
    private function paidOrder(int $total = 50000): Order
    {
        $admin = $this->admin();

        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'RFG-'.fake()->unique()->numberBetween(100000, 999999),
            'status' => OrderStatus::Paid,
            'total' => $total,
            'created_by' => $admin->id,
        ]));

        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => $total,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin');

        return $order->refresh();
    }

    public function test_refund_with_zero_amount_is_rejected_without_payment_rows(): void
    {
        $order = $this->paidOrder();

        $this->assertFalse($order->refund(0));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_refund_with_negative_amount_is_rejected_without_payment_rows(): void
    {
        $order = $this->paidOrder();

        $this->assertFalse($order->refund(-10000));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_refund_cannot_exceed_the_remaining_paid_total(): void
    {
        $order = $this->paidOrder(50000);

        $this->assertTrue($order->refund(30000));
        $this->assertFalse($order->refund(30000));
        $this->assertFalse($order->refund(20001));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(20000, $order->paid_total);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_refund_on_order_without_any_payment_rows_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin');

        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'RFG-NOPAY-1',
            'status' => OrderStatus::Paid,
            'total' => 50000,
            'created_by' => $admin->id,
        ]));

        $this->assertFalse($order->refund(1));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_refund_with_invalid_method_string_is_rejected_without_throwing(): void
    {
        $order = $this->paidOrder();

        try {
            $result = $order->refund(10000, 'not-a-payment-method');
        } catch (\ValueError $e) {
            $this->fail('refund() must not throw a ValueError for an invalid method string: '.$e->getMessage());
        }

        $this->assertFalse($result);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_valid_partial_refund_keeps_the_audit_trail(): void
    {
        $order = $this->paidOrder();

        $this->assertTrue($order->refund(15000, PaymentMethod::Qris, 'Salah pesanan'));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(35000, $order->paid_total);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => -15000,
            'reference' => 'Salah pesanan',
            'admin_id' => $order->created_by,
        ]);
    }

    public function test_full_refund_flips_status_to_refunded(): void
    {
        $order = $this->paidOrder();

        $this->assertTrue($order->refund(50000));

        $order->refresh();

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame(0, $order->paid_total);
        $this->assertDatabaseCount('payments', 2);
    }
}
