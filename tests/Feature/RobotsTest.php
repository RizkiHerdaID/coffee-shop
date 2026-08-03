<?php

namespace Tests\Feature;

use Tests\TestCase;

class RobotsTest extends TestCase
{
    public function test_robots_txt_returns_200(): void
    {
        $this->get('/robots.txt')->assertOk();
    }

    public function test_robots_txt_content_type_is_plain_text(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_robots_txt_serves_absolute_sitemap_url_from_app_url_config(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Sitemap: '.config('app.url').'/sitemap.xml', false);
        $this->assertStringStartsWith('http', config('app.url'));
    }

    public function test_robots_txt_content_matches_expected_format(): void
    {
        $response = $this->get('/robots.txt');

        $this->assertSame(
            "User-agent: *\nAllow: /\n\nSitemap: ".config('app.url')."/sitemap.xml\n",
            $response->getContent(),
        );
    }

    public function test_static_robots_file_does_not_shadow_the_route(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }
}
