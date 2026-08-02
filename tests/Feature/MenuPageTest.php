<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_page_renders_with_menu(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertViewHas('menu');
    }

    public function test_menu_page_shows_all_eight_items(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        foreach (['Espresso', 'Cappuccino', 'Flat White', 'V60 Pour Over', 'Cold Brew', 'Matcha Latte', 'Banana Bread', 'Butter Croissant'] as $name) {
            $response->assertSee($name);
        }
    }

    public function test_menu_page_shows_formatted_prices(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertSee('Rp 25.000');
        $response->assertSee('Rp 16.000');
        $response->assertSee('Rp 40.000');
    }

    public function test_menu_page_contains_product_structured_data(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('"priceCurrency": "IDR"', false);
        $response->assertSee('Espresso');
    }

    public function test_menu_page_marks_unavailable_items_as_sold_out_and_not_selectable(): void
    {
        $this->seed(MenuSeeder::class);

        $espresso = MenuItem::where('name', 'Espresso')->firstOrFail();
        $espresso->update(['available' => false]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('Espresso');
        $response->assertSee(__('menu.sold_out'));
        $response->assertSee('data-available="0"', false);
        $response->assertDontSee('data-add="'.$espresso->id.'"', false);
    }

    public function test_seeded_items_have_categories(): void
    {
        $this->seed(MenuSeeder::class);

        $this->assertSame(
            ['coffee', 'non-coffee', 'snack'],
            MenuItem::query()->pluck('category')->unique()->sort()->values()->all(),
        );
    }

    public function test_menu_page_renders_filter_chips_for_present_categories_only(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('data-filter="all"', false);
        $response->assertSee('data-filter="coffee"', false);
        $response->assertSee('data-filter="non-coffee"', false);
        $response->assertSee('data-filter="snack"', false);
        $response->assertDontSee('data-filter="food"', false);
    }

    public function test_menu_page_tags_items_with_category(): void
    {
        $this->seed(MenuSeeder::class);

        $response = $this->get('/menu');

        $response->assertSee('data-category="coffee"', false);
        $response->assertSee('data-category="non-coffee"', false);
        $response->assertSee('data-category="snack"', false);
    }

    public function test_menu_page_renders_badge_chips_for_items_with_badges(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::where('name', 'Matcha Latte')->firstOrFail()->update(['badges' => ['vegan', 'spicy']]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('Matcha Latte');
        $response->assertSee(__('menu.badges.vegan'));
        $response->assertSee(__('menu.badges.spicy'));
    }

    public function test_menu_page_does_not_render_badge_chips_when_none_are_set(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::query()->update(['badges' => null]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertDontSee(__('menu.badges.vegan'));
        $response->assertDontSee(__('menu.badges.spicy'));
        $response->assertDontSee(__('menu.badges.gluten_free'));
        $response->assertDontSee(__('menu.badges.halal'));
    }

    public function test_menu_page_badge_labels_are_localized(): void
    {
        $this->seed(MenuSeeder::class);

        MenuItem::where('name', 'Banana Bread')->firstOrFail()->update(['badges' => ['gluten_free', 'vegan']]);

        $idResponse = $this->get('/menu');

        $idResponse->assertOk();
        $idResponse->assertSee(__('menu.badges.gluten_free'));
        $idResponse->assertSee(__('menu.badges.vegan'));

        $enResponse = $this->get('/menu?lang=en');

        app()->setLocale('en');
        $enResponse->assertOk();
        $enResponse->assertSee(__('menu.badges.gluten_free'));
        $enResponse->assertSee(__('menu.badges.vegan'));
    }
}
