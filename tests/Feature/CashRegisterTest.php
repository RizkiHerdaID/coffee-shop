<?php

namespace Tests\Feature;

use App\Enums\CashRegisterStatus;
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
use Illuminate\Support\Facades\Lang;
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
     * Card 161: the sessions table renders the LIVE expected amount
     * (opening_float + revenue within the session window) instead of the
     * stored expected_amount column, which goes stale once orders are paid
     * after the session row was written. A closed session is unchanged:
     * orders paid after closed_at are outside its window.
     */
    public function test_table_expected_amount_renders_live_value(): void
    {
        $admin = Admin::factory()->create();

        $open = $this->openSession($admin); // stored expected_amount = 100000
        $closed = $this->openSession(
            $admin,
            Carbon::now()->subHours(3),
            Carbon::now()->subHours(2),
        );

        $this->makeOrder($admin, 50000, Carbon::now()->subMinutes(30)); // in the open window
        $this->makeOrder($admin, 70000, Carbon::now()->subMinutes(100)); // after the closed session, before the open one

        Livewire::actingAs($admin, 'admin')
            ->test(ListCashRegisterSessions::class)
            ->assertTableColumnStateSet('expected_amount', 150000, $open)
            ->assertTableColumnStateSet('expected_amount', 100000, $closed);
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

    /**
     * Card 161: the sessions table renders the LIVE expectedAmount() —
     * opening_float + revenue in the session window — NOT the stored
     * expected_amount snapshot, which goes stale when a paid order lands
     * after the session was opened. The stored column must stay 100000
     * (legacy snapshot) while the table shows 150000.
     */
    public function test_table_renders_live_expected_amount_for_an_open_session(): void
    {
        $admin = Admin::factory()->create();
        $session = $this->openSession($admin);
        $this->makeOrder($admin, 50000, Carbon::now()->subMinutes(30));

        $this->assertSame(100000, $session->fresh()->expected_amount);
        $this->assertSame(150000, $session->fresh()->expectedAmount());

        Livewire::actingAs($admin, 'admin')
            ->test(ListCashRegisterSessions::class)
            ->assertTableColumnStateSet('expected_amount', 150000, $session);
    }

    /**
     * Card 161: the live value for a CLOSED session is frozen at the close
     * — an order paid after closed_at must not inflate the table's
     * expected_amount (the window is bounded by closed_at).
     */
    public function test_table_expected_amount_is_unchanged_for_a_closed_session(): void
    {
        $admin = Admin::factory()->create();
        $session = $this->openSession($admin, Carbon::now()->subHours(4), Carbon::now()->subHours(2));
        $this->makeOrder($admin, 50000, Carbon::now()->subHours(3));
        $session->forceFill(['expected_amount' => 150000])->save();
        $this->makeOrder($admin, 20000, Carbon::now()->subHour());

        $this->assertSame(150000, $session->fresh()->expectedAmount());

        Livewire::actingAs($admin, 'admin')
            ->test(ListCashRegisterSessions::class)
            ->assertTableColumnStateSet('expected_amount', 150000, $session);
    }

    /**
     * Card 162: cash-register session UI labels resolve from the new
     * cash-register-sessions lang namespace in BOTH locales — a raw
     * 'cash-register-sessions.*' key must never surface. The key list
     * mirrors exactly what the enum, form, and table use.
     */
    public function test_cash_register_labels_resolve_from_the_new_namespace(): void
    {
        foreach (['id', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ([
                'status.open', 'status.closed',
                'fields.opened_at', 'fields.closed_at', 'fields.opening_float',
                'fields.expected_amount', 'fields.counted_amount', 'fields.discrepancy',
                'fields.status', 'fields.admin', 'fields.created_at',
                'hints.expected_formula',
                'empty.sessions_heading', 'empty.sessions_description',
            ] as $key) {
                $this->assertTrue(
                    Lang::has("cash-register-sessions.$key", $locale),
                    "cash-register-sessions.$key must exist in the $locale locale",
                );
            }

            $this->assertSame(__('cash-register-sessions.status.open'), CashRegisterStatus::Open->getLabel());
            $this->assertSame(__('cash-register-sessions.status.closed'), CashRegisterStatus::Closed->getLabel());
            $this->assertNotSame('cash-register-sessions.status.open', CashRegisterStatus::Open->getLabel());
        }
    }

    public function test_cash_register_sessions_lang_files_share_the_same_key_structure(): void
    {
        $id = require lang_path('id/cash-register-sessions.php');
        $en = require lang_path('en/cash-register-sessions.php');

        $this->assertSame(
            $this->flattenKeys($id),
            $this->flattenKeys($en)
        );
    }

    /**
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    private function flattenKeys(array $array): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $keys[] = (string) $key;

            if (is_array($value)) {
                foreach ($this->flattenKeys($value) as $nested) {
                    $keys[] = "$key.$nested";
                }
            }
        }

        return $keys;
    }
}
