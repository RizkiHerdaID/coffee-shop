<?php

namespace Tests\Feature;

use App\Models\MenuItem;
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

    public function test_home_page_hides_unavailable_items(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::where('name', 'Espresso')->update(['available' => false]);

        $response = $this->get('/');

        $response->assertDontSee('Espresso');
        $response->assertSee('Cappuccino');
    }

    public function test_home_page_has_whatsapp_cta(): void
    {
        $response = $this->get('/');

        $response->assertSee('https://wa.me/'.preg_replace('/\D/', '', config('shop.phone')));
        $response->assertSee('Pesan lewat WhatsApp');
    }

    public function test_home_page_mentions_qris(): void
    {
        $response = $this->get('/');

        $response->assertSee(__('home.cards.qris.title'));
    }

    public function test_home_page_shows_cta_feature_cards(): void
    {
        $response = $this->get('/');

        $response->assertSee(__('home.cards.whatsapp.title'));
        $response->assertSee(__('home.cards.qris.title'));
        $response->assertSee(__('home.cards.delivery.title'));
    }

    public function test_home_page_shows_delivery_app_links(): void
    {
        $response = $this->get('/');

        $response->assertSee(config('shop.gofood_url'));
        $response->assertSee(config('shop.grab_url'));
    }

    public function test_home_page_renders_localized_copy_for_both_locales(): void
    {
        $this->seed(MenuSeeder::class);

        $id = $this->get('/');

        $id->assertOk();
        $id->assertSee(__('home.hero.cta_menu'));
        $id->assertSee('lang="id"', false);

        app()->setLocale('en');

        $en = $this->get('/?lang=en');

        $en->assertOk();
        $en->assertSee(__('home.hero.cta_menu'));
        $en->assertSee('lang="en"', false);
    }

    public function test_home_page_shows_localized_empty_state_when_no_items(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(__('home.favorites.empty'));
    }

    public function test_home_page_has_no_hardcoded_english_strings_in_id_locale(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Order online');
        $response->assertDontSee('Change language to English');
    }
}
