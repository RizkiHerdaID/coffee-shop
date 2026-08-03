<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MenuItem;
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

    public function test_invalid_query_locale_keeps_session_locale(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/?lang=xx')
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('lang="en"', false);

        $this->withSession(['locale' => 'id'])
            ->get('/?lang=xx')
            ->assertOk()
            ->assertSee('Beranda')
            ->assertSee('lang="id"', false);
    }

    public function test_language_switch_redirect_strips_lang_query_but_keeps_other_params(): void
    {
        $this->withHeaders(['Referer' => url('/menu?lang=id&foo=1')])
            ->get('/lang/en')
            ->assertRedirect(url('/menu?foo=1'));
    }

    public function test_admin_panel_honors_session_locale(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('filament.admin.auth.login'))
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_admin_panel_invalid_query_locale_does_not_shadow_session_locale(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('filament.admin.auth.login').'?lang=fr')
            ->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_admin_panel_brand_uses_shop_config_name(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee(config('shop.name'));
    }

    public function test_unknown_dynamic_keys_fall_back_instead_of_leaking_keys(): void
    {
        MenuItem::where('name', 'Matcha Latte')->firstOrFail()->update([
            'badges' => ['vegan', 'unknown_badge'],
            'category' => 'botanical',
        ]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee(__('menu.badges.vegan'));
        $response->assertDontSee('menu.badges.unknown_badge');
        $response->assertDontSee('menu.categories.botanical');
    }
}
