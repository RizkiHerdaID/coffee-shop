<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Admin login page with email|IP-scoped rate limiting.
 *
 * Filament's built-in limiter keys attempts by IP only; keying by
 * email + IP matches the app's per-account brute-force protection design
 * (see tests/Feature/AdminLoginThrottleTest.php).
 */
class Login extends BaseLogin
{
    protected function getRateLimitKey($method, $component = null)
    {
        $email = strtolower((string) ($this->data['email'] ?? ''));

        return 'livewire-rate-limiter:'.sha1($component.'|'.$method.'|'.$email.'|'.request()->ip());
    }
}
