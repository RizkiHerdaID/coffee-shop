<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Widgets\BestSellersChart;
use App\Filament\Widgets\PeakHoursChart;
use App\Models\Admin;
use App\Models\Order;
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

    private function todayIndex(): int
    {
        return (now()->dayOfWeek + 6) % 7;
    }

    private function createOrderAt(int $total, int $hour, int $minute = 0, OrderStatus $status = OrderStatus::Paid): Order
    {
        $this->orderSeq++;

        return Order::withoutEvents(function () use ($total, $hour, $minute, $status) {
            $order = new Order([
                'order_number' => 'DASH-'.str_pad((string) $this->orderSeq, 3, '0', STR_PAD_LEFT),
                'status' => $status,
                'total' => $total,
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
