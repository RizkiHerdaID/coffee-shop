<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\PurchaseOrders\RelationManagers\PurchaseOrderItemsRelationManager;
use App\Models\Admin;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
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
}
