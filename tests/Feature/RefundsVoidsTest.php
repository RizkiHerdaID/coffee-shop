<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Order refunds (paid/served, full or partial, with reason) + voids
 * (cancel pending orders) — audit-safe corrections replacing the
 * DeleteBulkAction path.
 *
 * Model level: Order::refund(int $amount, string $method = 'cash',
 * ?string $reason = null, ?Admin $admin = null): bool and Order::void(): bool
 * (signature assumed from the contract — see test report). Refunds are
 * negative Payment rows; a refund that drains paid_total to <= 0 flips the
 * order to Refunded; partial refunds keep the current status.
 *
 * Action level: 'refund' visible on Paid/Served only, 'void' on Pending
 * only; both hidden when the order's shift is closed (shift null allowed).
 *
 * Fixtures create orders/payments directly (no customer_phone, so no
 * WhatsApp confirmation job is dispatched).
 */
class RefundsVoidsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    private function openShift(Admin $admin, int $openingCash = 500000): Shift
    {
        return Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => $openingCash,
            'admin_id' => $admin->id,
        ]);
    }

    private function closeShift(Shift $shift): void
    {
        $shift->update([
            'closed_at' => now(),
            'closing_cash' => $shift->opening_cash,
            'expected_total' => 0,
        ]);
    }

    private function paidOrder(Shift $shift, Admin $admin, int $total, OrderStatus $status = OrderStatus::Paid): Order
    {
        return Order::create([
            'order_number' => 'SH-'.Str::upper(Str::random(8)),
            'status' => $status,
            'total' => $total,
            'shift_id' => $shift->id,
            'created_by' => $admin->id,
        ]);
    }

    private function cashPayment(Order $order, Admin $admin, int $amount): Payment
    {
        return $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => $amount,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
    }

    private function paidCashOrder(Shift $shift, Admin $admin, int $total): Order
    {
        $order = $this->paidOrder($shift, $admin, $total);
        $this->cashPayment($order, $admin, $total);

        return $order;
    }

    // ---------------------------------------------------------------------
    // Model level: Order::refund()
    // ---------------------------------------------------------------------

    public function test_full_refund_marks_order_refunded_with_zero_paid_total(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(50000));

        $order->refresh();

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame(0, $order->paid_total);
        $this->assertDatabaseCount('payments', 2);

        $refund = $order->payments()->latest('id')->firstOrFail();
        $this->assertSame(PaymentMethod::Cash, $refund->method);
        $this->assertSame(-50000, $refund->amount);
        $this->assertSame($admin->id, $refund->admin_id);
        $this->assertNotNull($refund->paid_at);

        // The fully refunded order drops out of the shift report entirely.
        $this->assertSame(0, $shift->salesTotal());
        $this->assertSame(0, $shift->paidOrdersCount());
    }

    public function test_partial_refund_keeps_order_paid_and_reduces_paid_total(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(20000));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(30000, $order->paid_total);
        $this->assertSame(-20000, $order->payments()->latest('id')->firstOrFail()->amount);
        $this->assertSame(50000, $shift->salesTotal());
        $this->assertSame(1, $shift->paidOrdersCount());
    }

    public function test_partial_refund_keeps_served_order_served(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidOrder($shift, $admin, 50000, OrderStatus::Served);
        $this->cashPayment($order, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(20000));

        $order->refresh();

        $this->assertSame(OrderStatus::Served, $order->status);
        $this->assertSame(30000, $order->paid_total);
    }

    public function test_refund_exceeding_paid_total_is_rejected(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertFalse($order->refund(60000));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(50000, $order->paid_total);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_double_full_refund_is_rejected(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(50000));
        $this->assertFalse($order->refund(1000));

        $order->refresh();

        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertSame(0, $order->paid_total);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_refund_with_zero_or_negative_amount_is_rejected(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertFalse($order->refund(0));
        $this->assertFalse($order->refund(-10000));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_refund_on_pending_order_is_rejected(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidOrder($shift, $admin, 50000, OrderStatus::Pending);

        $this->actingAs($admin, 'admin');
        $this->assertFalse($order->refund(50000));

        $order->refresh();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_refund_on_order_with_closed_shift_is_rejected(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $this->closeShift($shift);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertFalse($order->refund(20000));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_refund_on_order_without_shift_is_allowed(): void
    {
        $admin = $this->admin();
        $order = Order::create([
            'order_number' => 'SH-NO-SHIFT-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::Paid,
            'total' => 50000,
            'created_by' => $admin->id,
        ]);
        $this->cashPayment($order, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(20000));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(30000, $order->paid_total);
    }

    // ---------------------------------------------------------------------
    // Model level: cash refund affects expected cash
    // ---------------------------------------------------------------------

    public function test_partial_cash_refund_reduces_expected_cash(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(15000));

        // 100.000 opening + 50.000 cash paid − 15.000 cash refunded.
        $this->assertSame(-15000, $shift->cashRefunds());
        $this->assertSame(135000, $shift->expectedCash());
    }

    public function test_full_cash_refund_restores_expected_cash(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(50000));

        // The drawer took in 50.000 and gave it all back: net zero, so
        // expected cash returns to the opening amount.
        $this->assertSame(0, $shift->cashRefunds());
        $this->assertSame(100000, $shift->expectedCash());
    }

    // ---------------------------------------------------------------------
    // Model level: Order::void()
    // ---------------------------------------------------------------------

    public function test_void_cancels_pending_order_without_payment_rows(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidOrder($shift, $admin, 50000, OrderStatus::Pending);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->void());

        $order->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $shift->salesTotal());
        $this->assertSame(0, $shift->paidOrdersCount());
    }

    public function test_void_on_paid_served_or_refunded_order_is_rejected(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $paid = $this->paidCashOrder($shift, $admin, 30000);
        $served = $this->paidOrder($shift, $admin, 40000, OrderStatus::Served);
        $this->cashPayment($served, $admin, 40000);
        $refunded = $this->paidOrder($shift, $admin, 50000, OrderStatus::Refunded);
        $this->cashPayment($refunded, $admin, 50000);
        $this->cashPayment($refunded, $admin, -50000);

        $this->actingAs($admin, 'admin');
        $this->assertFalse($paid->void());
        $this->assertFalse($served->void());
        $this->assertFalse($refunded->void());

        $this->assertSame(OrderStatus::Paid, $paid->fresh()->status);
        $this->assertSame(OrderStatus::Served, $served->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $refunded->fresh()->status);
    }

    public function test_void_on_order_with_closed_shift_is_rejected(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $this->closeShift($shift);
        $order = $this->paidOrder($shift, $admin, 50000, OrderStatus::Pending);

        $this->actingAs($admin, 'admin');
        $this->assertFalse($order->void());

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // Action level: Orders table (ListOrders)
    // ---------------------------------------------------------------------

    public function test_refund_action_is_visible_on_paid_and_served_but_not_other_statuses(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $paid = $this->paidCashOrder($shift, $admin, 50000);
        $served = $this->paidOrder($shift, $admin, 40000, OrderStatus::Served);
        $this->cashPayment($served, $admin, 40000);
        $pending = $this->paidOrder($shift, $admin, 30000, OrderStatus::Pending);
        $refunded = $this->paidOrder($shift, $admin, 20000, OrderStatus::Refunded);
        $this->cashPayment($refunded, $admin, 20000);
        $this->cashPayment($refunded, $admin, -20000);
        $cancelled = $this->paidOrder($shift, $admin, 10000, OrderStatus::Cancelled);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->assertTableActionVisible('refund', $paid)
            ->assertTableActionVisible('refund', $served)
            ->assertTableActionHidden('refund', $pending)
            ->assertTableActionHidden('refund', $refunded)
            ->assertTableActionHidden('refund', $cancelled);
    }

    public function test_void_action_is_visible_on_pending_orders_only(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $pending = $this->paidOrder($shift, $admin, 30000, OrderStatus::Pending);
        $paid = $this->paidCashOrder($shift, $admin, 50000);
        $refunded = $this->paidOrder($shift, $admin, 20000, OrderStatus::Refunded);
        $this->cashPayment($refunded, $admin, 20000);
        $this->cashPayment($refunded, $admin, -20000);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->assertTableActionVisible('void', $pending)
            ->assertTableActionHidden('void', $paid)
            ->assertTableActionHidden('void', $refunded);
    }

    public function test_refund_action_creates_negative_payment_with_reason_and_flips_status(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->callTableAction('refund', $order->getRouteKey(), [
                'amount' => 50000,
                'reason' => 'Barang rusak',
                'method' => PaymentMethod::Cash,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => -50000,
            'reference' => 'Barang rusak',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_refund_action_rejects_over_refund_with_validation_error(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->callTableAction('refund', $order->getRouteKey(), [
                'amount' => 60000,
                'method' => PaymentMethod::Cash,
            ])
            ->assertHasActionErrors(['amount']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_refund_action_requires_an_amount(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidCashOrder($shift, $admin, 50000);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->callTableAction('refund', $order->getRouteKey(), [
                'method' => PaymentMethod::Cash,
            ])
            ->assertHasActionErrors(['amount']);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_refund_action_is_hidden_on_orders_in_closed_shifts(): void
    {
        $admin = $this->admin();
        $closed = $this->openShift($admin);
        $this->closeShift($closed);
        $inClosedShift = $this->paidCashOrder($closed, $admin, 50000);

        $open = $this->openShift($admin);
        $inOpenShift = $this->paidCashOrder($open, $admin, 50000);

        $noShift = Order::create([
            'order_number' => 'SH-NO-SHIFT-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::Paid,
            'total' => 50000,
            'created_by' => $admin->id,
        ]);
        $this->cashPayment($noShift, $admin, 50000);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->assertTableActionHidden('refund', $inClosedShift)
            ->assertTableActionVisible('refund', $inOpenShift)
            ->assertTableActionVisible('refund', $noShift);
    }

    public function test_void_action_cancels_pending_order_via_modal(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $order = $this->paidOrder($shift, $admin, 50000, OrderStatus::Pending);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->callTableAction('void', $order->getRouteKey())
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $shift->salesTotal());
    }

    public function test_void_action_is_hidden_on_orders_in_closed_shifts(): void
    {
        $admin = $this->admin();
        $closed = $this->openShift($admin);
        $this->closeShift($closed);
        $inClosedShift = $this->paidOrder($closed, $admin, 50000, OrderStatus::Pending);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->assertTableActionHidden('void', $inClosedShift);
    }

    // ---------------------------------------------------------------------
    // Z-report
    // ---------------------------------------------------------------------

    public function test_z_report_shows_partial_cash_refunds(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);
        $order = $this->paidCashOrder($shift, $admin, 50000);
        $this->cashPayment($order, $admin, -15000); // partial refund, order stays paid
        $shift->update([
            'closed_at' => now(),
            'closing_cash' => 135000,
            'expected_total' => 35000,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('pos.zreport', $shift))
            ->assertOk()
            ->assertSee('PENGEMBALIAN TUNAI')
            ->assertSee('Rp -15.000')
            ->assertSee('Rp 35.000')
            ->assertSee('Rp 135.000');
    }
}
