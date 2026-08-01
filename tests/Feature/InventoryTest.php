<?php

namespace Tests\Feature;

use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Resources\StockItems\Pages\EditStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\Admin;
use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_increases_quantity_and_records_a_movement(): void
    {
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 1000,
            'min_threshold' => 500,
        ]);

        $result = $item->stockIn(500, 'Pembelian dari supplier');

        $this->assertTrue($result);
        $this->assertSame(1500, $item->fresh()->quantity);
        $this->assertSame(1, $item->movements()->count());
        $this->assertSame('in', $item->movements()->first()->type);
        $this->assertSame(500, $item->movements()->first()->quantity);
        $this->assertSame('Pembelian dari supplier', $item->movements()->first()->note);
    }

    public function test_stock_out_decreases_quantity_and_records_a_movement(): void
    {
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 1000,
            'min_threshold' => 500,
        ]);

        $result = $item->stockOut(300, 'Dipakai shift pagi');

        $this->assertTrue($result);
        $this->assertSame(700, $item->fresh()->quantity);
        $this->assertSame(1, $item->movements()->count());
        $this->assertSame('out', $item->movements()->first()->type);
        $this->assertSame(300, $item->movements()->first()->quantity);
    }

    public function test_stock_out_above_current_quantity_fails_without_changes(): void
    {
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 5,
            'min_threshold' => 0,
        ]);

        $result = $item->stockOut(10);

        $this->assertFalse($result);
        $this->assertSame(5, $item->fresh()->quantity);
        $this->assertSame(0, $item->movements()->count());
    }

    public function test_low_stock_scope_and_helper(): void
    {
        StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 1000,
            'min_threshold' => 500,
        ]);

        $low = StockItem::create([
            'name' => 'Susu',
            'unit' => 'liter',
            'quantity' => 2,
            'min_threshold' => 2,
        ]);

        $empty = StockItem::create([
            'name' => 'Gelas',
            'unit' => 'pcs',
            'quantity' => 0,
            'min_threshold' => 50,
        ]);

        $this->assertSame(['Gelas', 'Susu'], StockItem::query()->lowStock()->orderBy('name')->pluck('name')->all());
        $this->assertTrue($low->isLowStock());
        $this->assertTrue($empty->isLowStock());
        $this->assertFalse(StockItem::where('name', 'Biji Kopi')->first()->isLowStock());
    }

    public function test_stock_item_resource_pages_render_for_authenticated_admin(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 1000,
            'min_threshold' => 500,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(StockItemResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Biji Kopi');

        $this->actingAs($admin, 'admin')
            ->get(StockItemResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(StockItemResource::getUrl('edit', ['record' => $item]))
            ->assertOk()
            ->assertSee('Biji Kopi');
    }

    public function test_create_form_stores_raw_integers_from_formatted_input(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateStockItem::class)
            ->fillForm([
                'name' => 'Sirup Vanila',
                'unit' => 'ml',
                'quantity' => '25.000',
                'min_threshold' => '1.500',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stock_items', [
            'name' => 'Sirup Vanila',
            'unit' => 'ml',
            'quantity' => 25000,
            'min_threshold' => 1500,
        ]);
    }

    public function test_stock_in_action_stores_raw_integer_from_formatted_input(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 1000,
            'min_threshold' => 500,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ListStockItems::class)
            ->callTableAction('stockIn', $item, data: [
                'quantity' => '5.000',
                'note' => 'Tambah stok',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(6000, $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 5000,
            'note' => 'Tambah stok',
        ]);
    }

    public function test_stock_out_action_stores_raw_integer_from_formatted_input(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 6000,
            'min_threshold' => 500,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ListStockItems::class)
            ->callTableAction('stockOut', $item, data: [
                'quantity' => '1.000',
                'note' => 'Dipakai shift pagi',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(5000, $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'type' => 'out',
            'quantity' => 1000,
            'note' => 'Dipakai shift pagi',
        ]);
    }

    public function test_edit_form_prefills_database_integer_as_formatted_state(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 5000,
            'min_threshold' => 500,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(EditStockItem::class, ['record' => $item->getRouteKey()])
            ->assertFormSet([
                'quantity' => '5.000',
                'min_threshold' => '500',
            ]);
    }
}
