<?php

namespace Tests\Feature;

use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Filament\Resources\MenuItems\RelationManagers\RecipesRelationManager;
use App\Models\Admin;
use App\Models\MenuItem;
use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cogs_is_sum_of_ingredient_cost_times_quantity(): void
    {
        $item = MenuItem::create(['name' => 'Kopi Susu', 'price' => 25000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'cost' => 800, 'quantity' => 1000]);
        $milk = StockItem::create(['name' => 'Susu', 'unit' => 'ml', 'cost' => 20, 'quantity' => 5000]);

        $item->ingredients()->attach([
            $beans->id => ['quantity' => 18],
            $milk->id => ['quantity' => 250],
        ]);

        $this->assertSame(800 * 18 + 20 * 250, $item->cogs());
    }

    public function test_cogs_is_zero_without_ingredients_or_cost(): void
    {
        $item = MenuItem::create(['name' => 'Teh Panas', 'price' => 10000]);

        $this->assertSame(0, $item->cogs());
    }

    public function test_relation_manager_lists_ingredients_with_quantities(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Kopi Susu', 'price' => 25000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'cost' => 800, 'quantity' => 1000]);

        $item->ingredients()->attach($beans->id, ['quantity' => 18]);

        Livewire::actingAs($admin, 'admin')
            ->test(RecipesRelationManager::class, [
                'ownerRecord' => $item,
                'pageClass' => EditMenuItem::class,
            ])
            ->assertSee('Biji Kopi')
            ->assertSee('18');
    }

    public function test_attach_action_stores_pivot_quantity(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Kopi Susu', 'price' => 25000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'cost' => 800, 'quantity' => 1000]);

        Livewire::actingAs($admin, 'admin')
            ->test(RecipesRelationManager::class, [
                'ownerRecord' => $item,
                'pageClass' => EditMenuItem::class,
            ])
            ->callTableAction('attach', data: [
                'recordId' => $beans->id,
                'quantity' => 18,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('menu_item_stock_item', [
            'menu_item_id' => $item->id,
            'stock_item_id' => $beans->id,
            'quantity' => 18,
        ]);
    }

    public function test_attach_action_stores_raw_quantity_from_formatted_input(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Kopi Susu', 'price' => 25000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'cost' => 800, 'quantity' => 1000]);

        Livewire::actingAs($admin, 'admin')
            ->test(RecipesRelationManager::class, [
                'ownerRecord' => $item,
                'pageClass' => EditMenuItem::class,
            ])
            ->callTableAction('attach', data: [
                'recordId' => $beans->id,
                'quantity' => '1.500',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('menu_item_stock_item', [
            'menu_item_id' => $item->id,
            'stock_item_id' => $beans->id,
            'quantity' => 1500,
        ]);
    }

    public function test_attach_action_requires_quantity(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Kopi Susu', 'price' => 25000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'cost' => 800, 'quantity' => 1000]);

        Livewire::actingAs($admin, 'admin')
            ->test(RecipesRelationManager::class, [
                'ownerRecord' => $item,
                'pageClass' => EditMenuItem::class,
            ])
            ->callTableAction('attach', data: [
                'recordId' => $beans->id,
                'quantity' => null,
            ])
            ->assertHasTableActionErrors(['quantity']);
    }

    public function test_detach_action_removes_ingredient(): void
    {
        $admin = Admin::factory()->create();
        $item = MenuItem::create(['name' => 'Kopi Susu', 'price' => 25000]);
        $beans = StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'cost' => 800, 'quantity' => 1000]);

        $item->ingredients()->attach($beans->id, ['quantity' => 18]);

        Livewire::actingAs($admin, 'admin')
            ->test(RecipesRelationManager::class, [
                'ownerRecord' => $item,
                'pageClass' => EditMenuItem::class,
            ])
            ->callTableAction('detach', $beans->id)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('menu_item_stock_item', [
            'menu_item_id' => $item->id,
            'stock_item_id' => $beans->id,
        ]);
    }
}
