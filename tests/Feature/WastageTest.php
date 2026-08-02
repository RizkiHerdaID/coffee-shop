<?php

namespace Tests\Feature;

use App\Enums\WasteReason;
use App\Filament\Resources\StockItems\RelationManagers\WastagesRelationManager;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Resources\Wastages\Pages\CreateWastage;
use App\Filament\Resources\Wastages\Pages\EditWastage;
use App\Models\Admin;
use App\Models\StockItem;
use App\Models\Wastage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WastageTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_stores_quantity_as_raw_integer_from_indonesian_formatted_input(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Susu UHT',
            'unit' => 'liter',
            'quantity' => 50,
            'cost' => 20000,
            'min_threshold' => 10,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateWastage::class)
            ->fillForm([
                'stock_item_id' => $item->id,
                'quantity' => '25.000',
                'reason' => WasteReason::Spilled,
                'recorded_at' => '2026-08-02 08:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('wastages', [
            'stock_item_id' => $item->id,
            'quantity' => 25000,
            'reason' => 'spilled',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_create_form_persists_reason_as_enum_and_admin_is_set(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Kopi Arabika',
            'unit' => 'gram',
            'quantity' => 1000,
            'cost' => 50000,
            'min_threshold' => 100,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateWastage::class)
            ->fillForm([
                'stock_item_id' => $item->id,
                'quantity' => '500',
                'reason' => WasteReason::Expired,
                'note' => 'Melewati tanggal kadaluarsa',
                'recorded_at' => '2026-08-02 09:30:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $wastage = Wastage::query()->first();

        $this->assertSame(WasteReason::Expired, $wastage->reason);
        $this->assertSame($admin->id, $wastage->admin_id);
        $this->assertSame(500, $wastage->quantity);
        $this->assertSame('Melewati tanggal kadaluarsa', $wastage->note);
    }

    public function test_edit_form_displays_quantity_with_indonesian_separators(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Sirup Gula',
            'unit' => 'pcs',
            'quantity' => 100,
            'cost' => 15000,
            'min_threshold' => 20,
        ]);
        $wastage = Wastage::create([
            'stock_item_id' => $item->id,
            'quantity' => 15000,
            'reason' => WasteReason::Damaged,
            'admin_id' => $admin->id,
            'recorded_at' => '2026-08-02 10:00:00',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->assertFormSet(['quantity' => '15.000']);
    }

    public function test_create_form_requires_stock_item_quantity_reason_and_recorded_at(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateWastage::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['stock_item_id', 'quantity', 'reason', 'recorded_at']);
    }

    public function test_waste_reason_enum_get_label_returns_localized_indonesian(): void
    {
        $this->assertSame('Tumpah', WasteReason::Spilled->getLabel());
        $this->assertSame('Kadaluarsa', WasteReason::Expired->getLabel());
        $this->assertSame('Rusak', WasteReason::Damaged->getLabel());
        $this->assertSame('Lainnya', WasteReason::Other->getLabel());
    }

    public function test_stock_item_resource_registers_wastages_relation_manager(): void
    {
        $this->assertContains(WastagesRelationManager::class, StockItemResource::getRelations());
    }

    public function test_wastage_belongs_to_stock_item_and_admin(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Espresso Beans',
            'unit' => 'gram',
            'quantity' => 500,
            'cost' => 80000,
            'min_threshold' => 50,
        ]);
        $wastage = Wastage::create([
            'stock_item_id' => $item->id,
            'quantity' => 100,
            'reason' => WasteReason::Other,
            'admin_id' => $admin->id,
            'recorded_at' => now(),
        ]);

        $this->assertSame($item->id, $wastage->stockItem->id);
        $this->assertSame($admin->id, $wastage->admin->id);
        $this->assertCount(1, $item->wastages);
    }
}
