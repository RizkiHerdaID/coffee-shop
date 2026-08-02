<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\CashRegisterSessions\Pages\CreateCashRegisterSession;
use App\Filament\Resources\CashRegisterSessions\Pages\EditCashRegisterSession;
use App\Filament\Resources\CashRegisterSessions\Pages\ListCashRegisterSessions;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Admin;
use App\Models\CashRegisterSession;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Admin $admin, int $total, Carbon $createdAt, OrderStatus $status = OrderStatus::Paid, ?string $discountType = null, ?int $discountAmount = null): Order
    {
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'CR-'.Str::upper(Str::random(8)),
            'status' => $status,
            'total' => $total,
            'discount_type' => $discountType,
            'discount_amount' => $discountAmount,
            'created_by' => $admin->id,
        ]));

        $order->created_at = $createdAt;
        $order->save();

        return $order;
    }

    private function openSession(Admin $admin, ?Carbon $openedAt = null, ?Carbon $closedAt = null): CashRegisterSession
    {
        return CashRegisterSession::create([
            'opened_at' => $openedAt ?? Carbon::now()->subHour(),
            'closed_at' => $closedAt,
            'opening_float' => 100000,
            'expected_amount' => 100000,
            'counted_amount' => null,
            'discrepancy' => null,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_create_session_stores_opening_float_and_defaults_to_open_status(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateCashRegisterSession::class)
            ->fillForm([
                'opening_float' => '100.000',
                'admin_id' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cash_register_sessions', [
            'admin_id' => $admin->id,
            'opening_float' => 100000,
            'expected_amount' => 100000,
            'status' => 'open',
        ]);
    }

    public function test_expected_amount_includes_order_revenue_within_session_window(): void
    {
        $admin = Admin::factory()->create();
        $this->makeOrder($admin, 50000, Carbon::now()->subMinutes(30));

        Livewire::actingAs($admin, 'admin')
            ->test(CreateCashRegisterSession::class)
            ->fillForm([
                'opened_at' => Carbon::now()->subHour()->toDateTimeString(),
                'opening_float' => '100.000',
                'admin_id' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cash_register_sessions', [
            'opening_float' => 100000,
            'expected_amount' => 150000,
        ]);
    }

    public function test_revenue_excludes_orders_outside_the_session_window(): void
    {
        $admin = Admin::factory()->create();

        $this->makeOrder($admin, 40000, Carbon::now()->subHours(4));
        $this->makeOrder($admin, 60000, Carbon::now()->subHours(2));
        $this->makeOrder($admin, 20000, Carbon::now()->subMinutes(30));

        $session = CashRegisterSession::create([
            'opened_at' => Carbon::now()->subHours(3),
            'closed_at' => Carbon::now()->subHour(),
            'opening_float' => 100000,
            'expected_amount' => 100000,
            'counted_amount' => 160000,
            'discrepancy' => 0,
            'admin_id' => $admin->id,
        ]);

        $this->assertSame(60000, $session->revenue());
        $this->assertSame(160000, $session->expectedAmount());
    }

    /**
     * Card 105: discrepancy sign matches Shift::discrepancy() — COUNTED minus
     * expected (negative when the drawer is short).
     */
    public function test_discrepancy_is_counted_minus_expected(): void
    {
        $admin = Admin::factory()->create();
        $this->makeOrder($admin, 50000, Carbon::now()->subMinutes(30));

        Livewire::actingAs($admin, 'admin')
            ->test(CreateCashRegisterSession::class)
            ->fillForm([
                'opened_at' => Carbon::now()->subHour()->toDateTimeString(),
                'opening_float' => '100.000',
                'counted_amount' => '150.000',
                'admin_id' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cash_register_sessions', [
            'opening_float' => 100000,
            'expected_amount' => 150000,
            'counted_amount' => 150000,
            'discrepancy' => 0,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateCashRegisterSession::class)
            ->fillForm([
                'opened_at' => Carbon::now()->subHour()->toDateTimeString(),
                'opening_float' => '100.000',
                'counted_amount' => '120.000',
                'admin_id' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Counted 120.000 − expected 150.000 = −30.000 (drawer short).
        $this->assertDatabaseHas('cash_register_sessions', [
            'opening_float' => 100000,
            'expected_amount' => 150000,
            'counted_amount' => 120000,
            'discrepancy' => -30000,
        ]);
    }

    public function test_edit_form_displays_formatted_computed_values(): void
    {
        $admin = Admin::factory()->create();
        $session = CashRegisterSession::create([
            'opened_at' => Carbon::now()->subHour(),
            'opening_float' => 1000000,
            'expected_amount' => 1500000,
            'counted_amount' => 1500000,
            'discrepancy' => 0,
            'admin_id' => $admin->id,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditCashRegisterSession::class, ['record' => $session->getRouteKey()])
            ->assertFormSet([
                'opening_float' => '1.000.000',
                'expected_amount' => '1.500.000',
                'counted_amount' => '1.500.000',
            ]);
    }

    public function test_expenses_and_cash_register_list_pages_load(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(ListExpenses::class)
            ->assertOk();

        Livewire::actingAs($admin, 'admin')
            ->test(ListCashRegisterSessions::class)
            ->assertOk();
    }

    /**
     * Card 104: CashRegisterSession::revenue() mirrors Shift — paid/served
     * orders only, NET totals — so expectedAmount() can never include
     * pending/refunded/cancelled gross money.
     */
    public function test_revenue_counts_only_paid_and_served_orders_in_the_window(): void
    {
        $admin = Admin::factory()->create();
        $this->makeOrder($admin, 50000, Carbon::now()->subMinutes(30));                    // paid
        $this->makeOrder($admin, 99999, Carbon::now()->subMinutes(20), OrderStatus::Pending);
        $this->makeOrder($admin, 99999, Carbon::now()->subMinutes(10), OrderStatus::Refunded);
        $this->makeOrder($admin, 99999, Carbon::now()->subMinutes(5), OrderStatus::Cancelled);

        $session = $this->openSession($admin);

        $this->assertSame(50000, $session->revenue());
        $this->assertSame(150000, $session->expectedAmount());
    }

    public function test_revenue_uses_net_totals_of_discounted_orders(): void
    {
        $admin = Admin::factory()->create();
        $this->makeOrder($admin, 65000, Carbon::now()->subMinutes(30), OrderStatus::Paid, 'fixed', 10000);
        $this->makeOrder($admin, 20000, Carbon::now()->subMinutes(20), OrderStatus::Served);

        $session = $this->openSession($admin);

        $this->assertSame(75000, $session->revenue());
        $this->assertSame(175000, $session->expectedAmount());
    }

    public function test_expected_amount_uses_net_revenue_on_the_create_form(): void
    {
        $admin = Admin::factory()->create();
        $this->makeOrder($admin, 65000, Carbon::now()->subMinutes(30), OrderStatus::Paid, 'fixed', 10000);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateCashRegisterSession::class)
            ->fillForm([
                'opened_at' => Carbon::now()->subHour()->toDateTimeString(),
                'opening_float' => '100.000',
                'admin_id' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cash_register_sessions', [
            'opening_float' => 100000,
            'expected_amount' => 155000,
        ]);
    }
}
