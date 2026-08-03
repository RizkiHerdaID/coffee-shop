<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_seeder_seeds_eight_menu_items(): void
    {
        $this->seed(MenuSeeder::class);

        $this->assertDatabaseCount('menu_items', 8);
        $this->assertDatabaseHas('menu_items', ['name' => 'Espresso']);
        $this->assertDatabaseHas('menu_items', ['name' => 'Butter Croissant']);
    }

    public function test_reseeding_menu_seeder_does_not_duplicate_items(): void
    {
        $this->seed(MenuSeeder::class);
        $this->seed(MenuSeeder::class);
        $this->seed(MenuSeeder::class);

        $this->assertDatabaseCount('menu_items', 8);

        foreach (MenuItem::pluck('name') as $name) {
            $this->assertSame(1, MenuItem::where('name', $name)->count());
        }
    }

    public function test_reseeding_menu_seeder_restores_edited_notes(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::where('name', 'Espresso')->update(['note' => 'Nota yang sudah diubah']);

        $this->seed(MenuSeeder::class);

        $espresso = MenuItem::where('name', 'Espresso')->firstOrFail();
        $this->assertSame('Double shot, crema pekat', $espresso->note);
        $this->assertDatabaseCount('menu_items', 8);
    }
}
