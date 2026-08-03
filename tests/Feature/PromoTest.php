<?php

namespace Tests\Feature;

use App\Filament\Resources\Promos\Pages\CreatePromo;
use App\Filament\Resources\Promos\PromoResource;
use App\Models\Admin;
use App\Models\Promo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_scope_includes_active_promo_within_window(): void
    {
        $promo = Promo::factory()->create([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->assertTrue(Promo::query()->visible()->pluck('id')->contains($promo->id));
    }

    public function test_visible_scope_includes_active_promo_with_null_ends_at(): void
    {
        $promo = Promo::factory()->create([
            'ends_at' => null,
        ]);

        $this->assertTrue(Promo::query()->visible()->pluck('id')->contains($promo->id));
    }

    public function test_visible_scope_excludes_inactive_promo(): void
    {
        $promo = Promo::factory()->create([
            'active' => false,
        ]);

        $this->assertFalse(Promo::query()->visible()->pluck('id')->contains($promo->id));
    }

    public function test_visible_scope_excludes_promo_before_start(): void
    {
        $promo = Promo::factory()->create([
            'starts_at' => now()->addDay(),
        ]);

        $this->assertFalse(Promo::query()->visible()->pluck('id')->contains($promo->id));
    }

    public function test_visible_scope_excludes_promo_after_end(): void
    {
        $promo = Promo::factory()->create([
            'ends_at' => now()->subDay(),
        ]);

        $this->assertFalse(Promo::query()->visible()->pluck('id')->contains($promo->id));
    }

    public function test_active_promo_within_window_renders_on_home(): void
    {
        $promo = Promo::factory()->create([
            'title' => 'Promo Ramadhan Spesial',
            'badge' => 'Diskon 20%',
            'cta_text' => 'Pesan Sekarang',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewHas('promo', fn ($viewPromo) => $viewPromo->is($promo));
        $response->assertSee('Promo Ramadhan Spesial');
        $response->assertSee('Diskon 20%');
        $response->assertSee('Pesan Sekarang');
    }

    public function test_active_promo_within_window_renders_on_menu(): void
    {
        $promo = Promo::factory()->create([
            'title' => 'Promo Ramadhan Spesial',
        ]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertViewHas('promo');
        $response->assertSee('Promo Ramadhan Spesial');
    }

    public function test_promo_with_null_ends_at_renders_on_home(): void
    {
        Promo::factory()->create([
            'title' => 'Promo Tanpa Batas Waktu',
            'ends_at' => null,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Promo Tanpa Batas Waktu');
    }

    public function test_expired_promo_is_hidden_on_home_and_menu(): void
    {
        Promo::factory()->create([
            'title' => 'Promo Kadaluarsa',
            'ends_at' => now()->subDay(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Promo Kadaluarsa');
        $this->get('/menu')->assertOk()->assertDontSee('Promo Kadaluarsa');
    }

    public function test_not_yet_started_promo_is_hidden_on_home_and_menu(): void
    {
        Promo::factory()->create([
            'title' => 'Promo Mendatang',
            'starts_at' => now()->addDay(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Promo Mendatang');
        $this->get('/menu')->assertOk()->assertDontSee('Promo Mendatang');
    }

    public function test_inactive_promo_is_hidden_on_home_and_menu(): void
    {
        Promo::factory()->create([
            'title' => 'Promo Nonaktif',
            'active' => false,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Promo Nonaktif');
        $this->get('/menu')->assertOk()->assertDontSee('Promo Nonaktif');
    }

    public function test_only_first_promo_by_sort_order_renders(): void
    {
        $first = Promo::factory()->create([
            'title' => 'Promo Utama',
            'sort_order' => 0,
        ]);
        Promo::factory()->create([
            'title' => 'Promo Cadangan',
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewHas('promo', fn ($viewPromo) => $viewPromo->is($first));
        $response->assertSee('Promo Utama');
        $response->assertDontSee('Promo Cadangan');
    }

    public function test_admin_can_open_promo_resource_pages(): void
    {
        $admin = Admin::factory()->create();
        $promo = Promo::factory()->create(['title' => 'Promo Admin']);

        $this->actingAs($admin, 'admin')
            ->get(PromoResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Promo Admin');

        $this->actingAs($admin, 'admin')
            ->get(PromoResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get(PromoResource::getUrl('edit', ['record' => $promo]))
            ->assertOk()
            ->assertSee('Promo Admin');
    }

    // ---------------------------------------------------------------------
    // Resource hardening (Vikunja 160): ends_at must not precede starts_at,
    // and the cta_url placeholder is localized.
    // ---------------------------------------------------------------------

    public function test_admin_create_form_rejects_ends_before_starts(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreatePromo::class)
            ->fillForm([
                'title' => 'Promo Invalid',
                'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->call('create')
            ->assertHasFormErrors(['ends_at']);

        $this->assertDatabaseCount('promos', 0);
    }

    public function test_admin_create_form_accepts_ends_after_starts(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreatePromo::class)
            ->fillForm([
                'title' => 'Promo Valid',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('promos', 1);
    }

    public function test_admin_create_form_allows_null_ends_at(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreatePromo::class)
            ->fillForm([
                'title' => 'Promo Tanpa Akhir',
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('promos', 1);
    }

    public function test_admin_create_form_renders_localized_cta_url_placeholder(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(CreatePromo::class)
            ->assertSee(__('promos.fields.cta_url_placeholder'));
    }
}
