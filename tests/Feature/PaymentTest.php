<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Cashier;
use App\Jobs\PrintKitchenTicket;
use App\Jobs\PrintReceipt;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shift;
use Database\Seeders\MenuSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * POS payment capture + status transitions (milestone M2).
 *
 * Exercises the M2 surface on the Filament custom page
 * App\Filament\Pages\Cashier (route filament.admin.pages.cashier):
 *
 * - public ?int    $selectedOrderId = null   order being paid; createOrder()
 *     auto-selects the new order, selectOrder(int $orderId) re-selects
 * - public string  $paymentMethod = 'cash'   one of PaymentMethod values
 * - public ?int    $paymentAmount = null     integer IDR; for cash this is
 *     the TENDERED amount (overpayment allowed -> change due); null falls
 *     back to the order total for qris/ewallet
 * - public ?string $paymentReference = null  optional (qris/ewallet)
 * - selectOrder(int $orderId): void
 * - capturePayment(): void  validates (selected order, method, amount),
 *     creates a Payment row (method/amount/reference/paid_at/admin_id),
 *     transitions pending -> paid once sum(payments.amount) >= total,
 *     then dispatches PrintReceipt + PrintKitchenTicket for the order
 * - changeDue (computed, cash only): tendered - total, 0 when no overpayment
 * - markServed(int $orderId): void  transitions paid -> served; rejected
 *     for non-paid orders (ValidationException pattern, order unchanged)
 *
 * Only admins (guard 'admin') can capture payments / transition orders —
 * guests are redirected to the panel login.
 *
 * Out of scope here: Orders-resource record actions (transitions live on
 * the cashier page per the M2 contract's AND/OR split), printable receipt
 * view, printer hardware behavior.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
        Http::fake();
    }

    public function test_guest_cannot_access_the_cashier_page_to_capture_payments(): void
    {
        $this->get(route('filament.admin.pages.cashier'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_cash_overpayment_applies_only_the_order_total_and_tracks_the_change(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 100000)
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 35000);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 65000,
            'change' => 35000,
            'reference' => null,
            'admin_id' => $admin->id,
        ]);

        $payment = $order->payments()->firstOrFail();
        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertNotNull($payment->paid_at);
        $this->assertTrue($payment->paid_at->isPast());

        // The applied amount (never the tendered 100.000) feeds paid_total,
        // so the drawer math cannot absorb change.
        $this->assertSame(65000, $order->fresh()->paid_total);
        $this->assertSame(65000, $order->fresh()->net_total);
        $this->assertSame(0, $order->fresh()->remaining);
    }

    public function test_exact_cash_and_non_cash_payments_store_zero_change(): void
    {
        $admin = Admin::factory()->create();

        $exact = $this->makeOrder($admin, 50000);
        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $exact->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 50000)
            ->call('capturePayment')
            ->assertHasNoErrors();

        $qris = $this->makeOrder($admin, 40000);
        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $qris->id)
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', 40000)
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'order_id' => $exact->id,
            'method' => 'cash',
            'amount' => 50000,
            'change' => 0,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $qris->id,
            'method' => 'qris',
            'amount' => 40000,
            'change' => 0,
        ]);
    }

    public function test_partial_cash_payment_stores_applied_amount_without_change(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 20000)
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 0);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 20000,
            'change' => 0,
        ]);
        $this->assertSame(20000, $order->fresh()->paid_total);
    }

    public function test_cash_payment_with_exact_amount_marks_order_paid_without_change(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 65000)
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 0);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 65000,
        ]);
    }

    public function test_qris_payment_stores_reference_and_marks_order_paid(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'qris')
            ->set('paymentReference', 'QRIS-ABC-123456')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 65000,
            'reference' => 'QRIS-ABC-123456',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_ewallet_payment_without_reference_is_allowed(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'ewallet')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'ewallet',
            'amount' => 65000,
            'reference' => null,
        ]);
    }

    public function test_partial_payments_keep_order_pending_until_paid_in_full(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);
        $component->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 30000)
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);

        $component->set('paymentAmount', 35000)
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(65000, (int) $order->payments()->sum('amount'));
    }

    public function test_capture_payment_requires_a_selected_order(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 100000)
            ->call('capturePayment')
            ->assertHasErrors(['selectedOrderId']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_capture_payment_with_invalid_method_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'bitcoin')
            ->set('paymentAmount', 65000)
            ->call('capturePayment')
            ->assertHasErrors(['paymentMethod']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cash_payment_with_zero_amount_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 0)
            ->call('capturePayment')
            ->assertHasErrors(['paymentAmount']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_capturing_an_additional_payment_on_an_already_paid_order_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);
        $this->recordPayment($admin, $order, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 10000)
            ->call('capturePayment')
            ->assertHasErrors(['selectedOrderId']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_pending_order_cannot_be_marked_served(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('markServed', $order->id)
            ->assertHasErrors(['selectedOrderId']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_paid_order_can_be_marked_served(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);
        $this->recordPayment($admin, $order, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('markServed', $order->id)
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    public function test_print_receipt_and_kitchen_ticket_jobs_are_dispatched_when_order_becomes_paid(): void
    {
        Queue::fake();

        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'qris')
            ->set('paymentReference', 'QRIS-REF-1')
            ->call('capturePayment')
            ->assertHasNoErrors();

        Queue::assertPushed(PrintReceipt::class, fn (PrintReceipt $job) => $job->order->is($order));
        Queue::assertPushed(PrintKitchenTicket::class, fn (PrintKitchenTicket $job) => $job->order->is($order));
    }

    public function test_print_jobs_are_queued_and_carry_the_order(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        $receipt = new PrintReceipt($order);
        $this->assertInstanceOf(ShouldQueue::class, $receipt);
        $this->assertTrue($receipt->order->is($order));

        $ticket = new PrintKitchenTicket($order);
        $this->assertInstanceOf(ShouldQueue::class, $ticket);
        $this->assertTrue($ticket->order->is($order));
    }

    public function test_print_receipt_handles_missing_printer_gracefully(): void
    {
        config()->set('pos.printer', null);

        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        (new PrintReceipt($order))->handle();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_kitchen_ticket_handles_missing_printer_gracefully(): void
    {
        config()->set('pos.printer', null);

        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 65000);

        (new PrintKitchenTicket($order))->handle();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_cashier_can_pay_an_order_just_created_on_the_page(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso Uji', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant Uji', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->order_number);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->assertSet('selectedOrderId', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', 45000)
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 45000,
            'admin_id' => $admin->id,
        ]);
    }

    /**
     * Create a pending order directly (no booted-hook side effects).
     */
    private function makeOrder(Admin $admin, int $total, ?Shift $shift = null): Order
    {
        return Order::withoutEvents(fn () => Order::create([
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => OrderStatus::Pending,
            'total' => $total,
            'shift_id' => $shift?->id,
            'created_by' => $admin->id,
        ]));
    }

    // ---------------------------------------------------------------------
    // Closed-shift freeze on payment capture (Vikunja 135)
    // ---------------------------------------------------------------------

    /**
     * A pending order on a CLOSED shift must not receive payments: the
     * capture would retroactively mutate the printed Z-report (salesTotal /
     * expectedCash / paymentsByMethod of the closed shift would change).
     */
    public function test_capture_payment_on_order_with_closed_shift_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);
        $order = $this->makeOrder($admin, 65000, $shift);
        $shift->update(['closed_at' => now(), 'closing_cash' => 600000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '65.000')
            ->call('capturePayment')
            ->assertHasErrors(['selectedOrderId' => __('pos.payment.shift_closed')]);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * Defense in depth: even with full payment coverage already recorded, the
     * model's markPaidIfCovered must refuse to flip a closed-shift order to
     * paid (the Orders-table markPaid action and any future caller).
     */
    public function test_mark_paid_if_covered_returns_false_on_order_with_closed_shift(): void
    {
        $admin = Admin::factory()->create();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);
        $order = $this->makeOrder($admin, 65000, $shift);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 65000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $shift->update(['closed_at' => now(), 'closing_cash' => 600000]);

        $this->assertFalse($order->markPaidIfCovered());

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    /**
     * The rejection attempt must leave the closed shift's Z-report math
     * exactly as printed: sales total, expected cash, payment split and
     * discrepancy are all read fresh from the DB after the failed capture.
     */
    public function test_rejected_capture_on_closed_shift_leaves_z_report_math_untouched(): void
    {
        $admin = Admin::factory()->create();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);

        $paid = $this->makeOrder($admin, 50000, $shift);
        $paid->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 50000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $paid->update(['status' => OrderStatus::Paid]);

        $pending = $this->makeOrder($admin, 65000, $shift);
        $shift->update(['closed_at' => now(), 'closing_cash' => 600000]);

        $salesBefore = $shift->salesTotal();
        $expectedBefore = $shift->expectedCash();
        $byMethodBefore = $shift->paymentsByMethod();
        $discrepancyBefore = $shift->discrepancy();

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $pending->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '65.000')
            ->call('capturePayment')
            ->assertHasErrors(['selectedOrderId' => __('pos.payment.shift_closed')]);

        $this->assertSame($salesBefore, $shift->refresh()->salesTotal());
        $this->assertSame($expectedBefore, $shift->expectedCash());
        $this->assertSame($byMethodBefore, $shift->paymentsByMethod());
        $this->assertSame($discrepancyBefore, $shift->discrepancy());
        $this->assertSame(OrderStatus::Pending, $pending->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    // ---------------------------------------------------------------------
    // Closed-shift / stale-state guard on markServed (Vikunja 149)
    // ---------------------------------------------------------------------

    /**
     * The cashier page must reject marking served an order whose shift
     * closed since the page rendered (markServedIfPaid claim fails).
     */
    public function test_marking_served_on_order_with_closed_shift_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);
        $order = $this->makeOrder($admin, 65000, $shift);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 65000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $order->update(['status' => OrderStatus::Paid]);
        $shift->update(['closed_at' => now(), 'closing_cash' => 600000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('markServed', $order->id)
            ->assertHasErrors(['selectedOrderId']);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    /**
     * Happy path for the model claim used by the cashier page and the Orders
     * table: paid order on an open shift transitions paid -> served once.
     */
    public function test_mark_served_if_paid_transitions_a_paid_order_on_an_open_shift(): void
    {
        $admin = Admin::factory()->create();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);
        $order = $this->makeOrder($admin, 65000, $shift);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 65000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $order->update(['status' => OrderStatus::Paid]);

        $this->assertTrue($order->markServedIfPaid());

        $this->assertSame(OrderStatus::Served, $order->fresh()->status);
    }

    /**
     * Pay an order in full through the cashier page (used as fixture setup).
     */
    private function recordPayment(Admin $admin, Order $order, int $amount): void
    {
        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', $amount)
            ->call('capturePayment')
            ->assertHasNoErrors();
    }
}
