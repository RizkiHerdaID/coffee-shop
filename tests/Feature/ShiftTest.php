<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Cashier;
use App\Filament\Pages\ManageShift;
use App\Filament\Pages\ShiftReport;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * POS shift open/close + Z-report (milestone M3).
 *
 * Covers the contract: shift math (expected cash = opening cash + cash
 * payments − cash refunds, discrepancy = counted − expected, totals by
 * payment method), the open/close flow, the one-active-shift guard, orders
 * attaching to the active shift, and the localized Z-report view.
 *
 * Fixtures create orders/payments directly (no customer_phone, so no
 * WhatsApp confirmation job is dispatched).
 */
class ShiftTest extends TestCase
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

    private function cashPayment(Order $order, Admin $admin, int $amount, ?string $reference = null): Payment
    {
        return $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => $amount,
            'reference' => $reference,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
    }

    public function test_active_returns_null_when_no_shift_is_open(): void
    {
        $admin = $this->admin();

        $this->assertNull(Shift::active());

        Shift::create([
            'opened_at' => now()->subHours(5),
            'closed_at' => now()->subHours(1),
            'opening_cash' => 100000,
            'closing_cash' => 150000,
            'expected_total' => 50000,
            'admin_id' => $admin->id,
        ]);

        $this->assertNull(Shift::active());
    }

    public function test_active_returns_the_open_shift(): void
    {
        $admin = $this->admin();
        $open = $this->openShift($admin, 250000);

        $this->assertSame($open->id, Shift::active()?->id);
    }

    public function test_open_scope_filters_to_running_shifts(): void
    {
        $admin = $this->admin();
        $open = $this->openShift($admin);

        Shift::create([
            'opened_at' => now()->subHours(5),
            'closed_at' => now()->subHours(1),
            'opening_cash' => 100000,
            'closing_cash' => 150000,
            'expected_total' => 50000,
            'admin_id' => $admin->id,
        ]);

        $ids = Shift::open()->pluck('id');

        $this->assertSame([$open->id], $ids->all());
    }

    public function test_sales_total_sums_paid_and_served_orders_but_excludes_pending(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $this->paidOrder($shift, $admin, 20000);
        $this->paidOrder($shift, $admin, 30000, OrderStatus::Served);
        $this->paidOrder($shift, $admin, 99000, OrderStatus::Pending);

        $this->assertSame(50000, $shift->salesTotal());
        $this->assertSame(2, $shift->paidOrdersCount());
    }

    public function test_sales_total_ignores_orders_from_other_shifts_and_unattached_orders(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);
        $other = $this->openShift($admin, 100000);

        $this->paidOrder($shift, $admin, 20000);
        $this->paidOrder($other, $admin, 50000);

        Order::create([
            'order_number' => 'SH-NULL-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::Paid,
            'total' => 70000,
            'created_by' => $admin->id,
        ]);

        $this->assertSame(20000, $shift->salesTotal());
        $this->assertSame(50000, $other->salesTotal());
    }

    public function test_payments_by_method_totals_each_method_for_shift_paid_orders(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $order = $this->paidOrder($shift, $admin, 100000);
        $this->cashPayment($order, $admin, 60000);
        $order->payments()->create([
            'method' => PaymentMethod::Qris,
            'amount' => 40000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $order2 = $this->paidOrder($shift, $admin, 50000);
        $order2->payments()->create([
            'method' => PaymentMethod::Ewallet,
            'amount' => 50000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $other = $this->paidOrder($shift, $admin, 99999, OrderStatus::Pending);
        $this->cashPayment($other, $admin, 99999);

        $this->assertSame([
            'cash' => 60000,
            'qris' => 40000,
            'ewallet' => 50000,
        ], $shift->paymentsByMethod());
    }

    public function test_sales_total_excludes_refunded_and_cancelled_orders(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $this->paidOrder($shift, $admin, 20000, OrderStatus::Paid);
        $this->paidOrder($shift, $admin, 30000, OrderStatus::Served);
        $this->paidOrder($shift, $admin, 99000, OrderStatus::Refunded);
        $this->paidOrder($shift, $admin, 99000, OrderStatus::Cancelled);
        $this->paidOrder($shift, $admin, 99000, OrderStatus::Pending);

        $this->assertSame(50000, $shift->salesTotal());
        $this->assertSame(2, $shift->paidOrdersCount());
    }

    public function test_payments_by_method_excludes_refunded_and_cancelled_orders(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $order = $this->paidOrder($shift, $admin, 100000);
        $this->cashPayment($order, $admin, 60000);
        $order->payments()->create([
            'method' => PaymentMethod::Qris,
            'amount' => 40000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $refunded = $this->paidOrder($shift, $admin, 50000, OrderStatus::Refunded);
        $this->cashPayment($refunded, $admin, 50000);

        $cancelled = $this->paidOrder($shift, $admin, 70000, OrderStatus::Cancelled);
        $this->cashPayment($cancelled, $admin, 70000);

        $pending = $this->paidOrder($shift, $admin, 99000, OrderStatus::Pending);
        $this->cashPayment($pending, $admin, 99000);

        $this->assertSame([
            'cash' => 60000,
            'qris' => 40000,
            'ewallet' => 0,
        ], $shift->paymentsByMethod());
    }

    public function test_payments_by_method_nets_partial_refunds_on_in_scope_orders(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $order = $this->paidOrder($shift, $admin, 50000);
        $this->cashPayment($order, $admin, 50000);
        $this->cashPayment($order, $admin, -15000); // partial refund, order stays paid

        $this->assertSame(35000, $shift->paymentsByMethod()['cash']);
        $this->assertSame(50000, $shift->cashPaid());
        $this->assertSame(-15000, $shift->cashRefunds());
    }

    public function test_expected_cash_ignores_fully_refunded_orders(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);

        $order = $this->paidOrder($shift, $admin, 50000);
        $this->cashPayment($order, $admin, 50000);

        // Fully refunded order: its payment rows (+50.000 capture, -50.000
        // refund) both drop out of the shift math — the drawer nets zero, so
        // expected cash returns to the opening amount.
        $refunded = $this->paidOrder($shift, $admin, 50000, OrderStatus::Refunded);
        $this->cashPayment($refunded, $admin, 50000);
        $this->cashPayment($refunded, $admin, -50000);

        $this->assertSame(150000, $shift->expectedCash());
        $this->assertSame(50000, $shift->cashPaid());
        $this->assertSame(0, $shift->cashRefunds());
    }

    public function test_payments_by_method_defaults_to_zero_when_shift_has_no_payments(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin);

        $this->assertSame(['cash' => 0, 'qris' => 0, 'ewallet' => 0], $shift->paymentsByMethod());
    }

    public function test_expected_cash_math_opening_plus_cash_payments_minus_cash_refunds(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 250000);

        // Cashier records the full tendered amount (change is not subtracted),
        // so order A contributes 200.000 to the drawer.
        $order = $this->paidOrder($shift, $admin, 150000);
        $this->cashPayment($order, $admin, 200000);
        $order->payments()->create([
            'method' => PaymentMethod::Qris,
            'amount' => 0, // QRIS settles remaining exactly
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $refunded = $this->paidOrder($shift, $admin, 50000);
        $this->cashPayment($refunded, $admin, 50000);
        $this->cashPayment($refunded, $admin, -15000); // cash refund row

        // 250.000 + 200.000 + 50.000 − 15.000 = 485.000
        $this->assertSame(485000, $shift->expectedCash());
    }

    public function test_expected_cash_math_with_no_cash_activity_equals_opening_cash(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);

        $order = $this->paidOrder($shift, $admin, 80000);
        $order->payments()->create([
            'method' => PaymentMethod::Ewallet,
            'amount' => 80000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $this->assertSame(100000, $shift->expectedCash());
    }

    public function test_cash_paid_sums_only_positive_cash_payments(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);

        $order = $this->paidOrder($shift, $admin, 100000);
        $this->cashPayment($order, $admin, 100000);
        $this->cashPayment($order, $admin, -10000);

        $this->assertSame(100000, $shift->cashPaid());
        $this->assertSame(-10000, $shift->cashRefunds());
    }

    public function test_discrepancy_is_counted_minus_expected_after_close(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);

        $order = $this->paidOrder($shift, $admin, 50000);
        $this->cashPayment($order, $admin, 50000);

        $shift->update([
            'closed_at' => now(),
            'closing_cash' => 160000,
            'expected_total' => 50000,
        ]);

        $this->assertSame(150000, $shift->expectedCash());
        $this->assertSame(10000, $shift->discrepancy());

        $shift->update(['closing_cash' => 140000]);

        $this->assertSame(-10000, $shift->discrepancy());
    }

    public function test_discrepancy_is_zero_while_shift_is_still_open(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);

        $order = $this->paidOrder($shift, $admin, 50000);
        $this->cashPayment($order, $admin, 50000);

        $this->assertNull($shift->closed_at);
        $this->assertSame(0, $shift->discrepancy());
    }

    public function test_cashier_order_attaches_to_the_active_shift(): void
    {
        Http::fake();
        $admin = $this->admin();
        $shift = $this->openShift($admin, 300000);
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame($shift->id, $order->shift_id);
        $this->assertDatabaseHas('orders', [
            'order_number' => $order->order_number,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_cashier_order_without_an_open_shift_keeps_shift_id_null(): void
    {
        Http::fake();
        $admin = $this->admin();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['shift_id' => null]);
    }

    public function test_opening_a_shift_via_page_stores_opening_cash_and_opened_at(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->set('openingCash', '500.000')
            ->call('openShift')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('shifts', 1);
        $this->assertDatabaseHas('shifts', [
            'opening_cash' => 500000,
            'expected_total' => null,
            'closing_cash' => null,
            'admin_id' => $admin->id,
        ]);

        $shift = Shift::firstOrFail();
        $this->assertNotNull($shift->opened_at);
        $this->assertNull($shift->closed_at);
    }

    public function test_opening_a_shift_requires_a_valid_cash_amount(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->call('openShift')
            ->assertHasErrors(['openingCash']);

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->set('openingCash', 'abc')
            ->call('openShift')
            ->assertHasErrors(['openingCash']);

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_a_second_shift_cannot_be_opened_while_one_is_running(): void
    {
        $admin = $this->admin();
        $this->openShift($admin, 100000);

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->set('openingCash', '200.000')
            ->call('openShift')
            ->assertHasErrors(['openingCash']);

        $this->assertDatabaseCount('shifts', 1);
        $this->assertDatabaseHas('shifts', ['opening_cash' => 100000]);
    }

    public function test_closing_a_shift_stores_closing_cash_expected_total_and_closed_at(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 500000);
        $this->paidOrder($shift, $admin, 30000);
        $this->paidOrder($shift, $admin, 20000, OrderStatus::Served);
        $this->paidOrder($shift, $admin, 99999, OrderStatus::Pending);

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->set('closingCash', '535.000')
            ->call('closeShift')
            ->assertHasNoErrors();

        $shift->refresh();
        $this->assertNotNull($shift->closed_at);
        $this->assertSame(535000, $shift->closing_cash);
        $this->assertSame(50000, $shift->expected_total);
    }

    public function test_closing_a_shift_requires_counted_cash_and_an_open_shift(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->call('closeShift')
            ->assertHasErrors(['closingCash']);

        $shift = $this->openShift($admin, 100000);

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->set('closingCash', 'xyz')
            ->call('closeShift')
            ->assertHasErrors(['closingCash']);

        $shift->refresh();
        $this->assertNull($shift->closed_at);
    }

    public function test_closing_a_shift_redirects_to_the_shift_report_page(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);

        Livewire::actingAs($admin, 'admin')
            ->test(ManageShift::class)
            ->set('closingCash', '120.000')
            ->call('closeShift')
            ->assertRedirect(ShiftReport::getUrl(['record' => $shift->id]));
    }

    public function test_shift_report_page_shows_totals_payment_split_and_cash_check(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 500000);
        $order = $this->paidOrder($shift, $admin, 30000);
        $this->cashPayment($order, $admin, 30000);
        $order2 = $this->paidOrder($shift, $admin, 20000, OrderStatus::Served);
        $order2->payments()->create([
            'method' => PaymentMethod::Ewallet,
            'amount' => 20000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $shift->update([
            'closed_at' => now(),
            'closing_cash' => 535000,
            'expected_total' => 50000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ShiftReport::class, ['record' => $shift->id])
            ->assertOk()
            ->assertSee('Z-Report')
            ->assertSee('JUMLAH PESANAN')
            ->assertSee('Rp 50.000')
            ->assertSee('Rp 30.000')
            ->assertSee('Rp 20.000')
            ->assertSee('Rp 530.000')
            ->assertSee('Rp 535.000')
            ->assertSee('+Rp 5.000');
    }

    public function test_z_report_route_requires_authentication(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 100000);

        $this->get(route('pos.zreport', $shift))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_z_report_renders_standalone_and_localized(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 500000);
        $order = $this->paidOrder($shift, $admin, 30000);
        $this->cashPayment($order, $admin, 30000);
        $order2 = $this->paidOrder($shift, $admin, 20000, OrderStatus::Served);
        $order2->payments()->create([
            'method' => PaymentMethod::Ewallet,
            'amount' => 20000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $shift->update([
            'closed_at' => now(),
            'closing_cash' => 535000,
            'expected_total' => 50000,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('pos.zreport', $shift))
            ->assertOk()
            ->assertSee('LAPORAN PENUTUPAN SHIFT')
            ->assertSee('JUMLAH PESANAN')
            ->assertSee('RINCIAN PEMBAYARAN')
            ->assertSee('PERIKSA KAS')
            ->assertSee('Rp 50.000')
            ->assertSee('Rp 30.000')
            ->assertSee('Rp 20.000')
            ->assertSee('Rp 530.000')
            ->assertSee('Rp 535.000')
            ->assertSee('+Rp 5.000')
            ->assertSee('window.print')
            ->assertDontSee('fi-main');
    }

    public function test_z_report_renders_in_english_with_lang_parameter(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 500000);
        $shift->update([
            'closed_at' => now(),
            'closing_cash' => 500000,
            'expected_total' => 0,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('pos.zreport', $shift).'?lang=en')
            ->assertOk()
            ->assertSee('SHIFT CLOSING REPORT')
            ->assertSee('EXPECTED CASH')
            ->assertSee('COUNTED CASH')
            ->assertSee('DISCREPANCY')
            ->assertSee('MATCH')
            ->assertDontSee('LAPORAN PENUTUPAN SHIFT');
    }
}
