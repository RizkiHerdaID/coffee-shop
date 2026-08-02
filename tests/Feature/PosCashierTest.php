<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\Cashier;
use App\Jobs\PrintKitchenTicket;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * POS cashier order-creation flow (milestone M1).
 *
 * Tests the Filament custom page App\Filament\Pages\Cashier (route
 * filament.admin.pages.cashier):
 *
 * - public array $cart = []            flat map: menu item id => quantity
 * - public string $category = ''       category filter chip
 * - public ?string $customerPhone = null
 * - computed: menuItems (available only, category-filtered, orderBy sort_order),
 *     categories, cartLines (item/qty/subtotal), cartTotal
 * - addToCart(int $menuItemId): void   ignored for unavailable items
 * - incrementItem / decrementItem (removes the line at qty 0) / removeItem / clearCart
 * - createOrder(): void                throws a ValidationException with a 'cart'
 *     error when the cart is empty; otherwise persists an Order (status pending,
 *     generated order_number ORD-YYYYMMDD-XXXX, total = sum of line subtotals,
 *     created_by = current admin, customer_phone when set) with order_items
 *     snapshotting name/price and qty/subtotal, then clears the cart.
 */
class PosCashierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    public function test_cashier_page_requires_authentication(): void
    {
        $this->get(route('filament.admin.pages.cashier'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_cashier_page_shows_only_available_menu_items(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::create([
            'name' => 'Kopi Rusak',
            'price' => 10000,
            'available' => false,
        ]);

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('filament.admin.pages.cashier'))
            ->assertOk()
            ->assertSee('Espresso')
            ->assertSee('Cold Brew')
            ->assertSee('Banana Bread')
            ->assertDontSee('Kopi Rusak');
    }

    public function test_adding_items_to_cart_increments_quantities_and_total(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->assertCount('cart', 2)
            ->assertSet('cart.'.$espresso->id, 2)
            ->assertSet('cart.'.$croissant->id, 1)
            ->assertCount('cartLines', 2)
            ->assertSet('cartTotal', 65000);
    }

    public function test_increment_and_decrement_adjust_line_quantities(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('incrementItem', $item->id)
            ->assertSet('cart.'.$item->id, 2)
            ->assertSet('cartTotal', 40000)
            ->call('decrementItem', $item->id)
            ->assertSet('cart.'.$item->id, 1)
            ->assertSet('cartTotal', 20000);
    }

    public function test_decrementing_below_one_removes_the_line_from_cart(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('decrementItem', $item->id)
            ->assertCount('cart', 0)
            ->assertSet('cartTotal', 0);
    }

    public function test_remove_from_cart_drops_the_line(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('removeItem', $espresso->id)
            ->assertCount('cart', 1)
            ->assertSet('cart.'.$croissant->id, 1)
            ->assertSet('cartTotal', 25000);
    }

    public function test_creating_order_persists_pending_order_with_items_total_and_created_by(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('customerPhone', '081234567890')
            ->call('createOrder')
            ->assertHasNoErrors()
            ->assertCount('cart', 0);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'customer_phone' => '081234567890',
            'status' => 'pending',
            'total' => 65000,
            'created_by' => $admin->id,
        ]);

        $order = Order::firstOrFail();
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->order_number);
        $this->assertSame(OrderStatus::Pending, $order->status);

        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $espresso->id,
            'name' => 'Espresso',
            'price' => 20000,
            'qty' => 2,
            'subtotal' => 40000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $croissant->id,
            'name' => 'Croissant',
            'price' => 25000,
            'qty' => 1,
            'subtotal' => 25000,
        ]);
    }

    public function test_creating_order_without_customer_phone_stores_null(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'customer_phone' => null,
            'total' => 20000,
            'created_by' => $admin->id,
        ]);
    }

    public function test_creating_orders_generates_unique_order_numbers(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->call('addToCart', $item->id)
            ->call('createOrder');

        $this->assertDatabaseCount('orders', 2);

        $numbers = Order::pluck('order_number');
        $this->assertSame(2, $numbers->count());
        $this->assertSame(2, $numbers->unique()->count());
        $numbers->each(fn (string $number) => $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $number));
    }

    public function test_unavailable_menu_item_cannot_be_added_to_cart(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create([
            'name' => 'Kopi Rusak',
            'price' => 10000,
            'available' => false,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->assertCount('cart', 0)
            ->assertSet('cartTotal', 0);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_creating_order_with_empty_cart_is_rejected(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('createOrder')
            ->assertHasErrors(['cart']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    /**
     * Order/line notes (modifiers).
     *
     * Cashier exposes `notes` (?string, order-level) and `cartNotes`
     * (menu item id => note string, per line); nullable `notes` columns on
     * `orders` and `order_items`; PrintKitchenTicket::renderLines() prints
     * each line note on its own indented line under the item, then the
     * order-level note under all items.
     */
    public function test_creating_order_persists_order_level_note(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->set('notes', 'Gula dikit, makasih')
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'total' => 20000,
            'notes' => 'Gula dikit, makasih',
        ]);
    }

    public function test_creating_order_without_notes_stores_null(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder');

        $this->assertDatabaseHas('orders', ['notes' => null]);
        $this->assertDatabaseHas('order_items', ['notes' => null]);
    }

    public function test_creating_order_persists_per_line_notes(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('cartNotes.'.$espresso->id, 'Less ice, double shot')
            ->assertSet('cartNotes.'.$espresso->id, 'Less ice, double shot')
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $espresso->id,
            'notes' => 'Less ice, double shot',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $croissant->id,
            'notes' => null,
        ]);
    }

    public function test_notes_do_not_leak_into_the_next_order(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $item->id)
            ->set('notes', 'Pisah cup')
            ->set('cartNotes.'.$item->id, 'Separate cup')
            ->call('createOrder')
            ->assertHasNoErrors();

        $component
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('orders', 2);

        $orders = Order::orderBy('id')->get();
        $this->assertSame('Pisah cup', $orders[0]->notes);
        $this->assertNull($orders[1]->notes);

        $this->assertSame('Separate cup', $orders[0]->items()->first()->notes);
        $this->assertNull($orders[1]->items()->first()->notes);
    }

    public function test_kitchen_ticket_renders_line_notes_under_each_item(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('cartNotes.'.$espresso->id, 'Less ice')
            ->set('cartNotes.'.$croissant->id, 'No butter')
            ->set('notes', 'Gula dikit, makasih')
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();
        $lines = (new PrintKitchenTicket($order))->renderLines();
        $output = implode("\n", $lines);

        $this->assertContains('  - Less ice'."\n", $lines);
        $this->assertContains('  - No butter'."\n", $lines);
        $this->assertStringContainsString('Less ice', $output);
        $this->assertStringContainsString('No butter', $output);
        $this->assertStringContainsString('Gula dikit, makasih', $output);
        $this->assertGreaterThan(strpos($output, 'Espresso'), strpos($output, 'Less ice'));
        $this->assertGreaterThan(strpos($output, 'Croissant'), strpos($output, 'No butter'));
        $this->assertGreaterThan(strpos($output, 'No butter'), strpos($output, 'Gula dikit, makasih'));
    }

    public function test_kitchen_ticket_without_notes_omits_note_lines(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();
        $lines = (new PrintKitchenTicket($order))->renderLines();

        $this->assertStringContainsString('Espresso'."\n", implode("\n", $lines));
        $this->assertDoesNotMatchRegularExpression('/^\s+- /m', implode("\n", $lines));
    }

    /**
     * Quick reorder.
     *
     * `Cashier::repeatOrder()` loads the most recent order's lines into the
     * cart as a menu item id => qty map (skip unavailable items; no-op with a
     * clean cart when there is no prior order).
     */
    public function test_repeat_order_loads_the_most_recent_orders_items_into_the_cart(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);
        $latte = MenuItem::create(['name' => 'Latte', 'price' => 30000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $component
            ->call('addToCart', $croissant->id)
            ->call('addToCart', $latte->id)
            ->call('addToCart', $latte->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $component
            ->call('repeatOrder')
            ->assertCount('cart', 2)
            ->assertSet('cart.'.$croissant->id, 1)
            ->assertSet('cart.'.$latte->id, 2)
            ->assertCount('cartLines', 2)
            ->assertSet('cartTotal', 85000);

        $this->assertDatabaseCount('orders', 2);
    }

    public function test_repeat_order_skips_unavailable_menu_items(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $croissant->update(['available' => false]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('repeatOrder')
            ->assertCount('cart', 1)
            ->assertSet('cart.'.$espresso->id, 1)
            ->assertSet('cartTotal', 20000);
    }

    public function test_repeat_order_skips_deleted_menu_items(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $croissant->delete();

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('repeatOrder')
            ->assertCount('cart', 1)
            ->assertSet('cart.'.$espresso->id, 1)
            ->assertSet('cartTotal', 20000);
    }

    public function test_repeat_order_with_no_prior_order_leaves_cart_empty(): void
    {
        $admin = Admin::factory()->create();
        MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('repeatOrder')
            ->assertCount('cart', 0)
            ->assertSet('cartTotal', 0)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Split payments (wave 5).
     *
     * QRIS/e-wallet may take a PARTIAL amount: capturePayment() applies the
     * entered paymentAmount instead of always settling the remaining balance;
     * a blank amount still settles the full remainder (previous behaviour).
     * Cash was already partial-capable (it applies the tendered amount). The
     * order stays pending until the sum of all payment rows covers the total,
     * then markPaidIfCovered() transitions it to paid exactly once.
     */
    public function test_qris_partial_payment_leaves_the_remainder_payable(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $component
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', '20.000')
            ->set('paymentReference', 'QRIS-PARTIAL-1')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 0)
            ->assertSet('paymentAmount', '45.000')
            ->assertSet('paymentReference', '');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(20000, $order->fresh()->paid_total);
        $this->assertSame(45000, $order->fresh()->remaining);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 20000,
            'reference' => 'QRIS-PARTIAL-1',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_ewallet_partial_payment_then_cash_settles_the_rest_with_multiple_payment_rows(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $component
            ->set('paymentMethod', 'ewallet')
            ->set('paymentAmount', '20.000')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(45000, $order->fresh()->remaining);

        $component
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '45.000')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 0);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(65000, $order->fresh()->paid_total);
        $this->assertSame(0, $order->fresh()->remaining);

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'ewallet',
            'amount' => 20000,
            'reference' => null,
            'admin_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 45000,
            'admin_id' => $admin->id,
        ]);
        $this->assertSame(65000, (int) $order->payments()->sum('amount'));
    }

    public function test_qris_partial_payment_then_ewallet_settles_the_rest(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $component
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', '25.000')
            ->set('paymentReference', 'QRIS-PART-1')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(40000, $order->fresh()->remaining);

        $component
            ->set('paymentMethod', 'ewallet')
            ->set('paymentAmount', '40.000')
            ->set('paymentReference', 'EWALLET-REST-1')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(65000, (int) $order->payments()->sum('amount'));
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 25000,
            'reference' => 'QRIS-PART-1',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'ewallet',
            'amount' => 40000,
            'reference' => 'EWALLET-REST-1',
        ]);
    }

    public function test_qris_without_entered_amount_still_settles_the_full_remaining(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $component
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', '')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 0)
            ->assertSet('paymentAmount', '');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(0, $order->fresh()->remaining);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 65000,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_cash_partial_payment_leaves_the_remainder_payable(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $component
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '20.000')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 0)
            ->assertSet('paymentAmount', '45.000');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(20000, $order->fresh()->paid_total);
        $this->assertSame(45000, $order->fresh()->remaining);

        $component
            ->set('paymentAmount', '45.000')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(65000, (int) $order->payments()->sum('amount'));
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 20000,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 45000,
        ]);
    }

    public function test_pay_rest_fills_the_amount_with_the_remaining_balance(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $component
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', '20.000')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(45000, $order->fresh()->remaining);

        $component
            ->call('payRest')
            ->assertSet('paymentAmount', '45.000')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(0, $order->fresh()->remaining);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 20000,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 45000,
        ]);
    }

    public function test_non_cash_payment_above_the_remaining_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $component
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', '70.000')
            ->call('capturePayment')
            ->assertHasErrors(['paymentAmount']);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);

        $component
            ->set('paymentMethod', 'ewallet')
            ->set('paymentAmount', '70.000')
            ->call('capturePayment')
            ->assertHasErrors(['paymentAmount']);

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * Discounts (wave 6).
     *
     * Contract pinned with the lead: `orders.total` stays GROSS (sum of line
     * subtotals); the discount lives in `discount_type`
     * ('fixed'|'percent'|NULL) + `discount_amount` (raw: IDR for fixed,
     * percent integer for percent; both NULL when none). Accessors:
     * `discountValue` (effective IDR, 0 when none; percent =
     * round(total * amount / 100)) and `netTotal` (total - discountValue);
     * `remaining` and `markPaidIfCovered()` use netTotal, so the NET amount
     * covers the order. Cashier exposes `discountType` (''|'fixed'|'percent')
     * + `discountAmount` (masked display string); invalid discounts
     * (negative, exceeding the gross total, percent outside 1..100, bad
     * type) are rejected by createOrder with errors on discountAmount /
     * discountType. The printable receipt shows a discount line between the
     * items and the TOTAL (net).
     */
    public function test_fixed_discount_lowers_the_net_payable_and_payment_cover(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('discountType', 'fixed')
            ->set('discountAmount', '10.000')
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'discount_type' => 'fixed',
            'discount_amount' => 10000,
            'total' => 65000,
        ]);

        $order = Order::firstOrFail();
        $this->assertSame(10000, $order->discountValue);
        $this->assertSame(55000, $order->netTotal);
        $this->assertSame(55000, $order->remaining);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'qris')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qris',
            'amount' => 55000,
        ]);
    }

    public function test_cash_change_is_computed_from_the_net_total_after_a_fixed_discount(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('discountType', 'fixed')
            ->set('discountAmount', '10.000')
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'cash')
            ->set('paymentAmount', '60.000')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('changeDue', 5000);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 60000,
        ]);
    }

    public function test_percent_discount_is_computed_as_a_percentage_of_the_gross_total(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->set('discountType', 'percent')
            ->set('discountAmount', '10')
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'discount_type' => 'percent',
            'discount_amount' => 10,
            'total' => 20000,
        ]);

        $order = Order::firstOrFail();
        $this->assertSame(2000, $order->discountValue);
        $this->assertSame(18000, $order->netTotal);
        $this->assertSame(18000, $order->remaining);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'qris')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 18000,
        ]);
    }

    public function test_percent_discount_rounds_half_up_on_odd_gross_totals(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('discountType', 'percent')
            ->set('discountAmount', '15')
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(9750, $order->discountValue);
        $this->assertSame(55250, $order->netTotal);
        $this->assertSame(55250, $order->remaining);
    }

    public function test_negative_fixed_discount_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->set('discountType', 'fixed')
            ->set('discountAmount', '-5.000')
            ->call('createOrder')
            ->assertHasErrors(['discountAmount']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_fixed_discount_exceeding_the_gross_total_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('discountType', 'fixed')
            ->set('discountAmount', '70.000')
            ->call('createOrder')
            ->assertHasErrors(['discountAmount']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_percent_discount_outside_one_to_100_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        foreach (['0', '101', 'abc'] as $value) {
            Livewire::actingAs($admin, 'admin')
                ->test(Cashier::class)
                ->call('addToCart', $item->id)
                ->set('discountType', 'percent')
                ->set('discountAmount', $value)
                ->call('createOrder')
                ->assertHasErrors(['discountAmount']);
        }

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_discount_type_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->set('discountType', 'bogus')
            ->set('discountAmount', '10.000')
            ->call('createOrder')
            ->assertHasErrors(['discountType']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_blank_discount_type_stores_null_discount_and_full_gross_total(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->set('discountType', '')
            ->set('discountAmount', '10.000')
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'discount_type' => null,
            'discount_amount' => null,
            'total' => 20000,
        ]);

        $order = Order::firstOrFail();
        $this->assertSame(0, $order->discountValue);
        $this->assertSame(20000, $order->netTotal);
        $this->assertSame(20000, $order->remaining);
    }

    public function test_partial_payment_remaining_is_computed_from_the_net_total(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        $component = Livewire::actingAs($admin, 'admin')->test(Cashier::class);

        $component
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('discountType', 'fixed')
            ->set('discountAmount', '10.000')
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();

        $component
            ->set('paymentMethod', 'qris')
            ->set('paymentAmount', '20.000')
            ->call('capturePayment')
            ->assertHasNoErrors()
            ->assertSet('paymentAmount', '35.000');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(35000, $order->fresh()->remaining);

        $component
            ->set('paymentMethod', 'qris')
            ->call('payRest')
            ->assertSet('paymentAmount', '35.000')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(55000, $order->fresh()->paid_total);
        $this->assertSame(0, $order->fresh()->remaining);
    }

    public function test_receipt_page_shows_the_discount_line_between_items_and_the_net_total(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $croissant = MenuItem::create(['name' => 'Croissant', 'price' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $croissant->id)
            ->set('discountType', 'fixed')
            ->set('discountAmount', '10.000')
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('selectOrder', $order->id)
            ->set('paymentMethod', 'qris')
            ->call('capturePayment')
            ->assertHasNoErrors();

        $this->actingAs($admin, 'admin')
            ->get(route('pos.receipt', $order))
            ->assertOk()
            ->assertSee(__('dashboard.discount'))
            ->assertSee('-Rp 10.000')
            ->assertSee('Rp 55.000')
            ->assertDontSee('Rp 65.000');
    }
}
