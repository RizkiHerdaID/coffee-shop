<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\Cashier;
use App\Jobs\PrintKitchenTicket;
use App\Jobs\PrintReceipt;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Shift;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Card 115 (POS part) — concurrency guards.
 *
 * - Two open shifts are impossible at the DB level (partial unique index
 *   on open shifts; the page-level guard is a second, friendlier layer).
 * - markPaidIfCovered() must re-check the status in the DB (conditional
 *   update / row lock), so a double capture can only transition once.
 * - Order numbers must never duplicate even under rapid creation and must
 *   resume after existing orders for the day.
 */
class PosConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_a_second_open_shift(): void
    {
        $admin = Admin::factory()->create();
        Shift::create(['opened_at' => now(), 'opening_cash' => 100000, 'admin_id' => $admin->id]);

        $this->expectException(QueryException::class);

        Shift::create(['opened_at' => now(), 'opening_cash' => 200000, 'admin_id' => $admin->id]);
    }

    public function test_a_new_shift_can_open_after_the_previous_one_closes(): void
    {
        $admin = Admin::factory()->create();
        $first = Shift::create(['opened_at' => now()->subHours(2), 'opening_cash' => 100000, 'admin_id' => $admin->id]);
        $first->update(['closed_at' => now(), 'closing_cash' => 100000]);

        $second = Shift::create(['opened_at' => now(), 'opening_cash' => 200000, 'admin_id' => $admin->id]);

        $this->assertDatabaseCount('shifts', 2);
        $this->assertSame($second->id, Shift::active()?->id);
    }

    /**
     * Card 115 double-capture race: request B read the order earlier (full
     * 65.000 remaining) and fires its capture while request A's partial
     * 40.000 already landed. Under the capture-time re-read (row lock) B
     * applies only the 25.000 still owed — a concurrent capture can never
     * push paid_total above net_total.
     */
    public function test_two_rapid_captures_cannot_overpay_an_order(): void
    {
        Http::fake();
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'RACE-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Pending,
            'total' => 65000,
            'created_by' => $admin->id,
        ]));

        // Request A: partial cash capture (40.000), order stays pending.
        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '40.000')
            ->call('capturePayment')
            ->assertHasNoErrors();

        // Request B: stale full-amount tender (65.000) arriving late. The
        // capture re-reads the remaining under the lock: only 25.000 is
        // applied, the 40.000 surplus is recorded as change.
        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '65.000')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 40000);

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(65000, $order->paid_total);
        $this->assertSame(65000, $order->net_total);
        $this->assertSame(65000, (int) $order->payments()->sum('amount'));
        $this->assertSame(40000, (int) $order->payments()->sum('change'));
    }

    public function test_mark_paid_if_covered_transitions_exactly_once_and_dispatches_print_jobs_once(): void
    {
        Queue::fake();

        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'RACE-'.Str::upper(Str::random(8)),
            'status' => OrderStatus::Pending,
            'total' => 65000,
            'created_by' => $admin->id,
        ]));
        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => 65000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        // Two rapid "captures" both call markPaidIfCovered: the first
        // transitions, the second must return false.
        $this->assertTrue($order->markPaidIfCovered());
        $this->assertFalse($order->markPaidIfCovered());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        Queue::assertPushed(PrintReceipt::class, 1);
        Queue::assertPushed(PrintKitchenTicket::class, 1);
    }

    public function test_rapid_order_creation_never_duplicates_order_numbers(): void
    {
        Http::fake();
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        foreach (range(1, 10) as $i) {
            Livewire::actingAs($admin, 'admin')
                ->test(Cashier::class)
                ->call('addToCart', $item->id)
                ->call('createOrder')
                ->assertHasNoErrors();
        }

        $numbers = Order::pluck('order_number');

        $this->assertSame(10, $numbers->count());
        $this->assertSame(10, $numbers->unique()->count());

        $numbers->each(fn (string $number) => $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $number));
    }

    public function test_order_numbers_resume_after_existing_orders_for_the_day(): void
    {
        Http::fake();
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        // An order placed "by another terminal" moments earlier.
        Order::withoutEvents(fn () => Order::create([
            'order_number' => 'ORD-'.now()->format('Ymd').'-0042',
            'status' => OrderStatus::Pending,
            'total' => 20000,
            'created_by' => $admin->id,
        ]));

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $next = Order::where('order_number', 'like', 'ORD-'.now()->format('Ymd').'-%')
            ->where('order_number', '!=', 'ORD-'.now()->format('Ymd').'-0042')
            ->value('order_number');

        $this->assertSame('ORD-'.now()->format('Ymd').'-0043', $next);
    }
}
