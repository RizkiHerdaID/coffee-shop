<?php

namespace Tests\Feature;

use App\Filament\Exports\ExpenseExporter;
use App\Filament\Exports\OrderExporter;
use App\Filament\Exports\PurchaseOrderExporter;
use App\Filament\Exports\StockItemExporter;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Models\Admin;
use App\Models\Expense;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\Supplier;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ExportsTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    private int $orderSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();

        Storage::fake('local');
    }

    public function test_stock_item_exporter_columns_are_localized(): void
    {
        app()->setLocale('id');
        $this->assertSame(__('stock.fields.name'), StockItemExporter::getColumns()[0]->getLabel());

        app()->setLocale('en');
        $this->assertSame(__('stock.fields.name'), StockItemExporter::getColumns()[0]->getLabel());
    }

    public function test_all_four_exporters_resolve_models_and_expose_columns(): void
    {
        $exporters = [
            StockItemExporter::class => StockItem::class,
            OrderExporter::class => Order::class,
            ExpenseExporter::class => Expense::class,
            PurchaseOrderExporter::class => PurchaseOrder::class,
        ];

        foreach ($exporters as $exporter => $model) {
            $this->assertSame($model, $exporter::getModel());

            $columns = $exporter::getColumns();
            $this->assertNotEmpty($columns, "{$exporter}::getColumns() is empty");
            $this->assertNotEmpty(array_filter($columns, fn ($column): bool => $column->isVisible()), "{$exporter} has no visible columns");
        }
    }

    public function test_stock_items_export_runs_end_to_end_from_resource_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 1000, 'min_threshold' => 500]);
        StockItem::create(['name' => 'Susu', 'unit' => 'liter', 'quantity' => 2, 'min_threshold' => 2]);

        Livewire::test(ListStockItems::class)
            ->callTableAction('export', data: $this->columnMapData([
                'name' => 'Nama',
                'unit' => 'Satuan',
                'quantity' => 'Jumlah',
                'min_threshold' => 'Batas Minimum',
                'cost' => 'Biaya per Satuan',
                'note' => 'Catatan',
                'created_at' => 'Dibuat',
                'updated_at' => 'Diperbarui',
            ]))
            ->assertHasNoTableActionErrors();

        $export = Export::query()->first();

        $this->assertNotNull($export);
        $this->assertSame(StockItemExporter::class, $export->exporter);
        $this->assertSame(2, $export->total_rows);
        $this->assertSame(2, $export->successful_rows);
        $this->assertSame($this->admin->id, $export->user_id);
        $this->assertSame(Admin::class, $export->user_type);
        $this->assertNotNull($export->completed_at);

        $this->assertExportedFilesContain($export, 'Biji Kopi');
    }

    public function test_orders_export_runs_end_to_end_from_resource_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        Order::withoutEvents(function (): void {
            Order::create(['order_number' => 'EXP-001', 'status' => 'paid', 'total' => 25000, 'created_by' => $this->admin->id]);
        });

        Livewire::test(ListOrders::class)
            ->callTableAction('export', data: $this->columnMapData([
                'order_number' => 'Order Number',
                'customer_phone' => 'Customer phone',
                'status' => 'Status',
                'total' => 'Total',
                'paid_total' => 'Paid',
                'shift_id' => 'Shift',
                'admin.name' => 'Created by',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
            ]))
            ->assertHasNoTableActionErrors();

        $export = Export::query()->first();

        $this->assertNotNull($export);
        $this->assertSame(OrderExporter::class, $export->exporter);
        $this->assertSame(1, $export->successful_rows);
        $this->assertSame($this->admin->id, $export->user_id);
        $this->assertSame(Admin::class, $export->user_type);

        $this->assertExportedFilesContain($export, 'EXP-001');
    }

    public function test_expenses_export_runs_end_to_end_from_resource_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        Expense::create([
            'category' => 'ingredients',
            'description' => 'Biji kopi 5kg',
            'amount' => 450000,
            'spent_at' => today(),
        ]);

        Livewire::test(ListExpenses::class)
            ->callTableAction('export', data: $this->columnMapData([
                'category' => 'Kategori',
                'description' => 'Deskripsi',
                'amount' => 'Jumlah',
                'spent_at' => 'Tanggal',
                'note' => 'Catatan',
                'created_at' => 'Dibuat',
                'updated_at' => 'Diperbarui',
            ]))
            ->assertHasNoTableActionErrors();

        $export = Export::query()->first();

        $this->assertNotNull($export);
        $this->assertSame(ExpenseExporter::class, $export->exporter);
        $this->assertSame(1, $export->successful_rows);
        $this->assertSame(Admin::class, $export->user_type);

        $this->assertExportedFilesContain($export, 'Biji kopi 5kg');
    }

    public function test_purchase_orders_export_runs_end_to_end_from_resource_page(): void
    {
        $this->actingAs($this->admin, 'admin');

        $supplier = Supplier::create(['name' => 'PT Kopi Nusantara']);
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'ordered_at' => today(),
            'status' => 'pending',
            'total' => 750000,
        ]);

        Livewire::test(ListPurchaseOrders::class)
            ->callTableAction('export', data: $this->columnMapData([
                'supplier.name' => 'Pemasok',
                'ordered_at' => 'Tanggal Pesan',
                'expected_at' => 'Tanggal Tiba',
                'status' => 'Status',
                'total' => 'Total',
                'note' => 'Catatan',
                'created_at' => 'Dibuat',
                'updated_at' => 'Diperbarui',
            ]))
            ->assertHasNoTableActionErrors();

        $export = Export::query()->first();

        $this->assertNotNull($export);
        $this->assertSame(PurchaseOrderExporter::class, $export->exporter);
        $this->assertSame(1, $export->successful_rows);
        $this->assertSame(Admin::class, $export->user_type);

        $this->assertExportedFilesContain($export, 'PT Kopi Nusantara');
    }

    public function test_export_download_route_streams_file_for_authenticated_admin(): void
    {
        $this->actingAs($this->admin, 'admin');

        StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 1000, 'min_threshold' => 500]);

        Livewire::test(ListStockItems::class)
            ->callTableAction('export', data: $this->columnMapData([
                'name' => 'Nama',
                'unit' => 'Satuan',
                'quantity' => 'Jumlah',
                'min_threshold' => 'Batas Minimum',
                'cost' => 'Biaya per Satuan',
                'note' => 'Catatan',
                'created_at' => 'Dibuat',
                'updated_at' => 'Diperbarui',
            ]))
            ->assertHasNoTableActionErrors();

        // The synchronous export pipeline calls auth()->forgetGuards() inside
        // the ExportCsv job, which discards the guard instance actingAs() set.
        // Re-authenticate before hitting the download route.
        $this->actingAs($this->admin, 'admin');

        $export = Export::query()->first();

        $this->actingAs($this->admin, 'admin');

        $url = URL::signedRoute('filament.exports.download', [
            'authGuard' => 'admin',
            'export' => $export->getKey(),
            'format' => 'csv',
        ], absolute: false);

        $expectedFileName = __('filament-actions::export.file_name', [
            'export_id' => $export->getKey(),
            'model' => 'stock-items',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertDownload("{$expectedFileName}.csv");
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, mixed>
     */
    private function columnMapData(array $labels): array
    {
        return [
            'columnMap' => collect($labels)
                ->map(fn (string $label): array => ['isEnabled' => true, 'label' => $label])
                ->all(),
        ];
    }

    private function assertExportedFilesContain(Export $export, string $needle): void
    {
        $files = Storage::disk('local')->allFiles("filament_exports/{$export->getKey()}");

        $this->assertNotEmpty($files);
        $this->assertNotEmpty(collect($files)->filter(fn (string $file): bool => str_ends_with($file, '.csv')));
        $this->assertNotEmpty(collect($files)->filter(fn (string $file): bool => str_ends_with($file, '.xlsx')));

        $contents = collect($files)
            ->map(fn (string $file): string => (string) Storage::disk('local')->get($file))
            ->implode("\n");

        $this->assertStringContainsString($needle, $contents);
    }
}
