<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Widgets\DemandForecastWidget;
use App\Models\Admin;
use App\Models\Order;
use App\Services\DemandForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use ReflectionClass;
use Tests\TestCase;

class DemandForecastTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    private int $orderSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    public function test_service_exposes_default_window_of_three_months(): void
    {
        $this->assertSame(3, DemandForecastService::DEFAULT_MONTHS);
    }

    public function test_weekday_aggregate_counts_and_sums_revenue_by_weekday(): void
    {
        $monday = today()->startOfWeek();
        $this->createOrderAt($monday, 25000);
        $this->createOrderAt($monday->copy(), 10000);
        $this->createOrderAt($monday->copy()->addDay(1), 15000);
        $this->createOrderAt($monday->copy()->addDays(3), 3000);
        $this->createOrderAt($monday->copy()->addDays(6), 50000);

        $aggregate = (new DemandForecastService)->weekdayAggregate();

        $expected = [
            'count' => ['mon' => 2, 'tue' => 1, 'wed' => 0, 'thu' => 1, 'fri' => 0, 'sat' => 0, 'sun' => 1],
            'revenue' => ['mon' => 35000, 'tue' => 15000, 'wed' => 0, 'thu' => 3000, 'fri' => 0, 'sat' => 0, 'sun' => 50000],
        ];

        $this->assertSame($expected, $aggregate);
    }

    public function test_weekday_aggregate_respects_custom_month_window(): void
    {
        $service = new DemandForecastService;

        $this->createOrderAt(today()->startOfMonth(), 10000);
        $this->createOrderAt(today()->startOfMonth()->subMonth()->addDays(5), 20000);

        $oneMonth = $service->weekdayAggregate(1);

        $this->assertSame(1, array_sum($oneMonth['count']));
        $this->assertSame(10000, array_sum($oneMonth['revenue']));

        $twoMonths = $service->weekdayAggregate(2);

        $this->assertSame(2, array_sum($twoMonths['count']));
        $this->assertSame(30000, array_sum($twoMonths['revenue']));
    }

    public function test_month_aggregate_returns_exact_zero_filled_window_oldest_first(): void
    {
        $this->createOrderAt(today()->startOfMonth()->addDays(2), 10000);
        $this->createOrderAt(today()->startOfMonth()->subMonth()->addDays(3), 20000);
        $this->createOrderAt(today()->startOfMonth()->subMonths(2)->addDays(4), 30000);

        $result = (new DemandForecastService)->monthAggregate();

        $keys = array_keys($result);

        $this->assertCount(3, $keys);
        $this->assertSame(now()->subMonths(2)->startOfMonth()->format('Y-m'), $keys[0]);
        $this->assertSame(now()->subMonths(1)->startOfMonth()->format('Y-m'), $keys[1]);
        $this->assertSame(now()->startOfMonth()->format('Y-m'), $keys[2]);
        $this->assertSame(['count' => 1, 'revenue' => 30000], $result[$keys[0]]);
        $this->assertSame(['count' => 1, 'revenue' => 20000], $result[$keys[1]]);
        $this->assertSame(['count' => 1, 'revenue' => 10000], $result[$keys[2]]);
    }

    public function test_month_aggregate_respects_custom_month_window(): void
    {
        $service = new DemandForecastService;

        $this->createOrderAt(today()->startOfMonth()->addDays(2), 10000);
        $this->createOrderAt(today()->startOfMonth()->subMonth()->addDays(3), 20000);
        $this->createOrderAt(today()->startOfMonth()->subMonths(5)->addDays(4), 40000);

        $oneMonth = $service->monthAggregate(1);

        $this->assertCount(1, $oneMonth);
        $this->assertSame(now()->format('Y-m'), array_key_first($oneMonth));
        $this->assertSame(['count' => 1, 'revenue' => 10000], $oneMonth[now()->format('Y-m')]);

        $sixMonths = $service->monthAggregate(6);

        $this->assertCount(6, $sixMonths);
        $this->assertSame(now()->subMonths(5)->startOfMonth()->format('Y-m'), array_key_first($sixMonths));
        $this->assertSame(now()->format('Y-m'), array_key_last($sixMonths));
        $this->assertSame(['count' => 1, 'revenue' => 40000], $sixMonths[now()->subMonths(5)->startOfMonth()->format('Y-m')]);
    }

    public function test_aggregates_exclude_pending_refunded_and_cancelled_orders(): void
    {
        $this->createOrderAt(today()->startOfMonth(), 1000, OrderStatus::Paid);
        $this->createOrderAt(today()->startOfMonth()->addDays(1), 99999, OrderStatus::Pending);
        $this->createOrderAt(today()->startOfMonth()->addDays(2), 99999, OrderStatus::Refunded);
        $this->createOrderAt(today()->startOfMonth()->addDays(3), 99999, OrderStatus::Cancelled);
        $this->createOrderAt(today()->startOfMonth()->addDays(4), 5000, OrderStatus::Served);

        $weekday = (new DemandForecastService)->weekdayAggregate();

        $this->assertSame(2, array_sum($weekday['count']));
        $this->assertSame(6000, array_sum($weekday['revenue']));

        $month = (new DemandForecastService)->monthAggregate();

        $this->assertSame(['count' => 2, 'revenue' => 6000], $month[now()->format('Y-m')]);
    }

    public function test_empty_data_returns_zero_filled_safe_defaults(): void
    {
        $service = new DemandForecastService;

        $weekday = $service->weekdayAggregate();

        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], array_keys($weekday['count']));
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], array_values($weekday['count']));
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], array_values($weekday['revenue']));

        $month = $service->monthAggregate();

        $this->assertCount(3, $month);
        $this->assertSame(now()->format('Y-m'), array_key_last($month));

        foreach ($month as $values) {
            $this->assertSame(['count' => 0, 'revenue' => 0], $values);
        }

        $this->assertInstanceOf(Collection::class, $service->paidOrders());
        $this->assertCount(0, $service->paidOrders());
    }

    public function test_paid_orders_returns_only_non_excluded_statuses_in_window_descending(): void
    {
        $service = new DemandForecastService;

        $this->createOrderAt(today()->startOfMonth()->addDays(2), 1000);
        $this->createOrderAt(today()->startOfMonth()->subMonth()->addDays(2), 2000);
        $this->createOrderAt(today()->startOfMonth()->subMonths(3)->addDays(1), 3000);
        $this->createOrderAt(today()->startOfMonth()->addDays(5), 4000, OrderStatus::Pending);

        $orders = $service->paidOrders();

        $this->assertCount(2, $orders);
        $this->assertSame(1000, $orders->first()->total);
        $this->assertSame(2000, $orders->last()->total);
        $this->assertTrue($orders->first()->created_at->gt($orders->last()->created_at));

        $this->assertCount(1, $service->paidOrders(1));
        $this->assertCount(2, $service->paidOrders(2));
        $this->assertCount(3, $service->paidOrders(4));

        $attributes = array_keys($orders->first()->getAttributes());

        $this->assertContains('id', $attributes);
        $this->assertContains('created_at', $attributes);
        $this->assertContains('total', $attributes);
    }

    public function test_widget_weekday_revenue_mode_via_reflection(): void
    {
        $monday = today()->startOfWeek();
        $this->createOrderAt($monday, 25000);
        $this->createOrderAt($monday->copy()->addDay(1), 15000);
        $this->createOrderAt($monday->copy()->addDays(3), 3000);

        $data = $this->widgetData(null);

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $this->assertSame(array_map(fn (string $key): string => __("dashboard.day_labels.$key"), $dayKeys), $data['labels']);
        $this->assertSame([25000, 15000, 0, 3000, 0, 0, 0], $data['datasets'][0]['data']);
    }

    public function test_widget_weekday_count_mode_via_reflection(): void
    {
        $monday = today()->startOfWeek();
        $this->createOrderAt($monday, 25000);
        $this->createOrderAt($monday->copy(), 15000);
        $this->createOrderAt($monday->copy()->addDays(3), 3000);

        $data = $this->widgetData('weekday_count');

        $this->assertSame([2, 0, 0, 1, 0, 0, 0], $data['datasets'][0]['data']);
    }

    public function test_widget_month_revenue_mode_via_reflection(): void
    {
        $this->createOrderAt(today()->startOfMonth()->addDays(2), 10000);
        $this->createOrderAt(today()->startOfMonth()->subMonth()->addDays(2), 20000);

        $data = $this->widgetData('month_revenue');

        // The widget formats with the app locale explicitly (never via the
        // global Carbon locale), so mirror that here.
        $expectedLabels = [];
        for ($i = 2; $i >= 0; $i--) {
            $key = now()->subMonths($i)->startOfMonth()->format('Y-m');
            $expectedLabels[] = Carbon::createFromFormat('Y-m', $key)
                ->locale(app()->getLocale())
                ->translatedFormat('M Y');
        }

        $this->assertSame($expectedLabels, $data['labels']);
        $this->assertSame([0, 20000, 10000], $data['datasets'][0]['data']);
    }

    public function test_widget_month_count_mode_via_reflection(): void
    {
        $this->createOrderAt(today()->startOfMonth()->addDays(2), 10000);
        $this->createOrderAt(today()->startOfMonth()->subMonth()->addDays(2), 20000);

        $data = $this->widgetData('month_count');

        $this->assertSame([0, 1, 1], $data['datasets'][0]['data']);
    }

    public function test_widget_returns_zero_data_for_empty_store(): void
    {
        $weekday = $this->widgetData('weekday_revenue');

        $this->assertCount(7, $weekday['datasets'][0]['data']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $weekday['datasets'][0]['data']);

        $month = $this->widgetData('month_revenue');

        $this->assertCount(3, $month['datasets'][0]['data']);
        $this->assertSame([0, 0, 0], $month['datasets'][0]['data']);
    }

    public function test_widget_filters_expose_all_four_modes(): void
    {
        $filters = (new ReflectionClass(DemandForecastWidget::class))
            ->getMethod('getFilters')
            ->invoke(new DemandForecastWidget);

        $this->assertSame(
            ['weekday_revenue', 'weekday_count', 'month_revenue', 'month_count'],
            array_keys($filters),
        );
    }

    /**
     * Card 160: the widget must not leak its Carbon locale preference into
     * the global Carbon locale — a fixed app locale must not override a
     * caller-set locale for the rest of the request lifecycle.
     */
    public function test_widget_restores_the_global_carbon_locale(): void
    {
        Carbon::setLocale('de');

        try {
            $this->widgetData('weekday_revenue');
            $this->widgetData('month_revenue');

            $this->assertSame('de', Carbon::getLocale());
        } finally {
            Carbon::setLocale(app()->getLocale());
        }
    }

    public function test_widget_is_a_bar_chart(): void
    {
        $type = (new ReflectionClass(DemandForecastWidget::class))
            ->getMethod('getType')
            ->invoke(new DemandForecastWidget);

        $this->assertSame('bar', $type);
    }

    public function test_widget_heading_is_localized(): void
    {
        app()->setLocale('id');
        $this->assertSame(__('dashboard.demand_forecast_heading'), (new DemandForecastWidget)->getHeading());

        app()->setLocale('en');
        $this->assertSame(__('dashboard.demand_forecast_heading'), (new DemandForecastWidget)->getHeading());
    }

    public function test_demand_forecast_lang_keys_exist_in_both_locales(): void
    {
        foreach (['id', 'en'] as $locale) {
            app()->setLocale($locale);

            $this->assertNotSame('dashboard.demand_forecast_heading', Lang::get('dashboard.demand_forecast_heading'));
            $this->assertNotSame('dashboard.filter.weekday', Lang::get('dashboard.filter.weekday'));
            $this->assertNotSame('dashboard.filter.month', Lang::get('dashboard.filter.month'));
        }
    }

    public function test_dashboard_renders_with_demand_forecast_widget_registered(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('filament.admin.pages.dashboard'))
            ->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function widgetData(?string $filter): array
    {
        $widget = new DemandForecastWidget;
        $widget->filter = $filter;

        return (new ReflectionClass(DemandForecastWidget::class))
            ->getMethod('getData')
            ->invoke($widget);
    }

    private function createOrderAt(Carbon $date, int $total, OrderStatus $status = OrderStatus::Paid): Order
    {
        $this->orderSeq++;

        return Order::withoutEvents(function () use ($date, $total, $status) {
            $order = new Order([
                'order_number' => 'DF-'.str_pad((string) $this->orderSeq, 4, '0', STR_PAD_LEFT),
                'status' => $status,
                'total' => $total,
                'created_by' => $this->admin->id,
            ]);

            $order->created_at = $date;
            $order->save();

            return $order;
        });
    }
}
