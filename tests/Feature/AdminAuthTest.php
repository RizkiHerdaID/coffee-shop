<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_page_renders(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Admin Login');
    }

    public function test_admin_can_sign_in_with_valid_credentials(): void
    {
        $admin = Admin::factory()->create(['password' => 'password']);

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_cannot_sign_in_with_invalid_credentials(): void
    {
        $admin = Admin::factory()->create();

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_authenticated_admin_can_access_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($admin->name);
    }

    public function test_admin_can_log_out(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_authenticated_admin_visiting_login_page_is_redirected_to_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_requires_an_email(): void
    {
        $this->post(route('admin.login.attempt'), [
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $this->post(route('admin.login.attempt'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_login_requires_a_password(): void
    {
        $this->post(route('admin.login.attempt'), [
            'email' => 'needs-password@example.com',
        ])->assertSessionHasErrors('password');

        $this->assertGuest('admin');
    }

    public function test_successful_login_regenerates_the_session(): void
    {
        $admin = Admin::factory()->create(['password' => 'password']);

        $this->get(route('admin.login'));
        $sessionIdBefore = $this->app['session']->getId();
        $this->assertNotEmpty($sessionIdBefore);

        $this->post(route('admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($sessionIdBefore, $this->app['session']->getId());
    }

    public function test_logged_out_admin_cannot_access_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }
}
