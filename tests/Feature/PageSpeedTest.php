<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSpeedTest extends TestCase
{
    use RefreshDatabase;

    private function imageTags(string $html): array
    {
        preg_match_all('/<img[^>]+>/i', $html, $matches);

        return $matches[0];
    }

    private function preloadedImageSrcs(string $html): array
    {
        preg_match_all('/<link[^>]+rel="preload"[^>]*>/i', $html, $links);

        $srcs = [];

        foreach ($links[0] as $link) {
            if (! preg_match('/\bas="image"/i', $link)) {
                continue;
            }

            if (preg_match('/href="([^"]+)"/i', $link, $href)) {
                $srcs[] = $href[1];
            }
        }

        return $srcs;
    }

    private function lcpPreloadHref(string $html): ?string
    {
        preg_match('/<link[^>]+rel="preload"[^>]*fetchpriority="high"[^>]*>/i', $html, $match);

        if (! $match) {
            return null;
        }

        preg_match('/href="([^"]+)"/i', $match[0], $href);

        return $href[1] ?? null;
    }

    private function assertPreloadedAssetExists(?string $href): void
    {
        $this->assertNotNull($href, 'Preload link with fetchpriority not found');

        $path = parse_url($href, PHP_URL_PATH);

        $this->assertTrue(str_starts_with((string) $path, '/build/'), "Preload href outside build dir: {$href}");
        $this->assertFileExists(public_path(substr((string) $path, 1)), "Preloaded asset missing: {$href}");
    }

    public function test_home_page_preloads_lcp_font_with_fetchpriority(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="preload"', false);
        $response->assertSee('as="font"', false);
        $response->assertSee('fetchpriority="high"', false);

        preg_match('/<link[^>]+rel="preload"[^>]*fetchpriority="high"[^>]*>/i', $response->getContent(), $match);

        $this->assertNotEmpty($match, 'Preload link with fetchpriority not found');
        $this->assertMatchesRegularExpression('/\bas="font"/i', $match[0]);
        $this->assertMatchesRegularExpression('/\bhref="[^"]+\.woff2"/i', $match[0]);

        $this->assertPreloadedAssetExists($this->lcpPreloadHref($response->getContent()));
    }

    public function test_preloaded_asset_exists_and_home_images_are_lazy(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::all()->each(fn (MenuItem $item) => $item->update(['photo' => 'items/drink.jpg']));

        $html = $this->get('/')->getContent();

        $this->assertPreloadedAssetExists($this->lcpPreloadHref($html));

        $images = array_values(array_filter(
            $this->imageTags($html),
            fn (string $img): bool => ! str_contains((string) $this->imgSrc($img), '/build/'),
        ));

        $this->assertNotEmpty($images);

        foreach ($images as $img) {
            $this->assertStringContainsString('loading="lazy"', $img);
        }
    }

    public function test_menu_images_are_lazy_with_decoding_and_dimensions(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::all()->each(fn (MenuItem $item) => $item->update(['photo' => 'items/drink.jpg']));

        $response = $this->get('/menu');

        $response->assertOk();
        $images = $this->imageTags($response->getContent());

        $this->assertNotEmpty($images);

        foreach ($images as $img) {
            $this->assertStringContainsString('loading="lazy"', $img);
            $this->assertStringContainsString('decoding="async"', $img);
            $this->assertMatchesRegularExpression('/\swidth="\d+"/', $img);
            $this->assertMatchesRegularExpression('/\sheight="\d+"/', $img);
        }
    }

    public function test_home_below_fold_images_are_lazy_with_decoding_and_dimensions(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::all()->each(fn (MenuItem $item) => $item->update(['photo' => 'items/drink.jpg']));

        $html = $this->get('/')->getContent();
        $preloaded = $this->preloadedImageSrcs($html);
        $belowFold = array_values(array_filter(
            $this->imageTags($html),
            fn (string $img): bool => ! str_contains((string) $this->imgSrc($img), '/build/')
                && ! in_array($this->imgSrc($img), $preloaded, true),
        ));

        $this->assertNotEmpty($belowFold);

        foreach ($belowFold as $img) {
            $this->assertStringContainsString('loading="lazy"', $img);
            $this->assertStringContainsString('decoding="async"', $img);
            $this->assertMatchesRegularExpression('/\swidth="\d+"/', $img);
            $this->assertMatchesRegularExpression('/\sheight="\d+"/', $img);
        }
    }

    public function test_fonts_use_font_display_swap(): void
    {
        $html = $this->get('/')->getContent();

        if (str_contains($html, 'fonts.googleapis.com')) {
            $this->assertStringContainsString('display=swap', $html);
        }

        $fontCss = glob(public_path('build/assets/fonts-*.css'));

        if ($fontCss) {
            $this->assertStringContainsString('font-display: swap', (string) file_get_contents($fontCss[0]));
        }

        $this->assertTrue(
            str_contains($html, 'fonts.googleapis.com') || ! empty($fontCss),
            'No font loading mechanism detected',
        );
    }

    public function test_layout_head_has_no_third_party_render_blocking_scripts(): void
    {
        $html = $this->get('/')->getContent();

        preg_match('/<head[^>]*>(.*?)<\/head>/s', $html, $match);

        $this->assertNotEmpty($match, 'Layout head not found');

        preg_match_all('/<script[^>]+src\s*=\s*(["\'])(.*?)\1/i', $match[1], $scripts);

        $appHost = parse_url(url('/'), PHP_URL_HOST);

        foreach ($scripts[2] as $src) {
            if (! preg_match('#^https?://#i', $src)) {
                continue;
            }

            $this->assertSame($appHost, parse_url($src, PHP_URL_HOST), "Third-party script in head: {$src}");
        }
    }

    private function imgSrc(string $img): ?string
    {
        preg_match('/src="([^"]+)"/i', $img, $match);

        return $match[1] ?? null;
    }
}
