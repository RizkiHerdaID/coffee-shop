<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\PrintKitchenTicket;
use App\Jobs\PrintReceipt;
use App\Jobs\SendOrderConfirmation;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_be_created_with_items_and_total_matches_subtotals(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $order = Order::create([
            'order_number' => 'ORD-0001',
            'total' => 65000,
            'created_by' => $admin->id,
        ])->refresh();

        $order->items()->create([
            'menu_item_id' => $espresso->id,
            'name' => $espresso->name,
            'price' => $espresso->price,
            'qty' => 2,
            'subtotal' => 40000,
        ]);

        $order->items()->create([
            'menu_item_id' => $croissant->id,
            'name' => $croissant->name,
            'price' => $croissant->price,
            'qty' => 1,
            'subtotal' => 25000,
        ]);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(65000, $order->items()->sum('subtotal'));
        $this->assertSame(65000, $order->total);
    }

    public function test_order_status_flows_from_pending_to_paid_to_served(): void
    {
        $admin = Admin::factory()->create();

        $order = Order::create([
            'order_number' => 'ORD-0002',
            'total' => 30000,
            'created_by' => $admin->id,
        ])->refresh();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('pending', $order->getRawOriginal('status'));

        $order->update(['status' => OrderStatus::Paid]);

        $this->assertSame(OrderStatus::Paid, $order->status);

        $order->update(['status' => OrderStatus::Served]);

        $this->assertSame(OrderStatus::Served, $order->status);
    }

    public function test_order_item_snapshots_name_and_price_when_menu_item_changes(): void
    {
        $admin = Admin::factory()->create();
        $menuItem = MenuItem::create(['name' => 'Cappuccino', 'price' => 25000]);

        $order = Order::create([
            'order_number' => 'ORD-0003',
            'total' => 25000,
            'created_by' => $admin->id,
        ]);

        $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'name' => $menuItem->name,
            'price' => $menuItem->price,
            'qty' => 1,
            'subtotal' => 25000,
        ]);

        $menuItem->update(['name' => 'Cappuccino XL', 'price' => 30000]);

        $item = $order->items()->first();

        $this->assertSame('Cappuccino', $item->name);
        $this->assertSame(25000, $item->price);
        $this->assertSame('Cappuccino XL', $item->menuItem->name);
    }

    public function test_payment_can_be_recorded_against_an_order(): void
    {
        $admin = Admin::factory()->create();

        $order = Order::create([
            'order_number' => 'ORD-0004',
            'status' => OrderStatus::Paid,
            'total' => 50000,
            'created_by' => $admin->id,
        ]);

        $payment = $order->payments()->create([
            'method' => PaymentMethod::Qris,
            'amount' => 50000,
            'reference' => 'QRIS-REF-123',
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $this->assertSame(PaymentMethod::Qris, $payment->method);
        $this->assertSame('QRIS-REF-123', $payment->reference);
        $this->assertTrue($payment->paid_at->isToday());
        $this->assertSame($order->id, $payment->order->id);
        $this->assertSame($admin->id, $payment->admin->id);
    }

    public function test_order_belongs_to_a_shift(): void
    {
        $admin = Admin::factory()->create();

        $shift = Shift::create([
            'opened_at' => now(),
            'opening_cash' => 100000,
            'admin_id' => $admin->id,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-0005',
            'total' => 35000,
            'shift_id' => $shift->id,
            'created_by' => $admin->id,
        ]);

        $this->assertSame($shift->id, $order->shift->id);
        $this->assertTrue($shift->orders()->pluck('id')->contains($order->id));
    }

    // ---------------------------------------------------------------------
    // Delete protection (Vikunja 107): the model guard must block direct
    // deletes so the audit trail (and Z-reports) stay immutable.
    // ---------------------------------------------------------------------

    private function assertDeleteBlocked(Order $order): void
    {
        try {
            $order->delete();
            $this->fail('Order deletion must be blocked by the model guard.');
        } catch (RuntimeException) {
            // Expected: orders are immutable audit records.
        }
    }

    public function test_order_with_payments_cannot_be_deleted(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'DEL-001',
            'status' => OrderStatus::Paid,
            'total' => 50000,
            'created_by' => $admin->id,
        ]));
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 50000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $this->assertDeleteBlocked($order);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id]);
    }

    public function test_order_without_payments_cannot_be_deleted(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'DEL-002',
            'total' => 10000,
            'created_by' => $admin->id,
        ]));

        $this->assertDeleteBlocked($order);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    // ---------------------------------------------------------------------
    // Dispatch-after-commit (Vikunja 112): print/confirmation jobs must not
    // run inside the DB transaction of the status change, or a sync queue
    // would ship itemless confirmations against uncommitted rows.
    //
    // RefreshDatabase installs Illuminate\Foundation\Testing\
    // DatabaseTransactionsManager, which fires afterCommit callbacks when
    // the transaction level returns to 1 (the test's wrapping transaction)
    // — so the deferral is observable inside tests: nothing is pushed while
    // the inner transaction is open, everything is pushed once it commits,
    // and nothing is pushed when it rolls back.
    // ---------------------------------------------------------------------

    public function test_print_jobs_are_dispatched_after_transaction_commits(): void
    {
        $admin = Admin::factory()->create();
        Queue::fake();

        DB::beginTransaction();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'ORD-DEFER-1',
            'status' => OrderStatus::Pending,
            'total' => 30000,
            'created_by' => $admin->id,
        ]))->refresh();
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 30000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $order->markPaidIfCovered();

        Queue::assertNothingPushed();

        DB::commit();

        Queue::assertPushed(PrintReceipt::class, fn (PrintReceipt $job) => $job->order->is($order));
        Queue::assertPushed(PrintKitchenTicket::class, fn (PrintKitchenTicket $job) => $job->order->is($order));
    }

    public function test_print_jobs_are_not_dispatched_when_the_transaction_rolls_back(): void
    {
        $admin = Admin::factory()->create();
        Queue::fake();

        DB::beginTransaction();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'ORD-DEFER-2',
            'status' => OrderStatus::Pending,
            'total' => 30000,
            'created_by' => $admin->id,
        ]))->refresh();
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 30000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $order->markPaidIfCovered();

        DB::rollBack();

        Queue::assertNothingPushed();
    }

    public function test_order_confirmation_job_is_deferred_until_transaction_commits(): void
    {
        config()->set('whatsapp.enabled', true);

        $admin = Admin::factory()->create();
        Queue::fake();

        DB::beginTransaction();
        Order::create([
            'order_number' => 'ORD-DEFER-CONF',
            'customer_phone' => '081234567890',
            'status' => OrderStatus::Pending,
            'total' => 20000,
            'created_by' => $admin->id,
        ]);

        Queue::assertNothingPushed();

        DB::commit();

        Queue::assertPushed(SendOrderConfirmation::class);
    }
}
