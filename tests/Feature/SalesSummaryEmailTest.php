<?php

namespace Tests\Feature;

use App\Mail\SalesSummary;
use App\Models\Admin;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SalesSummaryEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'Asia/Jakarta'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<int, array{name: string, price: int, qty: int, subtotal: int}>  $items
     */
    private function createOrder(string $orderNumber, int $total, string $createdAt, array $items = [], string $status = 'paid', ?string $discountType = null, ?int $discountAmount = null): Order
    {
        $admin = Admin::factory()->create();

        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => $orderNumber,
            'total' => $total,
            'status' => $status,
            'discount_type' => $discountType,
            'discount_amount' => $discountAmount,
            'created_by' => $admin->id,
        ]));

        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        foreach ($items as $item) {
            $order->items()->create([
                'name' => $item['name'],
                'price' => $item['price'],
                'qty' => $item['qty'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        return $order;
    }

    public function test_daily_period_aggregates_yesterday_and_queues_email(): void
    {
        $this->createOrder('ORD-D1', 60000, '2026-08-01 09:00:00', [
            ['name' => 'Espresso', 'price' => 20000, 'qty' => 3, 'subtotal' => 60000],
        ], 'paid');
        $this->createOrder('ORD-D2', 55000, '2026-08-01 23:59:59', [
            ['name' => 'Cappuccino', 'price' => 25000, 'qty' => 1, 'subtotal' => 25000],
            ['name' => 'Teh Tarik', 'price' => 15000, 'qty' => 2, 'subtotal' => 30000],
        ], 'paid');
        $this->createOrder('ORD-D3', 100000, '2026-08-02 08:00:00', [], 'paid');
        $this->createOrder('ORD-D4', 50000, '2026-07-31 12:00:00', [], 'paid');

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            Carbon::setLocale(app()->getLocale());
            $subject = __('summary.subject.daily', ['date' => $mail->stats['end']->translatedFormat('d F Y')]);

            return $mail->period === 'daily'
                && $mail->to[0]['address'] === config('summary.recipient')
                && $mail->envelope()->subject === $subject
                && $mail->stats['start']->toDateString() === '2026-08-01'
                && $mail->stats['end']->toDateString() === '2026-08-01'
                && $mail->stats['revenue'] === 115000
                && $mail->stats['orders_count'] === 2
                && $mail->stats['avg_order'] === 57500
                && $mail->stats['top_items'][0] === ['name' => 'Espresso', 'qty' => 3, 'revenue' => 60000]
                && $mail->stats['top_items'][1] === ['name' => 'Teh Tarik', 'qty' => 2, 'revenue' => 30000]
                && $mail->stats['top_items'][2] === ['name' => 'Cappuccino', 'qty' => 1, 'revenue' => 25000];
        });
    }

    public function test_weekly_period_covers_last_seven_days(): void
    {
        $this->createOrder('ORD-W1', 50000, '2026-07-26 00:00:00', [], 'paid');
        $this->createOrder('ORD-W2', 30000, '2026-07-25 23:59:59', [], 'paid');
        $this->createOrder('ORD-W3', 20000, '2026-08-01 23:59:59', [], 'paid');

        $this->artisan('summary:send', ['--period' => 'weekly'])
            ->assertExitCode(0);

        $start = Carbon::now()->subDays(7)->startOfDay();
        $end = Carbon::now()->subDay()->endOfDay();

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) use ($start, $end) {
            return $mail->period === 'weekly'
                && $mail->stats['start']->eq($start)
                && $mail->stats['end']->eq($end)
                && $mail->stats['revenue'] === 70000
                && $mail->stats['orders_count'] === 2
                && $mail->stats['avg_order'] === 35000;
        });
    }

    public function test_date_override_replaces_today_for_period_math(): void
    {
        $this->createOrder('ORD-X1', 45000, '2026-08-04 15:00:00', [], 'paid');
        $this->createOrder('ORD-X2', 15000, '2026-08-05 01:00:00', [], 'paid');

        $this->artisan('summary:send', ['--period' => 'daily', '--date' => '2026-08-05'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->stats['start']->toDateString() === '2026-08-04'
                && $mail->stats['end']->toDateString() === '2026-08-04'
                && $mail->stats['orders_count'] === 1
                && $mail->stats['revenue'] === 45000;
        });
    }

    public function test_recipient_override_sends_to_given_address(): void
    {
        $this->createOrder('ORD-T1', 10000, '2026-08-01 10:00:00', [], 'paid');

        $this->artisan('summary:send', ['--period' => 'daily', '--to' => 'billing@example.com'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->to[0]['address'] === 'billing@example.com';
        });
    }

    public function test_missing_recipient_exits_with_error_code(): void
    {
        config(['summary.recipient' => null]);

        $this->createOrder('ORD-N1', 10000, '2026-08-01 10:00:00', [], 'paid');

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(1);

        Mail::assertNothingQueued();
    }

    public function test_zero_orders_still_queues_email_with_zeros(): void
    {
        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->stats['revenue'] === 0
                && $mail->stats['orders_count'] === 0
                && $mail->stats['avg_order'] === 0
                && $mail->stats['top_items'] === [];
        });
    }

    /**
     * Card 103: the summary counts ONLY paid/served orders — pending,
     * refunded and cancelled orders are excluded from revenue, the count
     * and the top items.
     */
    public function test_revenue_counts_only_paid_and_served_orders(): void
    {
        $this->createOrder('ORD-F1', 60000, '2026-08-01 09:00:00', [], 'paid');
        $this->createOrder('ORD-F2', 50000, '2026-08-01 10:00:00', [], 'served');
        $this->createOrder('ORD-F3', 99999, '2026-08-01 11:00:00', [], 'pending');
        $this->createOrder('ORD-F4', 99999, '2026-08-01 12:00:00', [], 'refunded');
        $this->createOrder('ORD-F5', 99999, '2026-08-01 13:00:00', [], 'cancelled');

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->stats['revenue'] === 110000
                && $mail->stats['orders_count'] === 2
                && $mail->stats['avg_order'] === 55000;
        });
    }

    public function test_revenue_uses_net_totals_for_discounted_orders(): void
    {
        $this->createOrder('ORD-N1', 65000, '2026-08-01 09:00:00', [], 'paid', 'fixed', 10000); // net 55.000
        $this->createOrder('ORD-N2', 20000, '2026-08-01 10:00:00', [], 'paid');                // net 20.000

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->stats['revenue'] === 75000
                && $mail->stats['orders_count'] === 2;
        });
    }

    public function test_top_items_come_from_paid_orders_only(): void
    {
        $this->createOrder('ORD-T1', 30000, '2026-08-01 09:00:00', [
            ['name' => 'Espresso', 'price' => 30000, 'qty' => 1, 'subtotal' => 30000],
        ], 'paid');
        $this->createOrder('ORD-T2', 99000, '2026-08-01 10:00:00', [
            ['name' => 'Cappuccino', 'price' => 99000, 'qty' => 5, 'subtotal' => 99000],
        ], 'pending');

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->stats['top_items'] === [
                ['name' => 'Espresso', 'qty' => 1, 'revenue' => 30000],
            ];
        });
    }

    /**
     * Card 138: order-level discounts are apportioned across the line items
     * (ratio net_total/total) so the top-items revenue column sums exactly to
     * the NET headline revenue — the column is NET, never gross.
     */
    public function test_top_items_apportion_order_discounts_to_match_net_headline(): void
    {
        // 10% percent discount on 95.000 gross → 85.500 net.
        $this->createOrder('ORD-A1', 95000, '2026-08-01 09:00:00', [
            ['name' => 'Espresso', 'price' => 50000, 'qty' => 1, 'subtotal' => 50000],
            ['name' => 'Cappuccino', 'price' => 45000, 'qty' => 1, 'subtotal' => 45000],
        ], 'paid', 'percent', 10);
        // Non-discounted order with the same item — groups under the same name.
        $this->createOrder('ORD-A2', 30000, '2026-08-01 10:00:00', [
            ['name' => 'Espresso', 'price' => 30000, 'qty' => 1, 'subtotal' => 30000],
        ], 'paid');

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            $topRevenue = array_sum(array_column($mail->stats['top_items'], 'revenue'));

            return $mail->stats['revenue'] === 115500
                && $topRevenue === $mail->stats['revenue']
                && $mail->stats['top_items'] === [
                    ['name' => 'Espresso', 'qty' => 2, 'revenue' => 75000],
                    ['name' => 'Cappuccino', 'qty' => 1, 'revenue' => 40500],
                ];
        });
    }

    /**
     * Card 138: items of a fully-discounted order (zero net) contribute 0 to
     * the top-items revenue, while their quantity still counts.
     */
    public function test_fully_discounted_order_items_contribute_zero_revenue(): void
    {
        $this->createOrder('ORD-Z1', 50000, '2026-08-01 09:00:00', [
            ['name' => 'Espresso', 'price' => 50000, 'qty' => 2, 'subtotal' => 50000],
        ], 'paid', 'fixed', 50000); // net 0

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->stats['revenue'] === 0
                && $mail->stats['top_items'] === [
                    ['name' => 'Espresso', 'qty' => 2, 'revenue' => 0],
                ];
        });
    }

    /**
     * Card 138: the percent-discount apportionment rounds half-up per item,
     * so a share landing exactly on the .5 boundary may shift one IDR either
     * way — but the top-items revenue column must still reconcile with the
     * NET headline within that per-item tolerance (±1 per item).
     */
    public function test_percent_apportionment_rounding_stays_within_tolerance_of_headline(): void
    {
        // 50% percent discount on 100.000 gross → 50.000 net; both shares
        // land exactly on the .5 boundary (12.500 / 37.500).
        $this->createOrder('ORD-R1', 100000, '2026-08-01 09:00:00', [
            ['name' => 'Espresso', 'price' => 25000, 'qty' => 1, 'subtotal' => 25000],
            ['name' => 'Cappuccino', 'price' => 75000, 'qty' => 1, 'subtotal' => 75000],
        ], 'paid', 'percent', 50);

        $this->artisan('summary:send', ['--period' => 'daily'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            $topRevenue = array_sum(array_column($mail->stats['top_items'], 'revenue'));
            $byName = collect($mail->stats['top_items'])->keyBy('name');

            return $mail->stats['revenue'] === 50000
                && $mail->stats['orders_count'] === 1
                && abs(($byName['Espresso']['revenue'] ?? 0) - 12500) <= 1000
                && abs(($byName['Cappuccino']['revenue'] ?? 0) - 37500) <= 1000
                // Two items at ±1 each → the column sums within ±2 of the
                // NET headline.
                && abs($topRevenue - $mail->stats['revenue']) <= 2000;
        });
    }
}
