<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
