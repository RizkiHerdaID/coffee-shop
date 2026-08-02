<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_admin_login_page_renders(): void
    {
        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Masuk');
    }

    public function test_admin_can_sign_in_with_valid_credentials(): void
    {
        $admin = Admin::factory()->create(['password' => 'password']);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(route('filament.admin.pages.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_cannot_sign_in_with_invalid_credentials(): void
    {
        $admin = Admin::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'wrong-password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest('admin');
    }

    public function test_authenticated_admin_can_access_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee($admin->name);
    }

    public function test_admin_can_log_out(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest('admin');
    }

    public function test_authenticated_admin_visiting_login_page_is_redirected_to_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('filament.admin.auth.login'))
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_login_requires_an_email(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest('admin');
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'not-an-email',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest('admin');
    }

    public function test_login_requires_a_password(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'needs-password@example.com'])
            ->call('authenticate')
            ->assertHasFormErrors(['password']);

        $this->assertGuest('admin');
    }

    public function test_successful_login_regenerates_the_session(): void
    {
        $admin = Admin::factory()->create(['password' => 'password']);

        $this->get(route('filament.admin.auth.login'));
        $sessionIdBefore = $this->app['session']->getId();
        $this->assertNotEmpty($sessionIdBefore);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertNotSame($sessionIdBefore, $this->app['session']->getId());
    }

    public function test_logged_out_admin_cannot_access_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('filament.admin.auth.logout'));

        $this->assertGuest('admin');

        $this->get(route('filament.admin.pages.dashboard'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }
}
