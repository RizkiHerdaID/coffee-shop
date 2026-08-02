<?php

namespace App\Filament\Widgets;

use App\Services\DemandForecastService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class DemandForecastWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.demand_forecast_heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            'weekday_revenue' => __('dashboard.filter.weekday').' — '.__('dashboard.revenue'),
            'weekday_count' => __('dashboard.filter.weekday').' — '.__('dashboard.count'),
            'month_revenue' => __('dashboard.filter.month').' — '.__('dashboard.revenue'),
            'month_count' => __('dashboard.filter.month').' — '.__('dashboard.count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $mode = $this->filter ?? 'weekday_revenue';
        $service = new DemandForecastService;
        Carbon::setLocale(app()->getLocale());

        if (str_starts_with($mode, 'month')) {
            $rows = $service->monthAggregate();
            $isCount = str_ends_with($mode, 'count');

            return [
                'datasets' => [
                    [
                        'label' => $isCount ? __('dashboard.count') : __('dashboard.revenue'),
                        'data' => array_map(
                            fn (array $row): int => $isCount ? $row['count'] : $row['revenue'],
                            array_values($rows),
                        ),
                    ],
                ],
                'labels' => array_map(
                    fn (string $key): string => Carbon::createFromFormat('Y-m', $key)->translatedFormat('M Y'),
                    array_keys($rows),
                ),
            ];
        }

        $rows = $service->weekdayAggregate();
        $isCount = str_ends_with($mode, 'count');
        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        return [
            'datasets' => [
                [
                    'label' => $isCount ? __('dashboard.count') : __('dashboard.revenue'),
                    'data' => array_map(
                        fn (string $key): int => $isCount ? $rows['count'][$key] : $rows['revenue'][$key],
                        $dayKeys,
                    ),
                ],
            ],
            'labels' => array_map(fn (string $key): string => __("dashboard.day_labels.$key"), $dayKeys),
        ];
    }
}
