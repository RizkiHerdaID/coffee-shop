<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_create_form_stores_total_as_raw_integer_from_indonesian_formatted_input(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'order_number' => 'TOTAL-001',
                'customer_phone' => '081234567890',
                'status' => OrderStatus::Pending,
                'total' => '25.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'TOTAL-001',
            'total' => 25000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'order_number' => 'TOTAL-002',
                'status' => OrderStatus::Pending,
                'total' => '1.500.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('orders', [
            'order_number' => 'TOTAL-002',
            'total' => 1500000,
        ]);
    }

    public function test_edit_form_displays_total_with_indonesian_separators(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'TOTAL-003',
            'status' => OrderStatus::Pending,
            'total' => 1500000,
            'created_by' => $admin->id,
        ]));

        Livewire::actingAs($admin, 'admin')
            ->test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormSet(['total' => '1.500.000']);
    }

    // ---------------------------------------------------------------------
    // Delete protection (Vikunja 107): orders are immutable audit records.
    // ---------------------------------------------------------------------

    public function test_orders_resource_has_no_delete_affordances(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'DEL-001',
            'status' => OrderStatus::Paid,
            'total' => 10000,
            'created_by' => $admin->id,
        ]));

        Livewire::actingAs($admin, 'admin')
            ->test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionDoesNotExist('delete');

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');
    }

    // ---------------------------------------------------------------------
    // Closed-shift freeze (Vikunja 108): paid/status mutations on orders
    // whose shift is closed would mutate the already-frozen Z-report.
    // ---------------------------------------------------------------------

    private function createShift(?string $closedAt = null): Shift
    {
        $admin = Admin::factory()->create();

        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 100000,
            'admin_id' => $admin->id,
        ]);

        if ($closedAt !== null) {
            $shift->update([
                'closed_at' => $closedAt,
                'closing_cash' => 100000,
                'expected_total' => 0,
            ]);
        }

        return $shift;
    }

    private function createOrder(array $attributes = [], ?Admin $admin = null): Order
    {
        $admin ??= Admin::factory()->create();

        return Order::withoutEvents(fn () => Order::create(array_merge([
            'order_number' => 'ORD-'.fake()->unique()->numberBetween(100000, 999999),
            'status' => OrderStatus::Pending,
            'total' => 30000,
            'created_by' => $admin->id,
        ], $attributes)));
    }

    public function test_mark_paid_action_is_hidden_on_orders_in_closed_shifts(): void
    {
        $admin = Admin::factory()->create();

        $inClosedShift = $this->createOrder([
            'status' => OrderStatus::Pending,
            'shift_id' => $this->createShift(now())->id,
        ], $admin);

        $inOpenShift = $this->createOrder([
            'status' => OrderStatus::Pending,
            'shift_id' => $this->createShift()->id,
        ], $admin);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->assertTableActionHidden('markPaid', $inClosedShift)
            ->assertTableActionVisible('markPaid', $inOpenShift);
    }

    public function test_mark_served_action_is_hidden_on_orders_in_closed_shifts(): void
    {
        $admin = Admin::factory()->create();

        $inClosedShift = $this->createOrder([
            'status' => OrderStatus::Paid,
            'shift_id' => $this->createShift(now())->id,
        ], $admin);

        $inOpenShift = $this->createOrder([
            'status' => OrderStatus::Paid,
            'shift_id' => $this->createShift()->id,
        ], $admin);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->assertTableActionHidden('markServed', $inClosedShift)
            ->assertTableActionVisible('markServed', $inOpenShift);
    }

    public function test_closed_shift_order_cannot_be_edited_via_the_form(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->createOrder([
            'status' => OrderStatus::Pending,
            'shift_id' => $this->createShift(now())->id,
        ], $admin);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm([
                'order_number' => $order->order_number,
                'status' => OrderStatus::Paid,
                'total' => '30.000',
                'created_by' => $admin->id,
            ])
            ->call('save')
            ->assertNotified(__('orders.shift_closed_edit_blocked'));

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    // ---------------------------------------------------------------------
    // markPaid toast honesty (Vikunja 116): no success toast on a no-op.
    // ---------------------------------------------------------------------

    public function test_mark_paid_no_op_does_not_show_success_notification(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->createOrder(['status' => OrderStatus::Pending, 'total' => 50000], $admin);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 20000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->callTableAction('markPaid', $order->getRouteKey())
            ->assertNotified(__('pos.actions.marked_paid_pending', ['order_number' => $order->order_number]))
            ->assertNotNotified(__('pos.actions.marked_paid', ['order_number' => $order->order_number]));

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_mark_paid_shows_success_notification_when_payment_covers_total(): void
    {
        $admin = Admin::factory()->create();
        $order = $this->createOrder(['status' => OrderStatus::Pending, 'total' => 50000], $admin);
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 50000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListOrders::class)
            ->callTableAction('markPaid', $order->getRouteKey())
            ->assertNotified(__('pos.actions.marked_paid', ['order_number' => $order->order_number]));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    // ---------------------------------------------------------------------
    // OrderForm total mask idempotency (Vikunja 116): failed-validation
    // re-hydration must not mangle 25.000 into 25.000.000.
    // ---------------------------------------------------------------------

    public function test_total_mask_is_idempotent_when_state_is_rehydrated(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'MASK-001',
            'status' => OrderStatus::Pending,
            'total' => 25000,
            'created_by' => $admin->id,
        ]));

        $component = Livewire::actingAs($admin, 'admin')
            ->test(EditOrder::class, ['record' => $order->getRouteKey()]);

        // Simulate the re-hydration Filament performs after a failed
        // validation submit: the raw state already carries the dotted mask.
        $component->instance()->form->fill(['total' => '25.000']);

        $component->assertFormSet(['total' => '25.000']);
    }

    public function test_order_form_total_formatter_is_idempotent(): void
    {
        $this->assertSame('25.000', OrderForm::formatTotal('25.000'));
        $this->assertSame('1.500.000', OrderForm::formatTotal('1.500.000'));
        $this->assertSame('25.000', OrderForm::formatTotal(25000));
        $this->assertSame('1.500.000', OrderForm::formatTotal(1500000));
        $this->assertNull(OrderForm::formatTotal(null));
    }
}
