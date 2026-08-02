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

    private function makeOrder(Admin $admin, int $total, Carbon $createdAt): Order
    {
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'CR-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Paid,
            'total' => $total,
            'created_by' => $admin->id,
        ]));

        $order->created_at = $createdAt;
        $order->save();

        return $order;
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

    public function test_discrepancy_is_expected_minus_counted(): void
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

        $this->assertDatabaseHas('cash_register_sessions', [
            'opening_float' => 100000,
            'expected_amount' => 150000,
            'counted_amount' => 120000,
            'discrepancy' => 30000,
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
}
