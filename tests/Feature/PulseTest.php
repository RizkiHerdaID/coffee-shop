<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PulseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/pulse')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_admin_can_access_pulse_dashboard(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/pulse')
            ->assertOk();
    }

    public function test_regular_user_cannot_access_pulse_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->get('/pulse')
            ->assertRedirect(route('filament.admin.auth.login'));
    }
}
