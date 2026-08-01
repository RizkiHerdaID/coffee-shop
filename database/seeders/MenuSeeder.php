<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $items = [
            ['name' => 'Espresso', 'price' => 25000, 'note' => 'Double shot, rich crema'],
            ['name' => 'Cappuccino', 'price' => 32000, 'note' => 'Velvet milk foam'],
            ['name' => 'Flat White', 'price' => 34000, 'note' => 'Smooth, strong, balanced'],
            ['name' => 'V60 Pour Over', 'price' => 40000, 'note' => 'Single-origin, brewed to order'],
            ['name' => 'Cold Brew', 'price' => 38000, 'note' => '18-hour slow steep'],
            ['name' => 'Matcha Latte', 'price' => 35000, 'note' => 'Ceremonial grade'],
            ['name' => 'Banana Bread', 'price' => 18000, 'note' => 'Baked fresh daily'],
            ['name' => 'Butter Croissant', 'price' => 16000, 'note' => 'Flaky, golden layers'],
        ];

        foreach ($items as $sortOrder => $item) {
            MenuItem::query()->firstOrCreate(
                ['name' => $item['name']],
                ['price' => $item['price'], 'note' => $item['note'], 'sort_order' => $sortOrder + 1],
            );
        }
    }
}
