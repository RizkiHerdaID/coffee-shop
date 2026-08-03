<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_home_page_renders_og_image_and_twitter_image_meta_tags(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('property="og:image" content="'.url('/images/og-image.png').'"', false);
        $response->assertSee('name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('name="twitter:image" content="'.url('/images/og-image.png').'"', false);
    }

    public function test_all_public_pages_render_og_share_meta_tags(): void
    {
        foreach (['/', '/menu', '/contact', '/cek-poin', '/reservasi', '/qr/1'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee('property="og:image" content="'.url('/images/og-image.png').'"', false);
            $response->assertSee('name="twitter:image" content="'.url('/images/og-image.png').'"', false);
        }
    }

    public function test_og_image_file_exists_and_is_a_non_zero_png(): void
    {
        $path = public_path('images/og-image.png');

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
        $this->assertNotFalse(getimagesize($path));
    }

    public function test_sitemap_includes_lastmod_for_each_url(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('<lastmod>'.date('Y-m-d').'</lastmod>', false);
    }

    public function test_sitemap_includes_points_and_qr_table_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee(url('/cek-poin'), false);

        for ($table = 1; $table <= config('shop.tables'); $table++) {
            $response->assertSee(url('/qr/'.$table), false);
        }
    }

    public function test_og_url_does_not_carry_lang_query_param(): void
    {
        $response = $this->get('/menu?lang=en');

        $response->assertOk();
        $response->assertSee('property="og:url" content="'.url('/menu').'"', false);
        $response->assertDontSee('property="og:url" content="'.url('/menu').'?lang=en"', false);
    }

    public function test_menu_page_json_ld_escapes_script_tag_payload_in_item_names(): void
    {
        $this->seed(MenuSeeder::class);

        $payload = '</script><script>alert(1)</script>';
        MenuItem::create([
            'name' => $payload,
            'price' => 25000,
            'category' => 'coffee',
            'sort_order' => 99,
            'available' => true,
        ]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertDontSee('</script><script>', false);

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1], 'No JSON-LD blocks found');

        $itemListIndex = collect($matches[1])->search(fn (string $json): bool => str_contains($json, '"ItemList"'));

        $this->assertNotFalse($itemListIndex, 'Menu ItemList JSON-LD block not found');

        $itemListJson = $matches[1][$itemListIndex];

        $this->assertStringNotContainsString('</script>', $itemListJson);

        $escapedInJson = str_contains($itemListJson, '\u003C/script\u003E') || str_contains($itemListJson, '\u003C\/script\u003E');
        $this->assertTrue($escapedInJson, 'ItemList JSON-LD must contain the HEX-escaped script tag form');

        $itemList = json_decode($itemListJson, true);
        $this->assertIsArray($itemList, 'ItemList JSON-LD must remain valid JSON');

        $malicious = collect($itemList['itemListElement'])->firstWhere('name', $payload);

        $this->assertNotNull($malicious, 'Payload item missing from ItemList JSON-LD');
        $this->assertSame($payload, $malicious['name']);
        $this->assertSame(25000, $malicious['offers']['price']);
    }

    public function test_menu_page_renders_script_tag_payload_escaped_in_item_card_html(): void
    {
        $this->seed(MenuSeeder::class);

        $payload = '</script><script>alert(1)</script>';
        MenuItem::create([
            'name' => $payload,
            'price' => 25000,
            'category' => 'coffee',
            'sort_order' => 99,
            'available' => true,
        ]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee($payload, false);
    }

    public function test_home_page_does_not_break_out_of_script_blocks_for_malicious_item_name(): void
    {
        $this->seed(MenuSeeder::class);

        $payload = '</script><script>alert(1)</script>';
        MenuItem::create([
            'name' => $payload,
            'price' => 25000,
            'category' => 'coffee',
            'sort_order' => 1,
            'available' => true,
        ]);

        Cache::flush();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('</script><script>', false);
        $response->assertSee('&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
