<?php

namespace Tests\Feature;

use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
    }

    public function test_default_locale_is_indonesian(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Beranda');
        $response->assertSee('Pesan lewat WhatsApp');
        $response->assertSee('lang="id"', false);
    }

    public function test_english_locale_via_query_param(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('Home');
        $response->assertSee('Order on WhatsApp');
        $response->assertSee('lang="en"', false);
    }

    public function test_language_switch_stays_on_the_same_page(): void
    {
        $this->withHeaders(['Referer' => url('/menu')])
            ->get('/lang/en')
            ->assertRedirect(url('/menu'));

        $this->get('/')->assertOk()->assertSee('Home');
    }

    public function test_language_switch_back_to_indonesian(): void
    {
        $this->withHeaders(['Referer' => url('/')])->get('/lang/en');
        $this->withHeaders(['Referer' => url('/')])->get('/lang/id')->assertRedirect(url('/'));

        $this->get('/')->assertOk()->assertSee('Beranda');
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->get('/lang/fr')->assertNotFound();
    }
}
