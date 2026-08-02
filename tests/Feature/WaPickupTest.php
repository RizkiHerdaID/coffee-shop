<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Services\WaPickupMessage;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaPickupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
    }

    public function test_format_price_formats_integer_idr(): void
    {
        $this->assertSame('Rp 25.000', WaPickupMessage::formatPrice(25000));
        $this->assertSame('Rp 16.000', WaPickupMessage::formatPrice(16000));
        $this->assertSame('Rp 66.000', WaPickupMessage::formatPrice(66000));
    }

    public function test_build_message_contains_greeting_title_items_and_total(): void
    {
        $message = WaPickupMessage::build([
            ['name' => 'Espresso', 'quantity' => 2, 'price' => 25000],
        ], 'id');

        $this->assertStringContainsString(__('site.wa_message', [], 'id'), $message);
        $this->assertStringContainsString(__('menu.pickup.message_title', ['shop' => config('shop.name')], 'id'), $message);
        $this->assertStringContainsString('Espresso', $message);
        $this->assertStringContainsString('× 2', $message);
        $this->assertStringContainsString('= Rp 50.000', $message);
        $this->assertStringContainsString('Total: Rp 50.000', $message);
        $this->assertStringContainsString(__('menu.pickup.message_pickup', [], 'id'), $message);
        $this->assertStringContainsString(config('shop.name'), $message);
    }

    public function test_build_message_sums_grand_total_across_items(): void
    {
        $message = WaPickupMessage::build([
            ['name' => 'Espresso', 'quantity' => 2, 'price' => 25000],
            ['name' => 'Butter Croissant', 'quantity' => 1, 'price' => 16000],
        ], 'id');

        $this->assertStringContainsString('Espresso × 2 = Rp 50.000', $message);
        $this->assertStringContainsString('Butter Croissant × 1 = Rp 16.000', $message);
        $this->assertStringContainsString('Total: Rp 66.000', $message);
    }

    public function test_build_message_localizes_with_locale_parameter(): void
    {
        $message = WaPickupMessage::build([
            ['name' => 'Espresso', 'quantity' => 2, 'price' => 25000],
        ], 'en');

        $this->assertStringContainsString(__('site.wa_message', [], 'en'), $message);
        $this->assertStringContainsString('Pickup Order — '.config('shop.name'), $message);
        $this->assertStringContainsString('Espresso × 2 = Rp 50.000', $message);
        $this->assertStringContainsString('Total: Rp 50.000', $message);
        $this->assertStringContainsString('Pickup at the store', $message);
    }

    public function test_menu_cards_expose_item_data_for_pickup(): void
    {
        $response = $this->get('/menu');

        $response->assertOk();

        foreach (MenuItem::all() as $item) {
            $response->assertSee('data-item-id="'.$item->id.'"', false);
            $response->assertSee('data-item-name="'.$item->name.'"', false);
            $response->assertSee('data-item-price="'.$item->price.'"', false);
            $response->assertSee('data-available="1"', false);
            $response->assertSee('data-category="'.$item->category.'"', false);
        }
    }

    public function test_available_cards_contain_add_button_with_localized_label(): void
    {
        $espresso = MenuItem::query()->where('name', 'Espresso')->firstOrFail();

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('data-add="'.$espresso->id.'"', false);
        $response->assertSee(__('menu.pickup.add'));
    }

    public function test_sold_out_items_remain_visible_but_are_not_selectable(): void
    {
        $espresso = MenuItem::query()->where('name', 'Espresso')->firstOrFail();
        $croissant = MenuItem::query()->where('name', 'Butter Croissant')->firstOrFail();

        MenuItem::query()->where('name', 'Espresso')->update(['available' => false]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('Espresso');
        $response->assertSee(__('menu.sold_out'));
        $response->assertSee('data-available="0"', false);
        $response->assertDontSee('data-add="'.$espresso->id.'"', false);
        $response->assertSee('data-add="'.$croissant->id.'"', false);
    }

    public function test_pickup_cart_uses_shop_phone_for_wa_link(): void
    {
        $digits = preg_replace('/\D/', '', config('shop.phone'));

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('id="pickup-cart"', false);
        $response->assertSee('data-wa-phone="'.$digits.'"', false);
        $response->assertSee("'https://wa.me/' + phone + '?text='", false);
    }

    public function test_pickup_wa_link_has_localized_label(): void
    {
        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('id="pickup-wa-link"', false);
        $response->assertSee(__('menu.pickup.order'));
    }

    public function test_pickup_i18n_script_contains_localized_message_templates(): void
    {
        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('id="wa-pickup-i18n"', false);
        $response->assertSee('"message_title":', false);
        $response->assertSee('"message_total":', false);
        $response->assertSee('"'.__('menu.pickup.message_total').'"', false);
        $response->assertSee('"'.__('site.wa_message').'"', false);
    }
}
