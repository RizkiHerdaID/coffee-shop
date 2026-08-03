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
        LoyaltyCard::create(['phone' => '081234567890', 'stamps' => 13, 'redeemed' => 2]);

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

        LoyaltyCard::create(['phone' => '081234567890', 'stamps' => 12, 'redeemed' => 0]);

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
}
