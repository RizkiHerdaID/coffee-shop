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

    private function createOrder(string $orderNumber, int $total, string $createdAt, array $items = []): Order
    {
        $admin = Admin::factory()->create();

        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => $orderNumber,
            'total' => $total,
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
        ]);
        $this->createOrder('ORD-D2', 55000, '2026-08-01 23:59:59', [
            ['name' => 'Cappuccino', 'price' => 25000, 'qty' => 1, 'subtotal' => 25000],
            ['name' => 'Teh Tarik', 'price' => 15000, 'qty' => 2, 'subtotal' => 30000],
        ]);
        $this->createOrder('ORD-D3', 100000, '2026-08-02 08:00:00');
        $this->createOrder('ORD-D4', 50000, '2026-07-31 12:00:00');

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
        $this->createOrder('ORD-W1', 50000, '2026-07-26 00:00:00');
        $this->createOrder('ORD-W2', 30000, '2026-07-25 23:59:59');
        $this->createOrder('ORD-W3', 20000, '2026-08-01 23:59:59');

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
        $this->createOrder('ORD-X1', 45000, '2026-08-04 15:00:00');
        $this->createOrder('ORD-X2', 15000, '2026-08-05 01:00:00');

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
        $this->createOrder('ORD-T1', 10000, '2026-08-01 10:00:00');

        $this->artisan('summary:send', ['--period' => 'daily', '--to' => 'billing@example.com'])
            ->assertExitCode(0);

        Mail::assertQueued(SalesSummary::class, function (SalesSummary $mail) {
            return $mail->to[0]['address'] === 'billing@example.com';
        });
    }

    public function test_missing_recipient_exits_with_error_code(): void
    {
        config(['summary.recipient' => null]);

        $this->createOrder('ORD-N1', 10000, '2026-08-01 10:00:00');

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
}
