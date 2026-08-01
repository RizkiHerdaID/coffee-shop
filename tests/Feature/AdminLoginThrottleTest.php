<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $email = 'throttled@example.com';
        Admin::factory()->create(['email' => $email, 'password' => 'password']);

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('admin.login.attempt'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('admin.login.attempt'), [
            'email' => $email,
            'password' => 'password',
        ])->assertStatus(429);

        $this->assertGuest('admin');
    }

    public function test_throttle_is_keyed_by_email_and_ip(): void
    {
        $throttledEmail = 'throttled-key-a@example.com';
        $otherEmail = 'throttled-key-b@example.com';
        Admin::factory()->create(['email' => $throttledEmail, 'password' => 'password']);
        Admin::factory()->create(['email' => $otherEmail, 'password' => 'password']);

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('admin.login.attempt'), [
                'email' => $throttledEmail,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('admin.login.attempt'), [
            'email' => $otherEmail,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }
}
