<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\PnlReport;
use App\Filament\Widgets\BestSellersChart;
use App\Filament\Widgets\PeakHoursChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\TodayStats;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use ReflectionClass;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    private int $orderSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    public function test_peak_hours_chart_buckets_revenue_by_day_and_hour(): void
    {
        $this->createOrderAt(25000, 8, 15);
        $this->createOrderAt(15000, 8, 45);
        $this->createOrderAt(3000, 14);

        $data = $this->peakHoursData();

        $this->assertCount(7, $data['datasets']);
        $this->assertCount(24, $data['labels']);
        $this->assertSame('00', $data['labels'][0]);
        $this->assertSame('23', $data['labels'][23]);

        $todayDataset = $data['datasets'][$this->todayIndex()]['data'];

        $this->assertSame(40000, $todayDataset[8]);
        $this->assertSame(3000, $todayDataset[14]);
    }

    public function test_peak_hours_chart_uses_localized_day_labels(): void
    {
        $data = $this->peakHoursData();

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        foreach ($dayKeys as $index => $key) {
            $this->assertSame(__("dashboard.day_labels.$key"), $data['datasets'][$index]['label']);
        }
    }

    public function test_peak_hours_chart_count_filter_counts_orders(): void
    {
        $this->createOrderAt(25000, 8, 15);
        $this->createOrderAt(15000, 8, 45);
        $this->createOrderAt(3000, 14);

        $data = $this->peakHoursData('count');

        $todayDataset = $data['datasets'][$this->todayIndex()]['data'];

        $this->assertSame(2, $todayDataset[8]);
        $this->assertSame(1, $todayDataset[14]);
    }

    public function test_peak_hours_chart_excludes_pending_orders(): void
    {
        $this->createOrderAt(25000, 8, 15);
        $this->createOrderAt(99999, 8, 30, OrderStatus::Pending);

        $data = $this->peakHoursData();

        $todayDataset = $data['datasets'][$this->todayIndex()]['data'];

        $this->assertSame(25000, $todayDataset[8]);
        $this->assertSame(0, $todayDataset[9]);
    }

    public function test_best_sellers_chart_orders_labels_by_descending_revenue(): void
    {
        $this->createOrderWithItem(5000, 'Latte');
        $this->createOrderWithItem(30000, 'Cold Brew');
        $this->createOrderWithItem(15000, 'Cappuccino');

        $data = $this->bestSellersData();

        $this->assertSame(['Cold Brew', 'Cappuccino', 'Latte'], $data['labels']);
        $this->assertSame([30000, 15000, 5000], $data['datasets'][0]['data']);
    }

    public function test_best_sellers_chart_limits_to_ten_items(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->createOrderWithItem(1000, "Item $i");
        }

        $data = $this->bestSellersData();

        $this->assertCount(10, $data['labels']);
        $this->assertCount(10, $data['datasets'][0]['data']);
    }

    public function test_dashboard_renders_with_widgets_registered(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk();
    }

    public function test_peak_hours_heading_is_localized(): void
    {
        app()->setLocale('id');
        $this->assertSame('Jam Sibuk (30 Hari Terakhir)', __('dashboard.peak_hours_heading'));

        app()->setLocale('en');
        $this->assertSame('Peak Hours (Last 30 Days)', __('dashboard.peak_hours_heading'));
    }

    public function test_day_labels_exist_in_both_locales(): void
    {
        $keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        app()->setLocale('id');
        $this->assertSame($keys, array_keys(Lang::get('dashboard.day_labels')));

        app()->setLocale('en');
        $this->assertSame($keys, array_keys(Lang::get('dashboard.day_labels')));
    }

    /**
     * Card 104: TodayStats must use the SAME revenue definition as
     * Shift::salesTotal() — paid/served orders, NET totals.
     */
    public function test_today_stats_uses_net_revenue_and_counts_paid_served_orders(): void
    {
        $this->createOrderAt(65000, 8, 0, OrderStatus::Paid, 'fixed', 10000); // net 55.000
        $this->createOrderAt(20000, 9, 0, OrderStatus::Served);               // net 20.000
        $this->createOrderAt(99999, 10, 0, OrderStatus::Pending);
        $this->createOrderAt(99999, 11, 0, OrderStatus::Refunded);
        $this->createOrderAt(99999, 12, 0, OrderStatus::Cancelled);

        $stats = $this->todayStats();

        $this->assertSame('Rp 75.000', $stats[0]->getValue());
        $this->assertSame(2, $stats[1]->getValue());
    }

    public function test_revenue_chart_uses_net_revenue_for_paid_and_served_orders(): void
    {
        $this->createOrderAt(65000, 8, 0, OrderStatus::Paid, 'fixed', 10000); // net 55.000
        $this->createOrderAt(20000, 9, 0, OrderStatus::Served);               // net 20.000
        $this->createOrderAt(99999, 10, 0, OrderStatus::Pending);

        $data = $this->revenueChartData();

        $todayData = $data['datasets'][0]['data'][13];
        $this->assertSame(75000, $todayData);
    }

    /**
     * Card 104 (core): every revenue surface reports the same number for the
     * same fixture — P&L, TodayStats, RevenueChart and Shift::salesTotal.
     * The discounted order (gross 85.000 → net 75.000) makes any
     * gross-summing or all-status surface diverge.
     */
    public function test_revenue_is_identical_across_pnl_today_stats_revenue_chart_and_shift_sales_total(): void
    {
        $shift = Shift::create([
            'opened_at' => today()->setTime(7, 0),
            'opening_cash' => 100000,
            'admin_id' => $this->admin->id,
        ]);

        $paid = $this->createOrderAt(65000, 8, 0, OrderStatus::Paid, 'fixed', 10000); // net 55.000
        $paid->update(['shift_id' => $shift->id]);
        $served = $this->createOrderAt(20000, 9, 0, OrderStatus::Served); // net 20.000
        $served->update(['shift_id' => $shift->id]);
        $pending = $this->createOrderAt(99999, 10, 0, OrderStatus::Pending);
        $pending->update(['shift_id' => $shift->id]);

        $shiftRevenue = $shift->salesTotal();
        $this->assertSame(75000, $shiftRevenue);

        $pnlRevenue = (new ReflectionClass(PnlReport::class))
            ->getMethod('getReportData')
            ->invoke(new PnlReport, today()->toDateString(), today()->toDateString())['revenue'];
        $this->assertSame($shiftRevenue, $pnlRevenue);

        $stats = $this->todayStats();
        $this->assertSame('Rp '.number_format($shiftRevenue, 0, ',', '.'), $stats[0]->getValue());

        $chart = $this->revenueChartData();
        $this->assertSame($shiftRevenue, $chart['datasets'][0]['data'][13]);
    }

    /**
     * @return array<string, mixed>
     */
    private function peakHoursData(?string $filter = null): array
    {
        $widget = new PeakHoursChart;
        $widget->filter = $filter;

        return (new ReflectionClass(PeakHoursChart::class))
            ->getMethod('getData')
            ->invoke($widget);
    }

    /**
     * @return array<string, mixed>
     */
    private function bestSellersData(): array
    {
        return (new ReflectionClass(BestSellersChart::class))
            ->getMethod('getData')
            ->invoke(new BestSellersChart);
    }

    /**
     * @return array<int, mixed>
     */
    private function todayStats(): array
    {
        return (new ReflectionClass(TodayStats::class))
            ->getMethod('getStats')
            ->invoke(new TodayStats);
    }

    /**
     * @return array<string, mixed>
     */
    private function revenueChartData(): array
    {
        return (new ReflectionClass(RevenueChart::class))
            ->getMethod('getData')
            ->invoke(new RevenueChart);
    }

    private function todayIndex(): int
    {
        return (now()->dayOfWeek + 6) % 7;
    }

    private function createOrderAt(int $total, int $hour, int $minute = 0, OrderStatus $status = OrderStatus::Paid, ?string $discountType = null, ?int $discountAmount = null): Order
    {
        $this->orderSeq++;

        return Order::withoutEvents(function () use ($total, $hour, $minute, $status, $discountType, $discountAmount) {
            $order = new Order([
                'order_number' => 'DASH-'.str_pad((string) $this->orderSeq, 3, '0', STR_PAD_LEFT),
                'status' => $status,
                'total' => $total,
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount,
                'created_by' => $this->admin->id,
            ]);

            $order->created_at = today()->setTime($hour, $minute);
            $order->save();

            return $order;
        });
    }

    private function createOrderWithItem(int $subtotal, string $name): void
    {
        $order = $this->createOrderAt($subtotal, 8);

        $order->items()->create([
            'name' => $name,
            'price' => $subtotal,
            'qty' => 1,
            'subtotal' => $subtotal,
        ]);
    }
}
