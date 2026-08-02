<?php

namespace Tests\Feature;

use App\Filament\Pages\Cashier;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockItem;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Recipe-driven stock deduction on POS orders (recipe-stock-consume).
 *
 * When an order is created through the cashier page, each line's recipe
 * ingredients (MenuItem::ingredients pivot, quantity per unit x line qty)
 * are consumed as 'out' StockMovements linked to the order item via
 * order_item_id. Insufficient stock never blocks the sale (lenient mode):
 * the affected ingredient is skipped and a warning is logged/notified.
 * config('pos.deduct_stock') toggles the whole feature off.
 */
class RecipeStockConsumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pos.deduct_stock' => true]);
    }

    public function test_creating_order_deducts_each_ingredient_as_out_movement_linked_to_order_item(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 1000]);
        $sugar = StockItem::create(['name' => 'Gula', 'unit' => 'gram', 'quantity' => 500]);
        $espresso->ingredients()->attach([
            $beans->id => ['quantity' => 18],
            $sugar->id => ['quantity' => 10],
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertSame(982, $beans->fresh()->quantity);
        $this->assertSame(490, $sugar->fresh()->quantity);

        $order = Order::firstOrFail();
        $orderItem = $order->items()->firstOrFail();

        $this->assertSame(1, $order->items()->count());
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $beans->id,
            'order_item_id' => $orderItem->id,
            'type' => 'out',
            'quantity' => 18,
            'note' => $order->order_number.' Espresso',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $sugar->id,
            'order_item_id' => $orderItem->id,
            'type' => 'out',
            'quantity' => 10,
            'note' => $order->order_number.' Espresso',
        ]);
    }

    public function test_item_without_recipe_produces_no_movements(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Teh Panas', 'price' => 10000]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $item->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_insufficient_stock_does_not_block_sale_or_drive_negative_and_warns(): void
    {
        Log::spy();

        $admin = Admin::factory()->create();
        $latte = MenuItem::create(['name' => 'Latte', 'price' => 25000]);
        $milk = StockItem::create(['name' => 'Susu', 'unit' => 'ml', 'quantity' => 100]);
        $latte->ingredients()->attach($milk->id, ['quantity' => 250]);

        $notification = Notification::make()
            ->title(__('pos.stock.warning_title'))
            ->body(__('pos.stock.skipped', ['ingredients' => 'Susu']))
            ->warning()
            ->send();

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $latte->id)
            ->call('createOrder')
            ->assertHasNoErrors()
            ->assertCount('cart', 0)
            ->assertNotified($notification);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(100, $milk->fresh()->quantity);
        $this->assertSame(0, $milk->fresh()->movements()->count());

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(Mockery::on(
                fn (string $message): bool => str_contains($message, 'Susu')
                    && str_contains($message, 'Stock deduction skipped')
            ));
    }

    public function test_partial_insufficiency_deducts_available_ingredients_only(): void
    {
        Log::spy();

        $admin = Admin::factory()->create();
        $latte = MenuItem::create(['name' => 'Latte', 'price' => 25000]);
        $milk = StockItem::create(['name' => 'Susu', 'unit' => 'ml', 'quantity' => 1000]);
        $syrup = StockItem::create(['name' => 'Sirup Vanila', 'unit' => 'ml', 'quantity' => 10]);
        $latte->ingredients()->attach([
            $milk->id => ['quantity' => 250],
            $syrup->id => ['quantity' => 20],
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $latte->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertSame(750, $milk->fresh()->quantity);
        $this->assertSame(10, $syrup->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $milk->id,
            'type' => 'out',
            'quantity' => 250,
        ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(Mockery::on(fn (string $message): bool => str_contains($message, 'Sirup Vanila')));
    }

    public function test_deduct_stock_toggle_off_skips_all_deduction(): void
    {
        config(['pos.deduct_stock' => false]);
        Log::spy();

        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 1000]);
        $espresso->ingredients()->attach($beans->id, ['quantity' => 18]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(1000, $beans->fresh()->quantity);
        Log::shouldNotHaveReceived('warning');
    }

    public function test_line_quantity_multiplies_recipe_ingredient_quantity(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 1000]);
        $espresso->ingredients()->attach($beans->id, ['quantity' => 3]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('incrementItem', $espresso->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $orderItem = Order::firstOrFail()->items()->firstOrFail();
        $this->assertSame(2, $orderItem->qty);
        $this->assertSame(994, $beans->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $beans->id,
            'order_item_id' => $orderItem->id,
            'type' => 'out',
            'quantity' => 6,
        ]);
    }

    public function test_multi_item_order_links_movements_to_their_own_order_items(): void
    {
        $admin = Admin::factory()->create();
        $espresso = MenuItem::create(['name' => 'Espresso', 'price' => 20000]);
        $latte = MenuItem::create(['name' => 'Latte', 'price' => 25000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 2000]);
        $milk = StockItem::create(['name' => 'Susu', 'unit' => 'ml', 'quantity' => 3000]);
        $espresso->ingredients()->attach($beans->id, ['quantity' => 18]);
        $latte->ingredients()->attach([
            $beans->id => ['quantity' => 18],
            $milk->id => ['quantity' => 250],
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(Cashier::class)
            ->call('addToCart', $espresso->id)
            ->call('addToCart', $latte->id)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();
        $espressoLine = $order->items()->where('menu_item_id', $espresso->id)->firstOrFail();
        $latteLine = $order->items()->where('menu_item_id', $latte->id)->firstOrFail();

        $this->assertSame(1964, $beans->fresh()->quantity);
        $this->assertSame(2750, $milk->fresh()->quantity);

        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $beans->id,
            'order_item_id' => $espressoLine->id,
            'type' => 'out',
            'quantity' => 18,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $beans->id,
            'order_item_id' => $latteLine->id,
            'type' => 'out',
            'quantity' => 18,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $milk->id,
            'order_item_id' => $latteLine->id,
            'type' => 'out',
            'quantity' => 250,
        ]);
    }
}
