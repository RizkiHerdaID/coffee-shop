<?php

namespace Database\Seeders;

use App\Models\StockItem;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Seed the application's stock items.
     */
    public function run(): void
    {
        $items = [
            ['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 5000, 'min_threshold' => 1000, 'note' => 'Single-origin, sangrai medium'],
            ['name' => 'Susu', 'unit' => 'liter', 'quantity' => 10, 'min_threshold' => 2, 'note' => 'Susu segar UHT'],
            ['name' => 'Gelas', 'unit' => 'pcs', 'quantity' => 100, 'min_threshold' => 50, 'note' => 'Gelas kertas double wall'],
            ['name' => 'Gula', 'unit' => 'gram', 'quantity' => 2000, 'min_threshold' => 500, 'note' => 'Gula pasir'],
        ];

        foreach ($items as $item) {
            StockItem::query()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                    'min_threshold' => $item['min_threshold'],
                    'note' => $item['note'],
                ],
            );
        }
    }
}
