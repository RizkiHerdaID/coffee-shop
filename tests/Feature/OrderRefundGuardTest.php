<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Shift;
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

    // ---------------------------------------------------------------------
    // Atomic refund claims (Vikunja 136): overlapping refunds must never
    // both pass, so paid_total can never go negative.
    // ---------------------------------------------------------------------

    /**
     * Two overlapping refunds without a refresh between: the second call's
     * locked re-read must see the first refund's row and reject the amount
     * that would over-refund. paid_total never drops below zero.
     */
    public function test_two_overlapping_refunds_only_one_succeeds_and_paid_total_never_goes_negative(): void
    {
        $order = $this->paidOrder(50000);

        $this->assertTrue($order->refund(30000));
        $this->assertFalse($order->refund(30000));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(20000, $order->paid_total);
        $this->assertSame(20000, (int) $order->payments()->sum('amount'));
        $this->assertDatabaseCount('payments', 2);
    }

    /**
     * Full-refund race: the first call drains paid_total to zero (and flips
     * the order to Refunded); a stale second refund of even 1 IDR must be
     * rejected and cannot resurrect the order or push paid_total negative.
     */
    public function test_overlapping_full_refunds_cannot_drive_paid_total_below_zero(): void
    {
        $order = $this->paidOrder(50000);

        $this->assertTrue($order->refund(50000));
        $this->assertFalse($order->refund(1));

        $order->refresh();

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame(0, $order->paid_total);
        $this->assertSame(0, (int) $order->payments()->sum('amount'));
        $this->assertDatabaseCount('payments', 2);
    }

    /**
     * A partial refund followed by an over-amount refund of the pre-refund
     * balance: the second call's in-transaction re-read sees only the
     * remaining 30000 and rejects the 30001.
     */
    public function test_stale_refund_amount_is_revalidated_against_the_locked_paid_total(): void
    {
        $order = $this->paidOrder(50000);

        $this->assertTrue($order->refund(20000));
        $this->assertFalse($order->refund(30001));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(30000, $order->paid_total);
        $this->assertDatabaseCount('payments', 2);
    }

    // ---------------------------------------------------------------------
    // markServedIfPaid guards (Vikunja 149): stale writes must never
    // resurrect a refunded order or mutate an order on a closed shift.
    // ---------------------------------------------------------------------

    /**
     * A stale markServed (the page rendered while the order was still paid,
     * but it was fully refunded before the click) must no-op: the status is
     * re-read under the lock and a Refunded order stays Refunded.
     */
    public function test_stale_mark_served_on_a_refunded_order_is_a_no_op(): void
    {
        $admin = $this->admin();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'MSV-'.fake()->unique()->numberBetween(100000, 999999),
            'status' => OrderStatus::Paid,
            'total' => 50000,
            'shift_id' => $shift->id,
            'created_by' => $admin->id,
        ]));
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 50000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $this->actingAs($admin, 'admin');

        // Refunded between render and click.
        $this->assertTrue($order->refund(50000));
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);

        $this->assertFalse($order->markServedIfPaid());

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
    }

    /**
     * markServedIfPaid on an order whose shift closed after it was paid is
     * rejected: the closed-shift freeze covers the served transition too.
     */
    public function test_mark_served_if_paid_returns_false_on_order_with_closed_shift(): void
    {
        $admin = $this->admin();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'MSV-'.fake()->unique()->numberBetween(100000, 999999),
            'status' => OrderStatus::Paid,
            'total' => 50000,
            'shift_id' => $shift->id,
            'created_by' => $admin->id,
        ]));
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 50000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $shift->update(['closed_at' => now(), 'closing_cash' => 500000]);

        $this->assertFalse($order->markServedIfPaid());

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }
}
