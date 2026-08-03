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
                'customer_phone' => '081234567890',
                'status' => OrderStatus::Pending,
                'total' => '25.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('orders', [
            'customer_phone' => '081234567890',
            'total' => 25000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'status' => OrderStatus::Pending,
                'total' => '1.500.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('orders', [
            'total' => 1500000,
        ]);
    }

    public function test_create_form_auto_generates_a_unique_order_number(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'status' => OrderStatus::Pending,
                'total' => '30.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $first = Order::firstOrFail();

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $first->order_number);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'status' => OrderStatus::Pending,
                'total' => '31.000',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $numbers = Order::pluck('order_number')->all();

        $this->assertCount(2, $numbers);
        $this->assertCount(2, array_unique($numbers));
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
    // Resource hardening (Vikunja 160): zero totals are not storable, and
    // every previously-unlabeled field shows its localized label.
    // ---------------------------------------------------------------------

    public function test_create_form_rejects_zero_total(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->fillForm([
                'status' => OrderStatus::Pending,
                'total' => '0',
                'created_by' => $admin->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['total']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_create_form_renders_localized_labels_for_previously_unlabeled_fields(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateOrder::class)
            ->assertSee(__('orders.fields.order_number'))
            ->assertSee(__('orders.status'))
            ->assertSee(__('orders.fields.shift_id'))
            ->assertSee(__('orders.fields.created_by'));
    }

    public function test_orders_table_renders_localized_column_labels(): void
    {
        $admin = Admin::factory()->create();
        $this->createOrder([], $admin);

        $component = Livewire::actingAs($admin, 'admin')->test(ListOrders::class);

        $expected = [
            'order_number' => __('orders.fields.order_number'),
            'status' => __('orders.status'),
            'created_at' => __('orders.fields.created_at'),
            'updated_at' => __('orders.fields.updated_at'),
        ];

        foreach ($expected as $column => $label) {
            $this->assertSame($label, $component->instance()->getTable()->getColumn($column)->getLabel());
        }
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
