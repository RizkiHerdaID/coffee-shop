<?php

namespace Tests\Feature;

use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_json_ld_local_business_schema(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('application/ld+json', false);
        $response->assertSee('https://schema.org', false);
        $response->assertSee('"@type":"Cafe"', false);
        $response->assertSee('openingHoursSpecification', false);
    }

    public function test_sitemap_route_returns_xml_with_page_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee(url('/'), false);
        $response->assertSee(url('/menu'), false);
        $response->assertSee(url('/contact'), false);
    }

    public function test_robots_txt_route_references_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }
}
