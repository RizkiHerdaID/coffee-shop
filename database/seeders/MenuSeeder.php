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
            ['name' => 'Espresso', 'price' => 25000, 'note' => 'Double shot, crema pekat'],
            ['name' => 'Cappuccino', 'price' => 32000, 'note' => 'Busa susu yang lembut'],
            ['name' => 'Flat White', 'price' => 34000, 'note' => 'Halus, kuat, dan seimbang'],
            ['name' => 'V60 Pour Over', 'price' => 40000, 'note' => 'Single-origin, diseduh saat dipesan'],
            ['name' => 'Cold Brew', 'price' => 38000, 'note' => 'Diseduh perlahan 18 jam'],
            ['name' => 'Matcha Latte', 'price' => 35000, 'note' => 'Grade ceremonial'],
            ['name' => 'Banana Bread', 'price' => 18000, 'note' => 'Dipanggang segar setiap hari'],
            ['name' => 'Butter Croissant', 'price' => 16000, 'note' => 'Lapisan renyah, berwarna keemasan'],
        ];

        foreach ($items as $sortOrder => $item) {
            MenuItem::query()->updateOrCreate(
                ['name' => $item['name']],
                ['price' => $item['price'], 'note' => $item['note'], 'sort_order' => $sortOrder + 1],
            );
        }
    }
}
