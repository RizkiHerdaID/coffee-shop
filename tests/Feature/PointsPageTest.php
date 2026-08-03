<?php

namespace Tests\Feature;

use App\Models\LoyaltyCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_page_renders_without_phone_query(): void
    {
        $response = $this->get(route('points'));

        $response->assertOk();
        $response->assertSee(__('points.phone_label'));
        $response->assertSee(__('points.submit'));
    }

    public function test_points_page_shows_card_balance_for_known_phone(): void
    {
        LoyaltyCard::create(['phone' => '6281234567890', 'stamps' => 13, 'redeemed' => 2]);

        $response = $this->get(route('points', ['phone' => '081234567890']));

        $response->assertOk();
        $response->assertSee(__('points.stamps_label'));
        $response->assertSee(__('points.available_free'));
        $response->assertSee(__('points.redeemed_label'));
        $response->assertSee('13');
        $response->assertSee('dari 10 stempel', false);
    }

    public function test_points_page_shows_not_found_state_for_unknown_phone(): void
    {
        $response = $this->get(route('points', ['phone' => '081200000000']));

        $response->assertOk();
        $response->assertSee(__('points.not_found'));
    }

    public function test_points_progress_uses_configured_stamps_per_reward(): void
    {
        config()->set('loyalty.stamps_per_reward', 8);

        LoyaltyCard::create(['phone' => '6281234567890', 'stamps' => 12, 'redeemed' => 0]);

        $response = $this->get(route('points', ['phone' => '081234567890']));

        $response->assertOk();
        $response->assertSee('dari 8 stempel', false);
        $response->assertDontSee('dari 10 stempel', false);
    }

    public function test_points_page_renders_localized_copy_in_both_locales(): void
    {
        $id = $this->get(route('points'));

        $id->assertOk();
        $id->assertSee(__('points.heading'));

        app()->setLocale('en');

        $en = $this->get(route('points').'?lang=en');

        $en->assertOk();
        $en->assertSee(__('points.heading'));
    }

    public function test_points_page_is_throttled_after_thirty_requests_per_minute(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get(route('points'))->assertOk();
        }

        $this->get(route('points'))->assertStatus(429);
    }

    public function test_points_page_found_and_not_found_shapes_still_render_under_throttle(): void
    {
        LoyaltyCard::create(['phone' => '6281234567890', 'stamps' => 13, 'redeemed' => 2]);

        $found = $this->get(route('points', ['phone' => '081234567890']));

        $found->assertOk();
        $found->assertSee(__('points.stamps_label'));
        $found->assertSee('13');

        $missing = $this->get(route('points', ['phone' => '081200000000']));

        $missing->assertOk();
        $missing->assertSee(__('points.not_found'));
    }

    public function test_points_page_ignores_array_phone_query_parameter(): void
    {
        $response = $this->get(route('points').'?phone[]=081234567890');

        $response->assertOk();
        $response->assertDontSee(__('points.not_found'));
        $response->assertDontSee(__('points.stamps_label'));
    }

    public function test_points_form_keeps_locale_with_hidden_lang_input(): void
    {
        $response = $this->get(route('points').'?lang=en');

        $response->assertOk();
        $response->assertSee('<input type="hidden" name="lang" value="en">', false);
    }
}
