<?php

namespace Tests\Feature;

use App\Filament\Widgets\LowStockWidget;
use App\Models\Admin;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

class LowStockWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    public function test_widget_query_returns_only_low_stock_items_ordered_by_quantity(): void
    {
        StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 1000, 'min_threshold' => 500]);
        StockItem::create(['name' => 'Gelas', 'unit' => 'pcs', 'quantity' => 0, 'min_threshold' => 50]);
        StockItem::create(['name' => 'Susu', 'unit' => 'liter', 'quantity' => 2, 'min_threshold' => 2]);

        $items = $this->widgetQuery()->pluck('name')->all();

        $this->assertSame(['Gelas', 'Susu'], $items);
        $this->assertNotContains('Biji Kopi', $items);
    }

    public function test_widget_query_is_empty_when_stock_is_healthy(): void
    {
        StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 1000, 'min_threshold' => 500]);

        $this->assertSame(0, $this->widgetQuery()->count());
    }

    public function test_stock_in_action_from_widget_records_movement(): void
    {
        $item = StockItem::create(['name' => 'Gelas', 'unit' => 'pcs', 'quantity' => 0, 'min_threshold' => 50]);

        $this->actingAs($this->admin, 'admin');

        Livewire::test(LowStockWidget::class)
            ->callTableAction('stockIn', $item, data: [
                'quantity' => '2.000',
                'note' => 'Restock dari gudang',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('stock.notifications.stock_in_success'));

        $this->assertSame(2000, $item->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'type' => 'in',
            'quantity' => 2000,
            'note' => 'Restock dari gudang',
        ]);
    }

    public function test_stock_in_action_from_widget_rejects_zero_quantity(): void
    {
        $item = StockItem::create(['name' => 'Gelas', 'unit' => 'pcs', 'quantity' => 0, 'min_threshold' => 50]);

        $this->actingAs($this->admin, 'admin');

        Livewire::test(LowStockWidget::class)
            ->callTableAction('stockIn', $item, data: [
                'quantity' => '0',
                'note' => 'Stok kosong',
            ])
            ->assertHasTableActionErrors(['quantity'])
            ->assertNotNotified(__('stock.notifications.stock_in_success'));

        $this->assertSame(0, $item->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_heading_is_localized(): void
    {
        app()->setLocale('id');
        $this->assertSame('Stok Menipis', __('dashboard.low_stock_heading'));

        app()->setLocale('en');
        $this->assertSame('Low Stock', __('dashboard.low_stock_heading'));
    }

    public function test_dashboard_renders_with_low_stock_widget_registered(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk();
    }

    /**
     * @return Builder<StockItem>
     */
    private function widgetQuery()
    {
        return (new ReflectionClass(LowStockWidget::class))
            ->getMethod('getTableQuery')
            ->invoke(new LowStockWidget);
    }
}
