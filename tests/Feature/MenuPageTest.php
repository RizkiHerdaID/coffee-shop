<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_page_renders_with_menu(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertViewHas('menu');
    }

    public function test_menu_page_shows_all_eight_items(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        foreach (['Espresso', 'Cappuccino', 'Flat White', 'V60 Pour Over', 'Cold Brew', 'Matcha Latte', 'Banana Bread', 'Butter Croissant'] as $name) {
            $response->assertSee($name);
        }
    }

    public function test_menu_page_shows_formatted_prices(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertSee('Rp 25.000');
        $response->assertSee('Rp 16.000');
        $response->assertSee('Rp 40.000');
    }

    public function test_menu_page_contains_product_structured_data(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('"priceCurrency": "IDR"', false);
        $response->assertSee('Espresso');
    }

    public function test_menu_page_hides_unavailable_items(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::where('name', 'Espresso')->update(['available' => false]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertDontSee('Espresso');
    }
}
