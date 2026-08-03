<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\LoyaltyCards\Pages\ListLoyaltyCards;
use App\Models\Admin;
use App\Models\LoyaltyCard;
use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Loyalty/stamps program (Vikunja 65).
 *
 * Paid orders keyed by customer_phone credit a stamp via the Order
 * observer (App\Observers\OrderObserver — fires on the pending → paid
 * transition, so pending orders, refunds and re-saves never re-credit).
 * Stamps accumulate per phone; every full block of 10 stamps can be
 * redeemed for one free drink (LoyaltyCard::redeem() subtracts 10 stamps
 * and increments `redeemed`). The public "Cek Poin" page
 * (PageController::points(), GET /cek-poin) shows the stamps and free
 * drinks available for a queried phone. Orders without a customer_phone
 * never credit a stamp.
 *
 * Since Vikunja 111, the phone is normalized to the canonical 62-prefixed
 * form before every lookup or mutation, so the `loyalty_cards.phone`
 * column stores e.g. "6281234567890" regardless of the input format, and
 * the first-create race for a brand-new phone degrades to a single row
 * (insert-or-ignore + fetch-and-increment under the unique index).
 */
class LoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '6281234567890';

    private const OTHER_PHONE = '6281298765432';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
    }

    /**
     * Create a fully paid order (pending → paid via markPaidIfCovered,
     * the same transition the cashier performs).
     */
    private function createPaidOrder(?string $phone = '081234567890', int $total = 20000, ?Admin $admin = null): Order
    {
        $admin ??= Admin::factory()->create();

        $order = Order::create([
            'order_number' => 'ORD-'.fake()->unique()->numberBetween(100000, 999999),
            'customer_phone' => $phone,
            'total' => $total,
            'created_by' => $admin->id,
        ])->refresh();

        $order->payments()->create([
            'method' => PaymentMethod::Cash,
            'amount' => $total,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $order->markPaidIfCovered();

        return $order->refresh();
    }

    public function test_paid_order_credits_a_stamp_keyed_by_customer_phone(): void
    {
        $this->createPaidOrder('081234567890');

        $card = LoyaltyCard::findByPhone('081234567890');

        $this->assertNotNull($card);
        $this->assertSame(1, $card->stamps);
        $this->assertSame(0, $card->redeemed);
        $this->assertSame(0, $card->freeDrinksAvailable());
    }

    public function test_stamps_are_keyed_per_phone_not_shared(): void
    {
        $this->createPaidOrder('081234567890');
        $this->createPaidOrder('081298765432');

        $this->assertSame(1, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);
        $this->assertSame(1, LoyaltyCard::where('phone', self::OTHER_PHONE)->firstOrFail()->stamps);
        $this->assertSame(2, LoyaltyCard::count());
    }

    public function test_multiple_paid_orders_accumulate_stamps(): void
    {
        foreach (range(1, 3) as $i) {
            $this->createPaidOrder('081234567890');
        }

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();

        $this->assertSame(3, $card->stamps);
        $this->assertSame(0, $card->redeemed);
        $this->assertSame(0, $card->freeDrinksAvailable());
    }

    public function test_ninth_order_holds_eight_stamps_and_no_free_drink_yet(): void
    {
        foreach (range(1, 9) as $i) {
            $this->createPaidOrder('081234567890');
        }

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();

        $this->assertSame(9, $card->stamps);
        $this->assertSame(0, $card->freeDrinksAvailable());
        $this->assertSame(1, $card->remainingToNextFreeDrink());
    }

    public function test_tenth_paid_order_makes_a_free_drink_available(): void
    {
        foreach (range(1, 10) as $i) {
            $this->createPaidOrder('081234567890');
        }

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();

        $this->assertSame(10, $card->stamps);
        $this->assertSame(0, $card->redeemed);
        $this->assertSame(1, $card->freeDrinksAvailable());
        $this->assertSame(10, $card->remainingToNextFreeDrink());
    }

    public function test_orders_beyond_the_tenth_accumulate_into_the_next_block(): void
    {
        foreach (range(1, 12) as $i) {
            $this->createPaidOrder('081234567890');
        }

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();

        $this->assertSame(12, $card->stamps);
        $this->assertSame(1, $card->freeDrinksAvailable());
        $this->assertSame(8, $card->remainingToNextFreeDrink());
    }

    public function test_redeem_returns_false_when_balance_is_below_ten_stamps(): void
    {
        LoyaltyCard::credit('081234567890', 9);

        $this->assertFalse(LoyaltyCard::redeem('081234567890'));

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(9, $card->stamps);
        $this->assertSame(0, $card->redeemed);
    }

    public function test_redeem_consumes_ten_stamps_and_increments_redeemed(): void
    {
        LoyaltyCard::credit('081234567890', 10);

        $this->assertTrue(LoyaltyCard::redeem('081234567890'));

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(0, $card->stamps);
        $this->assertSame(1, $card->redeemed);
        $this->assertSame(0, $card->freeDrinksAvailable());
        $this->assertSame(10, $card->remainingToNextFreeDrink());
    }

    public function test_redeem_returns_false_when_none_left(): void
    {
        LoyaltyCard::credit('081234567890', 20);

        $this->assertTrue(LoyaltyCard::redeem('081234567890'));
        $this->assertTrue(LoyaltyCard::redeem('081234567890'));
        $this->assertFalse(LoyaltyCard::redeem('081234567890'));

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(0, $card->stamps);
        $this->assertSame(2, $card->redeemed);
        $this->assertSame(0, $card->freeDrinksAvailable());
    }

    public function test_credit_upserts_a_single_row_per_phone(): void
    {
        LoyaltyCard::credit('081234567890');
        LoyaltyCard::credit('081234567890');
        LoyaltyCard::credit('081298765432');

        $this->assertSame(2, LoyaltyCard::count());
        $this->assertSame(2, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);
        $this->assertSame(1, LoyaltyCard::where('phone', self::OTHER_PHONE)->firstOrFail()->stamps);
    }

    public function test_adjust_stamps_applies_delta_and_clamps_at_zero(): void
    {
        LoyaltyCard::credit('081234567890', 4);

        LoyaltyCard::adjustStamps('081234567890', 2);
        $this->assertSame(6, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);

        LoyaltyCard::adjustStamps('081234567890', -6);
        $this->assertSame(0, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);

        LoyaltyCard::adjustStamps('081298765432', 3);
        $this->assertSame(3, LoyaltyCard::where('phone', self::OTHER_PHONE)->firstOrFail()->stamps);
    }

    public function test_order_without_customer_phone_gets_no_stamp(): void
    {
        $this->createPaidOrder(null);

        $this->assertDatabaseCount('loyalty_cards', 0);
    }

    public function test_pending_order_does_not_credit_a_stamp_until_paid(): void
    {
        $admin = Admin::factory()->create();

        Order::create([
            'order_number' => 'ORD-777777',
            'customer_phone' => '081234567890',
            'total' => 20000,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseCount('loyalty_cards', 0);
    }

    public function test_editing_a_paid_order_does_not_recredit_a_stamp(): void
    {
        $this->createPaidOrder('081234567890');

        $order = Order::where('customer_phone', '081234567890')->firstOrFail();
        $order->update(['notes' => 'edit after payment']);

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(1, $card->stamps);
    }

    // ---------------------------------------------------------------------
    // Phone normalization (Vikunja 111): any Indonesian format converges
    // to the same canonical key, so POS, admin and public lookups always
    // land on the same card.
    // ---------------------------------------------------------------------

    public function test_credit_converges_all_phone_formats_to_a_single_card(): void
    {
        LoyaltyCard::credit('0812-3456-7890');
        LoyaltyCard::credit('+6281234567890');
        LoyaltyCard::credit('08 1234 5678 90');

        $this->assertSame(1, LoyaltyCard::count());
        $this->assertSame(3, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);
    }

    public function test_find_by_phone_matches_any_phone_format(): void
    {
        LoyaltyCard::credit('0812-3456-7890', 3);

        $this->assertSame(3, LoyaltyCard::findByPhone('+6281234567890')?->stamps);
        $this->assertSame(3, LoyaltyCard::findByPhone('081234567890')?->stamps);
        $this->assertNull(LoyaltyCard::findByPhone('081200000000'));
    }

    public function test_redeem_with_a_formatted_phone_uses_the_same_card(): void
    {
        LoyaltyCard::credit('081234567890', 10);

        $this->assertTrue(LoyaltyCard::redeem('+62 812-3456-7890'));

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(0, $card->stamps);
        $this->assertSame(1, $card->redeemed);
    }

    // ---------------------------------------------------------------------
    // First-create race (Vikunja 115, part): two concurrent first credits
    // for a brand-new phone must collapse into a single card. The unique
    // index on phone + insert-or-ignore makes the loser of the race
    // degrade to fetch-and-increment.
    // ---------------------------------------------------------------------

    public function test_credit_after_a_concurrent_first_create_lands_on_the_existing_card(): void
    {
        // Simulate the loser of the race: a concurrent first-credit has
        // already inserted the row, so this credit's insert-or-ignore is a
        // no-op and it must fetch-and-increment instead of duplicating.
        LoyaltyCard::create(['phone' => self::PHONE, 'stamps' => 0, 'redeemed' => 0]);

        $card = LoyaltyCard::credit('0812-3456-7890');

        $this->assertSame(1, LoyaltyCard::count());
        $this->assertSame(1, $card->stamps);
        $this->assertSame(self::PHONE, $card->phone);
    }

    public function test_unique_phone_index_rejects_duplicate_rows(): void
    {
        LoyaltyCard::credit('081234567890');

        $this->expectException(QueryException::class);

        LoyaltyCard::create(['phone' => self::PHONE, 'stamps' => 1, 'redeemed' => 0]);
    }

    // ---------------------------------------------------------------------
    // Configurable reward threshold (worktree-4 parity): the stamps needed
    // per free drink come from config('loyalty.stamps_per_reward') (env
    // LOYALTY_STAMPS_PER_REWARD, default 10), so the threshold can change
    // without code edits. Default-value tests above keep asserting 10.
    // ---------------------------------------------------------------------

    public function test_free_drinks_remaining_and_redeem_honor_configured_threshold(): void
    {
        $original = config('loyalty.stamps_per_reward');

        try {
            config()->set('loyalty.stamps_per_reward', 8);

            LoyaltyCard::credit('081234567890', 7);
            $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
            $this->assertSame(0, $card->freeDrinksAvailable());
            $this->assertSame(1, $card->remainingToNextFreeDrink());

            LoyaltyCard::credit('081234567890', 1);
            $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
            $this->assertSame(1, $card->freeDrinksAvailable());
            $this->assertSame(8, $card->remainingToNextFreeDrink());

            $this->assertTrue(LoyaltyCard::redeem('081234567890'));

            $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
            $this->assertSame(0, $card->stamps);
            $this->assertSame(1, $card->redeemed);
            $this->assertSame(0, $card->freeDrinksAvailable());
            $this->assertSame(8, $card->remainingToNextFreeDrink());

            LoyaltyCard::credit('081234567890', 16);
            $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
            $this->assertSame(2, $card->freeDrinksAvailable());
            $this->assertSame(8, $card->remainingToNextFreeDrink());

            $this->assertTrue(LoyaltyCard::redeem('081234567890'));

            $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
            $this->assertSame(8, $card->stamps);
            $this->assertSame(2, $card->redeemed);
            $this->assertSame(1, $card->freeDrinksAvailable());

            $this->assertTrue(LoyaltyCard::redeem('081234567890'));

            $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
            $this->assertSame(0, $card->stamps);
            $this->assertSame(3, $card->redeemed);
            $this->assertSame(0, $card->freeDrinksAvailable());

            $this->assertFalse(LoyaltyCard::redeem('081234567890'));
        } finally {
            config()->set('loyalty.stamps_per_reward', $original);
        }
    }

    public function test_default_threshold_is_ten_stamps(): void
    {
        $this->assertSame(10, (int) config('loyalty.stamps_per_reward'));
    }

    // ---------------------------------------------------------------------
    // Public "Cek Poin" page.
    // ---------------------------------------------------------------------

    public function test_points_page_renders_successfully(): void
    {
        $this->get(url('/cek-poin'))
            ->assertOk();
    }

    public function test_points_page_has_phone_form_field(): void
    {
        $this->get(url('/cek-poin'))
            ->assertOk()
            ->assertSee('name="phone"', false);
    }

    public function test_points_page_shows_stamps_and_free_drinks_for_known_phone(): void
    {
        LoyaltyCard::credit('081234567890', 4);

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(4, $card->stamps);
        $this->assertSame(0, $card->freeDrinksAvailable());

        // The raw format is queried on purpose: the controller normalizes
        // via LoyaltyCard::findByPhone(), so any format finds the card.
        $this->get(url('/cek-poin').'?phone=081234567890')
            ->assertOk()
            ->assertSee(__('points.stamps_label'))
            ->assertSee(__('points.available_free'))
            ->assertSee('4', false);
    }

    public function test_points_page_keeps_the_queried_phone_in_the_input(): void
    {
        LoyaltyCard::credit('081234567890', 2);

        $this->get(url('/cek-poin').'?phone=081234567890')
            ->assertOk()
            ->assertSee('value="081234567890"', false);
    }

    public function test_points_page_unknown_phone_shows_not_found_state(): void
    {
        $this->get(url('/cek-poin').'?phone=081200000000')
            ->assertOk()
            ->assertSee(__('points.not_found'));
    }

    // ---------------------------------------------------------------------
    // Exactly-once credit per order lifecycle (Vikunja 117): refunded →
    // paid edits and status round-trips must never re-credit a stamp, and
    // the direct edit path credits like the POS payment path.
    // ---------------------------------------------------------------------

    public function test_refunded_then_repaid_order_credits_exactly_once(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $order = $this->createPaidOrder('081234567890', 20000, $admin);
        $this->assertSame(1, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);

        $order->refund($order->paid_total);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);

        // Admin corrects the refund back to paid: must NOT re-credit.
        $order->update(['status' => OrderStatus::Paid]);

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(1, $card->stamps);
        $this->assertSame(0, $card->redeemed);
    }

    public function test_status_roundtrip_away_from_and_back_to_paid_never_recredits(): void
    {
        $admin = Admin::factory()->create();

        $order = $this->createPaidOrder('081234567890', 20000, $admin);
        $this->assertSame(1, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);

        $order->update(['status' => OrderStatus::Served]);
        $order->update(['status' => OrderStatus::Pending]);
        $order->update(['status' => OrderStatus::Paid]);

        $this->assertSame(1, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);
    }

    public function test_direct_status_edit_to_paid_credits_a_stamp_exactly_once(): void
    {
        $admin = Admin::factory()->create();

        $order = Order::create([
            'order_number' => 'ORD-EDIT-PAID',
            'customer_phone' => '081234567890',
            'status' => OrderStatus::Pending,
            'total' => 20000,
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseCount('loyalty_cards', 0);

        $order->update(['status' => OrderStatus::Paid]);
        $this->assertSame(1, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);

        $order->update(['notes' => 're-save after payment']);
        $this->assertSame(1, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);
    }

    // ---------------------------------------------------------------------
    // Atomic balance mutations (Vikunja 115, part): read-modify-write is
    // serialized, so sequential/nested operations never lose stamps and a
    // redeem can never drive the balance negative.
    // ---------------------------------------------------------------------

    public function test_two_sequential_credits_inside_a_transaction_preserve_the_total(): void
    {
        DB::beginTransaction();

        LoyaltyCard::credit('081234567890', 5);
        LoyaltyCard::credit('081234567890', 5);

        $this->assertSame(10, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);

        DB::commit();

        $this->assertSame(10, LoyaltyCard::where('phone', self::PHONE)->firstOrFail()->stamps);
    }

    public function test_double_redeem_on_exactly_ten_stamps_allows_only_one(): void
    {
        LoyaltyCard::credit('081234567890', 10);

        $this->assertTrue(LoyaltyCard::redeem('081234567890'));
        $this->assertFalse(LoyaltyCard::redeem('081234567890'));

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(0, $card->stamps);
        $this->assertSame(1, $card->redeemed);
    }

    // ---------------------------------------------------------------------
    // Numeric input masks (Vikunja 128): the grant/adjust stamp forms accept
    // Indonesian-formatted quantities and persist the raw integer.
    // ---------------------------------------------------------------------

    public function test_grant_stamps_action_accepts_formatted_quantity_and_stores_raw_integer(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(ListLoyaltyCards::class)
            ->callAction('grantStamps', data: [
                'phone' => '081234567890',
                'qty' => '25.000',
            ])
            ->assertHasNoActionErrors();

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();
        $this->assertSame(25000, $card->stamps);
    }

    public function test_adjust_stamps_action_accepts_formatted_quantity_and_stores_raw_integer(): void
    {
        $admin = Admin::factory()->create();
        LoyaltyCard::credit('081234567890', 5);

        $card = LoyaltyCard::where('phone', self::PHONE)->firstOrFail();

        Livewire::actingAs($admin, 'admin')
            ->test(ListLoyaltyCards::class)
            ->callTableAction('adjustStamps', $card, data: [
                'qty' => '1.500',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1505, $card->fresh()->stamps);
    }
}
