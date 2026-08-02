<?php

namespace Tests\Feature;

use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected const CSP_REPORT_ONLY = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; img-src 'self' data: https://gravatar.com https://www.gravatar.com; font-src 'self' data: https://fonts.bunny.net; connect-src 'self' ws: wss:; frame-src 'self' https://maps.google.com https://www.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self';";

    public function test_public_page_sends_security_headers(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy-Report-Only', self::CSP_REPORT_ONLY);
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_csp_report_only_allows_only_self_framing(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        $response->assertHeader('Content-Security-Policy-Report-Only', self::CSP_REPORT_ONLY);
    }

    public function test_headers_absent_on_404_response(): void
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();
        $response->assertHeaderMissing('X-Content-Type-Options');
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('Referrer-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_headers_absent_on_redirect_response(): void
    {
        $response = $this->get('/lang/en');

        $response->assertRedirect();
        $response->assertHeaderMissing('X-Content-Type-Options');
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('Referrer-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_hsts_not_sent_outside_production_even_over_https(): void
    {
        $this->seed(MenuSeeder::class);

        $this->get('/', ['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_sent_on_https_production_requests(): void
    {
        $this->seed(MenuSeeder::class);
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/', ['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_hsts_not_sent_on_plain_http_in_production(): void
    {
        $this->seed(MenuSeeder::class);
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
