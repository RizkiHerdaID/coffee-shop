<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Cashier;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Card 101 + 102 regression — the 2026-08-03 audit's cash over-tender bug.
 *
 * An 85.500 order paid cash with 100.000 tendered used to store the full
 * 100.000 on payments.amount: the drawer was short by the 14.500 change,
 * Shift::cashPaid()/expectedCash() were overstated, and the Z-report cash
 * line could not reconcile with the drawer. After the fix the payment row
 * stores the APPLIED 85.500 with change 14.500 in its own column, and the
 * Z-report components sum to expectedCash() exactly.
 */
class OverTenderRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create();
    }

    private function openShift(Admin $admin, int $openingCash): Shift
    {
        return Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => $openingCash,
            'admin_id' => $admin->id,
        ]);
    }

    private function paidOrder(Shift $shift, Admin $admin, int $total): Order
    {
        return Order::create([
            'order_number' => 'OT-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Paid,
            'total' => $total,
            'shift_id' => $shift->id,
            'created_by' => $admin->id,
        ]);
    }

    private function cashPayment(Order $order, Admin $admin, int $amount, int $change = 0): Payment
    {
        return $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => $amount,
            'change' => $change,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);
    }

    private function deposit(Shift $shift, Admin $admin, int $amount): ShiftCashMovement
    {
        return ShiftCashMovement::create([
            'shift_id' => $shift->id,
            'type' => 'in',
            'amount' => $amount,
            'admin_id' => $admin->id,
        ]);
    }

    private function pettyOut(Shift $shift, Admin $admin, int $amount): ShiftCashMovement
    {
        return ShiftCashMovement::create([
            'shift_id' => $shift->id,
            'type' => 'out',
            'amount' => $amount,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_cashier_never_stores_the_tendered_amount_on_the_payment_row(): void
    {
        $admin = $this->admin();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'OT-CASHIER-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::Pending,
            'total' => 85500,
            'created_by' => $admin->id,
        ]));

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
        $this->assertSame(85500, $order->fresh()->paid_total);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_drawer_expected_cash_equals_opening_plus_applied_cash_only(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 250000);
        $order = $this->paidOrder($shift, $admin, 85500);
        $this->cashPayment($order, $admin, 85500, 14500);

        // Old behavior: cashPaid() 100.000 → expectedCash() 350.000. The
        // drawer only gained 85.500 → expectedCash() 335.500.
        $this->assertSame(85500, $shift->cashPaid());
        $this->assertSame(335500, $shift->expectedCash());
    }

    /**
     * Full money fixture from the audit: cash over-tender + QRIS + partial
     * refund + deposit + petty out. The Z-report must reconcile EXACTLY:
     * opening + applied cash − refunds + deposits − petty = expected.
     */
    public function test_z_report_reconciles_for_the_full_money_fixture(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 250000);

        // 85.500 order, 100.000 tendered → 14.500 change stored separately.
        $cashOrder = $this->paidOrder($shift, $admin, 85500);
        $this->cashPayment($cashOrder, $admin, 85500, 14500);

        $qrisOrder = $this->paidOrder($shift, $admin, 20000);
        $qrisOrder->payments()->create([
            'method' => PaymentMethod::Qris,
            'amount' => 20000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        // Paid order with a partial cash refund (order stays paid).
        $refunded = $this->paidOrder($shift, $admin, 50000);
        $this->cashPayment($refunded, $admin, 50000);
        $this->cashPayment($refunded, $admin, -10000);

        $this->deposit($shift, $admin, 50000);
        $this->pettyOut($shift, $admin, 15000);

        // 250.000 + 85.500 + 50.000 − 10.000 + 50.000 − 15.000 = 410.500
        $this->assertSame(135500, $shift->cashPaid());
        $this->assertSame(-10000, $shift->cashRefunds());
        $this->assertSame(410500, $shift->expectedCash());

        // Counted cash equals the expectation → zero discrepancy.
        $shift->update(['closed_at' => now(), 'closing_cash' => 410500]);
        $this->assertSame(0, $shift->discrepancy());

        $this->actingAs($admin, 'admin')
            ->get(route('pos.zreport', $shift))
            ->assertOk()
            ->assertSee('Rp 135.500') // cash: applied only, change excluded
            ->assertSee('Rp 20.000') // qris
            ->assertSee('Rp -10.000') // refunds, one exclusive line
            ->assertSee('Rp 50.000') // deposits
            ->assertSee('Rp 15.000') // petty out
            ->assertSee('Rp 410.500') // expected == counted → COCOK
            ->assertSee('COCOK');
    }

    public function test_partial_refund_after_over_tender_keeps_the_drawer_math_exact(): void
    {
        $admin = $this->admin();
        $shift = $this->openShift($admin, 200000);
        $order = $this->paidOrder($shift, $admin, 85500);
        $this->cashPayment($order, $admin, 85500, 14500);

        $this->actingAs($admin, 'admin');
        $this->assertTrue($order->refund(20000));

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(65500, $order->paid_total); // 85.500 − 20.000, change never included
        $this->assertSame(-20000, $shift->cashRefunds());
        $this->assertSame(265500, $shift->expectedCash()); // 200.000 + 85.500 − 20.000
    }
}
