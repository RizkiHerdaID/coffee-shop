<?php

namespace Tests\Feature;

use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_highlights(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewHas('highlights');
    }

    public function test_home_page_shows_first_four_menu_items(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        foreach (['Espresso', 'Cappuccino', 'Flat White', 'V60 Pour Over'] as $name) {
            $response->assertSee($name);
        }

        $response->assertDontSee('Cold Brew');
    }

    public function test_home_page_shows_formatted_prices(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        $response->assertSee('Rp 25.000');
        $response->assertSee('Rp 32.000');
    }

    public function test_home_page_has_whatsapp_cta(): void
    {
        $response = $this->get('/');

        $response->assertSee('https://wa.me/'.preg_replace('/\D/', '', config('shop.phone')));
        $response->assertSee('Order on WhatsApp');
    }

    public function test_home_page_mentions_qris(): void
    {
        $response = $this->get('/');

        $response->assertSee('Terima QRIS');
    }

    public function test_home_page_shows_delivery_app_links(): void
    {
        $response = $this->get('/');

        $response->assertSee(config('shop.gofood_url'));
        $response->assertSee(config('shop.grab_url'));
    }
}
