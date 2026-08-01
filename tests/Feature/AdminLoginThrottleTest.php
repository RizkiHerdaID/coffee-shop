<?php

namespace Tests\Feature;

use App\Models\Admin;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $email = 'throttled@example.com';
        Admin::factory()->create(['email' => $email, 'password' => 'password']);

        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(Login::class)
                ->fillForm([
                    'email' => $email,
                    'password' => 'wrong-password',
                ])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);
        }

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertNotified();

        $this->assertGuest('admin');
    }

    public function test_throttle_is_keyed_by_ip_not_email(): void
    {
        $throttledEmail = 'throttled-a@example.com';
        $otherEmail = 'throttled-b@example.com';
        Admin::factory()->create(['email' => $throttledEmail, 'password' => 'password']);
        Admin::factory()->create(['email' => $otherEmail, 'password' => 'password']);

        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(Login::class)
                ->fillForm([
                    'email' => $throttledEmail,
                    'password' => 'wrong-password',
                ])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);
        }

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $otherEmail,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertNotified();

        $this->assertGuest('admin');
    }
}
