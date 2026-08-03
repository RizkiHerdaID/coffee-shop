<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseOrders\RelationManagers\PurchaseOrderItemsRelationManager;
use App\Models\Admin;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'PT Kopi Nusantara',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
            'note' => 'Supplier utama',
        ]);
    }

    public function test_create_form_stores_masked_total_as_raw_integer(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();

        Livewire::actingAs($admin, 'admin')
            ->test(CreatePurchaseOrder::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Pending,
                'total' => '25.000',
                'ordered_at' => '2026-08-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', ['total' => 25000]);

        Livewire::actingAs($admin, 'admin')
            ->test(CreatePurchaseOrder::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Pending,
                'total' => '1.500.000',
                'ordered_at' => '2026-08-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', ['total' => 1500000]);
    }

    public function test_edit_form_prefills_total_with_indonesian_separators(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 1500000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditPurchaseOrder::class, ['record' => $po->getKey()])
            ->assertFormSet(['total' => '1.500.000']);
    }

    public function test_create_form_shows_localized_labels(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreatePurchaseOrder::class)
            ->assertSee(__('purchase-orders.fields.supplier'))
            ->assertSee(__('purchase-orders.fields.total'))
            ->assertSee(__('purchase-orders.statuses.pending'));
    }

    public function test_status_badge_formats_localized(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Received,
            'total' => 25000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListPurchaseOrders::class)
            ->assertSee(__('purchase-orders.statuses.received'));
    }

    public function test_relation_manager_lists_order_items(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 500000,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'description' => 'Biji kopi arabika',
            'quantity' => 10,
            'unit_price' => 50000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(PurchaseOrderItemsRelationManager::class, [
                'ownerRecord' => $po,
                'pageClass' => EditPurchaseOrder::class,
            ])
            ->assertSee('Biji kopi arabika')
            ->assertSee('10');
    }

    public function test_relation_manager_requires_description_and_quantity(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 0,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(PurchaseOrderItemsRelationManager::class, [
                'ownerRecord' => $po,
                'pageClass' => EditPurchaseOrder::class,
            ])
            ->callTableAction('create', data: [
                'description' => '',
                'quantity' => null,
            ])
            ->assertHasTableActionErrors(['description']);

        Livewire::actingAs($admin, 'admin')
            ->test(PurchaseOrderItemsRelationManager::class, [
                'ownerRecord' => $po,
                'pageClass' => EditPurchaseOrder::class,
            ])
            ->callTableAction('create', data: [
                'description' => 'Gula aren',
                'quantity' => 5,
                'unit_price' => '10.000',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('purchase_order_items', [
            'description' => 'Gula aren',
            'quantity' => 5,
            'unit_price' => 10000,
        ]);
    }

    public function test_recalculate_total_sums_line_items(): void
    {
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'description' => 'Biji kopi arabika',
            'quantity' => 10,
            'unit_price' => 50000,
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'description' => 'Gula aren',
            'quantity' => 5,
            'unit_price' => 25000,
        ]);

        $po->recalculateTotal();

        $this->assertSame(625000, $po->fresh()->total);
    }

    private function makeStockItem(string $name, int $quantity, int $minThreshold): StockItem
    {
        return StockItem::create([
            'name' => $name,
            'unit' => 'gram',
            'quantity' => $quantity,
            'min_threshold' => $minThreshold,
        ]);
    }

    public function test_receive_action_stocks_in_each_linked_line_and_marks_po_received(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 750000,
        ]);

        $coffee = $this->makeStockItem('Biji Kopi Arabika', 1000, 500);
        $sugar = $this->makeStockItem('Gula Aren', 2000, 1000);

        foreach ([[$coffee, 10, 50000], [$sugar, 5, 25000]] as [$stock, $quantity, $unitPrice]) {
            $item = PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'description' => $stock->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ]);
            $item->forceFill(['stock_item_id' => $stock->id])->save();
        }

        Livewire::actingAs($admin, 'admin')
            ->test(ListPurchaseOrders::class)
            ->callTableAction('receive', $po)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => PurchaseOrderStatus::Received->value,
        ]);
        $this->assertSame(1010, $coffee->fresh()->quantity);
        $this->assertSame(2005, $sugar->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $coffee->id,
            'type' => 'in',
            'quantity' => 10,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $sugar->id,
            'type' => 'in',
            'quantity' => 5,
        ]);
        $this->assertSame(1, $coffee->fresh()->movements()->count());
        $this->assertSame(2, StockMovement::count());
    }

    public function test_receive_action_keeps_description_fallback_for_unlinked_lines(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 300000,
        ]);

        $coffee = $this->makeStockItem('Biji Kopi Arabika', 1000, 500);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'description' => 'Biji Kopi Arabika',
            'quantity' => 10,
            'unit_price' => 50000,
        ])->forceFill(['stock_item_id' => $coffee->id])->save();

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'description' => 'Kemasan pouch custom',
            'quantity' => 100,
            'unit_price' => 2500,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListPurchaseOrders::class)
            ->callTableAction('receive', $po)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => PurchaseOrderStatus::Received->value,
        ]);
        $this->assertSame(1010, $coffee->fresh()->quantity);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_receive_action_cannot_be_run_twice_on_same_po(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 500000,
        ]);

        $coffee = $this->makeStockItem('Biji Kopi Arabika', 1000, 500);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'description' => 'Biji Kopi Arabika',
            'quantity' => 10,
            'unit_price' => 50000,
        ])->forceFill(['stock_item_id' => $coffee->id])->save();

        Livewire::actingAs($admin, 'admin')
            ->test(ListPurchaseOrders::class)
            ->callTableAction('receive', $po)
            ->assertHasNoTableActionErrors();

        $this->assertSame(1010, $coffee->fresh()->quantity);

        Livewire::actingAs($admin, 'admin')
            ->test(ListPurchaseOrders::class)
            ->assertTableActionHidden('receive', $po);

        $this->assertSame(1010, $coffee->fresh()->quantity);
        $this->assertSame(1, $coffee->fresh()->movements()->count());
    }

    public function test_receive_is_idempotent_when_called_repeatedly(): void
    {
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 500000,
        ]);

        $coffee = $this->makeStockItem('Biji Kopi Arabika', 1000, 500);
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'description' => 'Biji Kopi Arabika',
            'quantity' => 10,
            'unit_price' => 50000,
        ])->forceFill(['stock_item_id' => $coffee->id])->save();

        $this->assertSame(1, $po->receive());
        $this->assertSame(1010, $coffee->fresh()->quantity);
        $this->assertSame(1, $coffee->fresh()->movements()->count());

        $this->assertSame(0, $po->fresh()->receive());
        $this->assertSame(1010, $coffee->fresh()->quantity);
        $this->assertSame(1, $coffee->fresh()->movements()->count());
        $this->assertSame(1, StockMovement::count());
    }

    public function test_receive_refuses_zero_total_order_at_model_level(): void
    {
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 0,
        ]);

        $this->assertSame(0, $po->receive());

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => PurchaseOrderStatus::Pending->value,
            'total' => 0,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_receive_action_refuses_zero_total_order(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 0,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListPurchaseOrders::class)
            ->callTableAction('receive', $po)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => PurchaseOrderStatus::Pending->value,
            'total' => 0,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_restock_suggestions_page_lists_low_stock_items_only(): void
    {
        $admin = Admin::factory()->create();
        $this->makeStockItem('Susu Bubuk Premium', 1, 2);
        $this->makeStockItem('Gelas Kertas 12oz', 0, 50);
        $healthy = $this->makeStockItem('Biji Kopi Arabika Gayo', 100, 10);

        $this->actingAs($admin, 'admin')
            ->get(PurchaseOrderResource::getUrl('restock'))
            ->assertOk()
            ->assertSee('Susu Bubuk Premium')
            ->assertSee('Gelas Kertas 12oz')
            ->assertDontSee($healthy->name);
    }

    private function makeReceivedPo(Supplier $supplier): PurchaseOrder
    {
        return PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Received,
            'total' => 500000,
            'received_at' => now(),
        ]);
    }

    public function test_received_purchase_order_cannot_be_deleted(): void
    {
        $supplier = $this->makeSupplier();
        $po = $this->makeReceivedPo($supplier);

        $this->assertThrows(
            fn () => $po->delete(),
            \RuntimeException::class,
        );

        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
    }

    public function test_pending_purchase_order_is_deletable(): void
    {
        $supplier = $this->makeSupplier();
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 250000,
        ]);

        $po->delete();

        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
    }

    public function test_delete_action_hidden_on_received_po_edit_page(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $received = $this->makeReceivedPo($supplier);
        $pending = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 250000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditPurchaseOrder::class, ['record' => $received->getKey()])
            ->assertActionHidden(DeleteAction::class);

        Livewire::actingAs($admin, 'admin')
            ->test(EditPurchaseOrder::class, ['record' => $pending->getKey()])
            ->assertActionVisible(DeleteAction::class);
    }

    public function test_bulk_delete_hidden_when_selected_pos_are_received(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $received = $this->makeReceivedPo($supplier);
        $pending = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 250000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListPurchaseOrders::class)
            ->selectTableRecords([$received])
            ->assertTableBulkActionHidden('delete')
            ->selectTableRecords([$pending])
            ->assertTableBulkActionVisible('delete');
    }

    public function test_received_po_edit_form_fields_are_disabled(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $received = $this->makeReceivedPo($supplier);
        $pending = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 250000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditPurchaseOrder::class, ['record' => $received->getKey()])
            ->assertFormFieldDisabled('supplier_id')
            ->assertFormFieldDisabled('ordered_at')
            ->assertFormFieldDisabled('expected_at')
            ->assertFormFieldDisabled('status')
            ->assertFormFieldDisabled('total')
            ->assertFormFieldDisabled('note');

        Livewire::actingAs($admin, 'admin')
            ->test(EditPurchaseOrder::class, ['record' => $pending->getKey()])
            ->assertFormFieldEnabled('supplier_id')
            ->assertFormFieldEnabled('total');
    }

    public function test_saving_a_received_po_is_a_noop(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        // Itemless fixture: afterSave() recalculates the total from line
        // items, so an itemless received PO proves the save mutated nothing.
        $received = $this->makeReceivedPo($supplier);

        Livewire::actingAs($admin, 'admin')
            ->test(EditPurchaseOrder::class, ['record' => $received->getKey()])
            ->fillForm(['note' => 'Nota yang tidak boleh tersimpan'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $received->id,
            'status' => PurchaseOrderStatus::Received->value,
            'total' => 500000,
            'note' => null,
        ]);
    }

    public function test_received_po_relation_manager_hides_create_action(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();
        $received = $this->makeReceivedPo($supplier);
        $pending = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 250000,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(PurchaseOrderItemsRelationManager::class, [
                'ownerRecord' => $received,
                'pageClass' => EditPurchaseOrder::class,
            ])
            ->assertTableActionHidden('create');

        Livewire::actingAs($admin, 'admin')
            ->test(PurchaseOrderItemsRelationManager::class, [
                'ownerRecord' => $pending,
                'pageClass' => EditPurchaseOrder::class,
            ])
            ->assertTableActionVisible('create');
    }
}

    // ---------------------------------------------------------------------
    // Delete protection + edit freeze (Vikunja 141): a received PO is an
    // audit record — its stock was already moved in, so it must not be
    // deletable or editable anymore. Unreceived POs stay fully manageable.
