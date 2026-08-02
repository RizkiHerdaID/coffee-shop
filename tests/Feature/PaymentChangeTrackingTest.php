<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Cashier;
use App\Jobs\PrintReceipt;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Shift;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Card 101 — payments.change tracking.
 *
 * Payment rows store the APPLIED amount (what counts toward the order and
 * the drawer); the tendered overage lives in payments.change so the drawer
 * math (Shift::cashPaid / expectedCash) never absorbs change. The thermal
 * receipt prints the KEMBALIAN line from the STORED change, not from
 * paid_total − net_total (which is always 0 once amounts are applied).
 */
class PaymentChangeTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
        Http::fake();
    }

    private function makeOrder(Admin $admin, int $total): Order
    {
        return Order::withoutEvents(fn () => Order::create([
            'order_number' => 'CHG-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => OrderStatus::Pending,
            'total' => $total,
            'created_by' => $admin->id,
        ]));
    }

    public function test_change_is_stored_on_the_payment_row_and_excluded_from_paid_total(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 85500);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '100.000')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 14500);

        $payment = $order->payments()->firstOrFail();
        $this->assertSame(85500, $payment->amount);
        $this->assertSame(14500, $payment->change);

        // paid_total measures applied amounts only — change never inflates it.
        $this->assertSame(85500, $order->fresh()->paid_total);
        $this->assertSame(85500, $order->fresh()->net_total);
        $this->assertSame(0, $order->fresh()->remaining);
    }

    public function test_change_defaults_to_zero_for_exact_and_non_cash_payments(): void
    {
        $admin = Admin::factory()->create();

        $exact = $this->makeOrder($admin, 50000);
        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $exact->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '50.000')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $qris = $this->makeOrder($admin, 40000);
        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $qris->id)
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', '40.000')
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

    public function test_shift_drawer_math_excludes_change(): void
    {
        $admin = Admin::factory()->create();
        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 500000,
            'admin_id' => $admin->id,
        ]);

        $order = Order::create([
            'order_number' => 'CHG-SHIFT-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::Paid,
            'total' => 85500,
            'shift_id' => $shift->id,
            'created_by' => $admin->id,
        ]);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 85500,
            'change' => 14500, // 100.000 tendered
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $this->assertSame(85500, $shift->cashPaid());
        $this->assertSame(585500, $shift->expectedCash()); // 500.000 + 85.500, NOT 600.000
        $this->assertSame(['cash' => 85500, 'qris' => 0, 'ewallet' => 0], $shift->paymentsByMethod());
    }

    public function test_refund_rows_do_not_carry_change(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 50000);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 50000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $order->update(['status' => OrderStatus::Paid]);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(20000));

        $refund = $order->payments()->latest('id')->firstOrFail();
        $this->assertSame(-20000, $refund->amount);
        $this->assertSame(0, $refund->change);
    }

    /**
     * The receipt change line must come from the STORED change (14.500),
     * not from paid_total − net_total (0 after the fix) — the regression
     * guard from the 2026-08-03 over-tender audit.
     */
    public function test_receipt_change_line_uses_the_stored_change(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 85500);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 85500,
            'change' => 14500,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $order->update(['status' => OrderStatus::Paid]);

        $path = tempnam(sys_get_temp_dir(), 'pos-receipt-');
        config()->set('pos.printer', [
            'enabled' => true,
            'connection' => 'file',
            'address' => $path,
            'port' => 9100,
            'chars_per_line' => 32,
        ]);

        (new PrintReceipt($order))->handle();

        $bytes = file_get_contents($path);
        @unlink($path);

        $this->assertNotFalse($bytes);
        $this->assertStringContainsString('KEMBALIAN', $bytes);
        $this->assertStringContainsString('14.500', $bytes);
    }

    public function test_receipt_prints_no_change_line_when_change_is_zero(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->makeOrder($admin, 50000);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 50000,
            'change' => 0,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
        $order->update(['status' => OrderStatus::Paid]);

        $path = tempnam(sys_get_temp_dir(), 'pos-receipt-');
        config()->set('pos.printer', [
            'enabled' => true,
            'connection' => 'file',
            'address' => $path,
            'port' => 9100,
            'chars_per_line' => 32,
        ]);

        (new PrintReceipt($order))->handle();

        $bytes = file_get_contents($path);
        @unlink($path);

        $this->assertNotFalse($bytes);
        $this->assertStringNotContainsString('KEMBALIAN', $bytes);
    }
}
