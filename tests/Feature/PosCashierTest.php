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
}
