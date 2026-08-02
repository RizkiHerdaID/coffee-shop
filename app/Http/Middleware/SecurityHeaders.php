<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    protected const HSTS_MAX_AGE = 31536000;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response->isRedirect()) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; img-src 'self' data: https://gravatar.com https://www.gravatar.com; font-src 'self' data: https://fonts.bunny.net; connect-src 'self' ws: wss:; frame-src 'self' https://maps.google.com https://www.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self';"
        );

        if ($this->enforceHsts($request)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.self::HSTS_MAX_AGE.'; includeSubDomains'
            );
        }

        return $response;
    }

    protected function enforceHsts(Request $request): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        return $request->isSecure() || $request->headers->get('X-Forwarded-Proto') === 'https';
    }
}
