<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Admin;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuppliersTest extends TestCase
{
    use RefreshDatabase;

    private function makeSupplier(): Supplier
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

    private function makePo(Supplier $supplier, array $attributes = []): void
    {
        $supplier->purchaseOrders()->create($attributes);
    }

    public function test_create_form_stores_supplier(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateSupplier::class)
            ->fillForm([
                'name' => 'PT Kopi Nusantara',
                'contact_person' => 'Budi',
                'phone' => '081234567890',
                'email' => 'budi@example.com',
                'address' => 'Jl. Merdeka 1',
                'note' => 'Supplier utama',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('suppliers', [
            'name' => 'PT Kopi Nusantara',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
            'note' => 'Supplier utama',
        ]);
    }

    public function test_create_form_requires_name(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateSupplier::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name']);
    }

    public function test_edit_form_prefills_values(): void
    {
        $admin = Admin::factory()->create();
        $supplier = Supplier::create([
            'name' => 'PT Kopi Nusantara',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
            'note' => 'Supplier utama',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(EditSupplier::class, ['record' => $supplier->getKey()])
            ->assertFormSet([
                'name' => 'PT Kopi Nusantara',
                'email' => 'budi@example.com',
            ]);
    }

    public function test_create_form_shows_localized_labels(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreateSupplier::class)
            ->assertSee(__('suppliers.fields.name'))
            ->assertSee(__('suppliers.fields.contact_person'))
            ->assertSee(__('suppliers.fields.phone'))
            ->assertSee(__('suppliers.fields.email'))
            ->assertSee(__('suppliers.fields.address'))
            ->assertSee(__('suppliers.fields.note'));
    }

    public function test_po_count_counts_all_purchase_orders_including_cancelled(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Received, 'total' => 50000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Received, 'total' => 75000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Received, 'total' => 100000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 250000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Cancelled, 'total' => 300000]);

        $this->assertSame(5, $supplier->poCount());
        $this->assertSame(0, $this->makeSupplier()->poCount());
    }

    public function test_received_total_sums_only_received_pos(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Received, 'total' => 50000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Received, 'total' => 75000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Received, 'total' => 100000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 250000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Cancelled, 'total' => 300000]);

        $this->assertSame(225000, $supplier->receivedTotal());
        $this->assertSame(0, $this->makeSupplier()->receivedTotal());
    }

    public function test_outstanding_count_counts_only_pending_pos(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Received, 'total' => 50000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 250000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 300000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Cancelled, 'total' => 350000]);

        $this->assertSame(2, $supplier->outstandingCount());
        $this->assertSame(0, $this->makeSupplier()->outstandingCount());
    }

    public function test_avg_lead_days_averages_received_at_minus_ordered_at_in_days(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 50000,
            'ordered_at' => '2026-08-01 09:00:00',
            'received_at' => '2026-08-04 00:00:00',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 75000,
            'ordered_at' => '2026-08-02 09:00:00',
            'received_at' => '2026-08-06 00:00:00',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 100000,
            'ordered_at' => '2026-08-03 09:00:00',
            'received_at' => '2026-08-05 00:00:00',
        ]);

        $this->assertSame(3.0, $supplier->avgLeadDays());
    }

    public function test_avg_lead_days_rounds_to_one_decimal_and_skips_pending_cancelled(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 50000,
            'ordered_at' => '2026-08-01 09:00:00',
            'received_at' => '2026-08-04 00:00:00',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 75000,
            'ordered_at' => '2026-08-02 09:00:00',
            'received_at' => '2026-08-06 00:00:00',
        ]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 250000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Cancelled, 'total' => 300000]);

        $this->assertSame(3.5, $supplier->avgLeadDays());
    }

    public function test_avg_lead_days_returns_null_when_no_qualifying_received_po(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 250000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Cancelled, 'total' => 300000]);

        $this->assertNull($supplier->avgLeadDays());

        $noTimestamps = $this->makeSupplier();
        $this->makePo($noTimestamps, ['status' => PurchaseOrderStatus::Received, 'total' => 50000]);

        $this->assertNull($noTimestamps->avgLeadDays());
    }

    public function test_on_time_rate_counts_received_at_on_or_before_expected_at(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 50000,
            'received_at' => '2026-08-04 00:00:00',
            'expected_at' => '2026-08-05',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 75000,
            'received_at' => '2026-08-06 00:00:00',
            'expected_at' => '2026-08-05',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 100000,
            'received_at' => '2026-08-08 00:00:00',
            'expected_at' => '2026-08-10',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 120000,
            'received_at' => '2026-08-12 00:00:00',
            'expected_at' => '2026-08-12',
        ]);

        $this->assertSame(75, $supplier->onTimeRate());
    }

    public function test_on_time_rate_ignores_received_pos_without_expected_at(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 50000,
            'received_at' => '2026-08-04 00:00:00',
            'expected_at' => '2026-08-05',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 75000,
            'received_at' => '2026-08-08 00:00:00',
            'expected_at' => '2026-08-10',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 100000,
            'received_at' => '2026-08-09 00:00:00',
        ]);

        $this->assertSame(100, $supplier->onTimeRate());
    }

    public function test_on_time_rate_returns_null_when_no_received_po_has_expected_at(): void
    {
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 50000,
            'received_at' => '2026-08-04 00:00:00',
        ]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 250000]);

        $this->assertNull($supplier->onTimeRate());
    }

    public function test_suppliers_table_shows_scorecard_aggregate_columns(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 50000,
            'ordered_at' => '2026-08-01 09:00:00',
            'received_at' => '2026-08-04 00:00:00',
            'expected_at' => '2026-08-05',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 75000,
            'ordered_at' => '2026-08-02 09:00:00',
            'received_at' => '2026-08-06 00:00:00',
            'expected_at' => '2026-08-05',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 100000,
            'ordered_at' => '2026-08-03 09:00:00',
            'received_at' => '2026-08-05 00:00:00',
        ]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Pending, 'total' => 250000]);
        $this->makePo($supplier, ['status' => PurchaseOrderStatus::Cancelled, 'total' => 300000]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListSuppliers::class)
            ->assertTableColumnExists('po_count')
            ->assertTableColumnExists('total_spend')
            ->assertTableColumnExists('outstanding_count')
            ->assertTableColumnExists('avg_lead_days')
            ->assertTableColumnExists('on_time_rate')
            ->assertTableColumnStateSet('po_count', 5, $supplier)
            ->assertTableColumnStateSet('total_spend', 225000, $supplier)
            ->assertTableColumnStateSet('outstanding_count', 1, $supplier)
            ->assertTableColumnStateSet('avg_lead_days', 3.0, $supplier)
            ->assertTableColumnStateSet('on_time_rate', 50, $supplier);
    }

    public function test_suppliers_table_scorecard_columns_are_null_for_supplier_without_pos(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();

        Livewire::actingAs($admin, 'admin')
            ->test(ListSuppliers::class)
            ->assertTableColumnStateSet('po_count', 0, $supplier)
            ->assertTableColumnStateSet('total_spend', 0, $supplier)
            ->assertTableColumnStateSet('outstanding_count', 0, $supplier)
            ->assertTableColumnStateSet('avg_lead_days', null, $supplier)
            ->assertTableColumnStateSet('on_time_rate', null, $supplier);
    }

    public function test_suppliers_table_scorecard_columns_have_localized_headers(): void
    {
        $admin = Admin::factory()->create();
        $this->makeSupplier();

        Livewire::actingAs($admin, 'admin')
            ->test(ListSuppliers::class)
            ->assertSee(__('suppliers.scorecard.orders_count'))
            ->assertSee(__('suppliers.scorecard.total_spend'))
            ->assertSee(__('suppliers.scorecard.outstanding'))
            ->assertSee(__('suppliers.scorecard.avg_lead_time'))
            ->assertSee(__('suppliers.scorecard.on_time_rate'));
    }

    public function test_suppliers_table_formats_scorecard_values(): void
    {
        $admin = Admin::factory()->create();
        $supplier = $this->makeSupplier();

        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 50000,
            'ordered_at' => '2026-08-01 09:00:00',
            'received_at' => '2026-08-04 00:00:00',
            'expected_at' => '2026-08-05',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 75000,
            'ordered_at' => '2026-08-02 09:00:00',
            'received_at' => '2026-08-04 00:00:00',
            'expected_at' => '2026-08-05',
        ]);
        $this->makePo($supplier, [
            'status' => PurchaseOrderStatus::Received,
            'total' => 100000,
            'ordered_at' => '2026-08-03 09:00:00',
            'received_at' => '2026-08-05 00:00:00',
            'expected_at' => '2026-08-04',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ListSuppliers::class)
            ->assertSee('Rp 225.000')
            ->assertSee('2,3 '.__('suppliers.scorecard.days'))
            ->assertSee('67%');
    }
}
