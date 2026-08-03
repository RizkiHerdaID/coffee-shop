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
use Filament\Actions\DeleteAction;
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
            'quantity' => 50000,
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

        $this->assertSame(25000, $item->fresh()->quantity);
    }

    public function test_create_wastage_decrements_stock_and_records_out_movement(): void
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
                'quantity' => '250',
                'reason' => WasteReason::Spilled,
                'recorded_at' => '2026-08-02 08:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(750, $item->fresh()->quantity);
        $this->assertSame(1, $item->fresh()->movements()->count());
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'type' => 'out',
            'quantity' => 250,
        ]);
    }

    public function test_create_wastage_rejects_zero_quantity(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Gula Aren',
            'unit' => 'gram',
            'quantity' => 1000,
            'cost' => 20000,
            'min_threshold' => 100,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateWastage::class)
            ->fillForm([
                'stock_item_id' => $item->id,
                'quantity' => '0',
                'reason' => WasteReason::Spilled,
                'recorded_at' => '2026-08-02 08:00:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['quantity']);
    }

    public function test_create_wastage_rejects_quantity_above_available_stock(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Susu Segar',
            'unit' => 'liter',
            'quantity' => 100,
            'cost' => 15000,
            'min_threshold' => 20,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateWastage::class)
            ->fillForm([
                'stock_item_id' => $item->id,
                'quantity' => '500',
                'reason' => WasteReason::Expired,
                'recorded_at' => '2026-08-02 08:00:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['quantity']);

        $this->assertSame(100, $item->fresh()->quantity);
        $this->assertDatabaseCount('wastages', 0);
    }

    public function test_create_wastage_sets_admin_id_from_auth_ignoring_form_value(): void
    {
        $admin = Admin::factory()->create();
        $otherAdmin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Sirup Vanila',
            'unit' => 'ml',
            'quantity' => 1000,
            'cost' => 30000,
            'min_threshold' => 100,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreateWastage::class)
            ->fillForm([
                'stock_item_id' => $item->id,
                'quantity' => '100',
                'reason' => WasteReason::Damaged,
                'admin_id' => $otherAdmin->id,
                'recorded_at' => '2026-08-02 08:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($admin->id, Wastage::query()->firstOrFail()->admin_id);
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

    public function test_edit_wastage_increasing_quantity_decrements_stock_by_delta(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Kopi Robusta',
            'unit' => 'gram',
            'quantity' => 1000,
            'cost' => 40000,
            'min_threshold' => 100,
        ]);

        $wastage = $this->createWastageViaForm($admin, $item, '250');

        $this->assertSame(750, $item->fresh()->quantity);

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->fillForm(['quantity' => '400'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(600, $item->fresh()->quantity);
        $this->assertSame(400, $wastage->fresh()->quantity);
        $this->assertSame(400, $this->outMovementSum($item->fresh()));
        $this->assertSame(0, $this->inMovementSum($item->fresh()));
    }

    public function test_edit_wastage_decreasing_quantity_increases_stock_by_delta(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Susu Full Cream',
            'unit' => 'liter',
            'quantity' => 1000,
            'cost' => 25000,
            'min_threshold' => 50,
        ]);

        $wastage = $this->createWastageViaForm($admin, $item, '250');

        $this->assertSame(750, $item->fresh()->quantity);

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->fillForm(['quantity' => '100'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(900, $item->fresh()->quantity);
        $this->assertSame(100, $wastage->fresh()->quantity);
        $this->assertSame(100, $this->movementNet($item->fresh()));
    }

    public function test_edit_wastage_swapping_stock_item_adjusts_both_items(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Susu Full Cream',
            'unit' => 'liter',
            'quantity' => 1000,
            'cost' => 25000,
            'min_threshold' => 50,
        ]);
        $otherItem = StockItem::create([
            'name' => 'Susu UHT',
            'unit' => 'liter',
            'quantity' => 1000,
            'cost' => 20000,
            'min_threshold' => 50,
        ]);

        $wastage = $this->createWastageViaForm($admin, $item, '250');

        $this->assertSame(750, $item->fresh()->quantity);

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->fillForm([
                'stock_item_id' => $otherItem->id,
                'quantity' => '300',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1000, $item->fresh()->quantity);
        $this->assertSame(700, $otherItem->fresh()->quantity);
        $this->assertSame($otherItem->id, $wastage->fresh()->stock_item_id);
        $this->assertSame(250, $this->inMovementSum($item->fresh()));
        $this->assertSame(300, $this->outMovementSum($otherItem->fresh()));
    }

    public function test_edit_wastage_without_quantity_change_keeps_stock_unchanged(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Gula Pasir',
            'unit' => 'gram',
            'quantity' => 1000,
            'cost' => 18000,
            'min_threshold' => 100,
        ]);

        $wastage = $this->createWastageViaForm($admin, $item, '250');

        $this->assertSame(750, $item->fresh()->quantity);

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->fillForm(['quantity' => '250'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(750, $item->fresh()->quantity);
        $this->assertSame(250, $wastage->fresh()->quantity);
        $this->assertSame(250, $this->movementNet($item->fresh()));
        $this->assertSame(1, $item->fresh()->movements()->count());
    }

    public function test_edit_wastage_rejects_quantity_above_available_stock(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Biji Kopi Aceh',
            'unit' => 'gram',
            'quantity' => 1000,
            'cost' => 90000,
            'min_threshold' => 100,
        ]);

        $wastage = $this->createWastageViaForm($admin, $item, '250');

        $this->assertSame(750, $item->fresh()->quantity);

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->fillForm(['quantity' => '2.000'])
            ->call('save')
            ->assertHasFormErrors(['quantity']);

        $this->assertSame(750, $item->fresh()->quantity);
        $this->assertDatabaseHas('wastages', [
            'id' => $wastage->id,
            'quantity' => 250,
        ]);
        $this->assertSame(1, $item->fresh()->movements()->count());
        $this->assertSame(250, $this->movementNet($item->fresh()));
    }

    public function test_delete_wastage_restores_stock(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Sirup Karamel',
            'unit' => 'ml',
            'quantity' => 1000,
            'cost' => 35000,
            'min_threshold' => 100,
        ]);

        $wastage = $this->createWastageViaForm($admin, $item, '250');

        $this->assertSame(750, $item->fresh()->quantity);

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseCount('wastages', 0);
        $this->assertSame(1000, $item->fresh()->quantity);
        $this->assertSame(0, $this->movementNet($item->fresh()));
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 250,
        ]);
    }

    public function test_delete_wastage_when_stock_item_deleted_does_not_crash(): void
    {
        $admin = Admin::factory()->create();
        $item = StockItem::create([
            'name' => 'Espresso Blend',
            'unit' => 'gram',
            'quantity' => 1000,
            'cost' => 85000,
            'min_threshold' => 100,
        ]);

        $wastage = $this->createWastageViaForm($admin, $item, '250');

        $item->delete();

        Livewire::actingAs($admin, 'admin')
            ->test(EditWastage::class, ['record' => $wastage->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseCount('wastages', 0);
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

    private function createWastageViaForm(Admin $admin, StockItem $item, string $quantity): Wastage
    {
        Livewire::actingAs($admin, 'admin')
            ->test(CreateWastage::class)
            ->fillForm([
                'stock_item_id' => $item->id,
                'quantity' => $quantity,
                'reason' => WasteReason::Spilled,
                'recorded_at' => '2026-08-02 08:00:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return Wastage::query()->firstOrFail();
    }

    private function outMovementSum(StockItem $item): int
    {
        return (int) $item->movements()->where('type', 'out')->sum('quantity');
    }

    private function inMovementSum(StockItem $item): int
    {
        return (int) $item->movements()->where('type', 'in')->sum('quantity');
    }

    private function movementNet(StockItem $item): int
    {
        return $this->outMovementSum($item) - $this->inMovementSum($item);
    }
}
