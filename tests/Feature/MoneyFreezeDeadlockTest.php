<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\Cashier;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Database\DeadlockException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Vikunja 151: the capture and createOrder transactions run with
 * attempts: 5, so a transient deadlock from row-lock contention is retried
 * instead of failing the whole payment/order.
 *
 * NOTE: this class deliberately does NOT use RefreshDatabase. Its per-test
 * wrapping transaction would make Laravel's deadlock handler rethrow (nested
 * transactions are never retried); a top-level transaction is required to
 * exercise the retry loop. Each test starts from a freshly migrated database.
 */
class MoneyFreezeDeadlockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--no-interaction' => true]);
    }

    public function test_capture_payment_retries_after_a_deadlock(): void
    {
        Queue::fake();

        $admin = Admin::factory()->create();
        $order = Order::create([
            'order_number' => 'DLK-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Pending,
            'total' => 50000,
            'created_by' => $admin->id,
        ]);

        $thrown = false;
        DB::listen(function ($query) use (&$thrown): void {
            if (! $thrown && str_contains($query->sql, 'insert into "payments"')) {
                $thrown = true;
                throw new DeadlockException('database is locked', 40001);
            }
        });

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'qris')
            ->set('paymentReference', 'DLK-REF')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $order->refresh();

        $this->assertTrue($thrown);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame(50000, $order->paid_total);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 50000,
            'reference' => 'DLK-REF',
        ]);
    }

    public function test_create_order_retries_after_a_deadlock(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        $thrown = false;
        DB::listen(function ($query) use (&$thrown): void {
            if (! $thrown && str_contains($query->sql, 'insert into "order_items"')) {
                $thrown = true;
                throw new DeadlockException('database is locked', 40001);
            }
        });

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertTrue($thrown);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);

        $order = Order::firstOrFail();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(20000, $order->total);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->order_number);
    }
}
