<?php

namespace Tests\Feature;

use App\Models\StockItem;
use Database\Seeders\StockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_seeder_creates_all_stock_items_on_first_run(): void
    {
        $this->seed(StockSeeder::class);

        $this->assertDatabaseCount('stock_items', 4);
        $this->assertDatabaseHas('stock_items', [
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 5000,
            'min_threshold' => 1000,
        ]);
        $this->assertDatabaseHas('stock_items', ['name' => 'Susu']);
        $this->assertDatabaseHas('stock_items', ['name' => 'Gelas']);
        $this->assertDatabaseHas('stock_items', ['name' => 'Gula']);
    }

    public function test_reseeding_does_not_duplicate_stock_items(): void
    {
        $this->seed(StockSeeder::class);
        $this->seed(StockSeeder::class);
        $this->seed(StockSeeder::class);

        $this->assertDatabaseCount('stock_items', 4);

        foreach (StockItem::pluck('name') as $name) {
            $this->assertSame(1, StockItem::where('name', $name)->count());
        }
    }

    public function test_reseeding_leaves_live_quantity_and_min_threshold_untouched(): void
    {
        $this->seed(StockSeeder::class);

        StockItem::where('name', 'Biji Kopi')->update([
            'quantity' => 12345,
            'min_threshold' => 250,
        ]);

        $this->seed(StockSeeder::class);

        $this->assertDatabaseHas('stock_items', [
            'name' => 'Biji Kopi',
            'quantity' => 12345,
            'min_threshold' => 250,
        ]);
        $this->assertDatabaseCount('stock_items', 4);
    }

    public function test_reseeding_refreshes_static_fields_but_not_stock_levels(): void
    {
        $this->seed(StockSeeder::class);

        StockItem::where('name', 'Biji Kopi')->update([
            'unit' => 'kg',
            'note' => 'Nota yang sudah diubah',
            'quantity' => 999,
        ]);

        $this->seed(StockSeeder::class);

        $this->assertDatabaseHas('stock_items', [
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'note' => 'Single-origin, sangrai medium',
            'quantity' => 999,
        ]);
    }
}
