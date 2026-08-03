<?php

namespace Tests\Feature;

use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_menu_page_renders_for_valid_table(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/qr/1');

        $response->assertOk();
        $response->assertViewHas('menu');
        $response->assertSee(__('qr.table_name', ['number' => 1]));
        $response->assertSee('Espresso');
        $response->assertSee('Butter Croissant');
    }

    public function test_qr_menu_page_shows_formatted_prices(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/qr/1');

        $response->assertSee('Rp 25.000');
        $response->assertSee('Rp 16.000');
    }

    public function test_qr_menu_page_aborts_for_table_beyond_count(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/qr/'.(config('shop.tables') + 1));

        $response->assertNotFound();
    }

    public function test_qr_menu_page_aborts_for_non_numeric_table(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/qr/abc');

        $response->assertNotFound();
    }

    public function test_qr_menu_page_shows_localized_empty_state_when_no_items(): void
    {
        $response = $this->get('/qr/1');

        $response->assertOk();
        $response->assertSee(__('qr.empty'));
    }

    public function test_qr_menu_page_renders_localized_copy(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/qr/1?lang=en');

        app()->setLocale('en');
        $response->assertOk();
        $response->assertSee(__('qr.table_name', ['number' => 1]));
        $response->assertSee(__('qr.open_full_menu'));
    }
}
