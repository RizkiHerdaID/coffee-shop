<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class PeakHoursChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.peak_hours_heading');
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
            'revenue' => __('dashboard.filter.revenue'),
            'count' => __('dashboard.filter.count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $mode = $this->filter ?? 'revenue';

        $orders = Order::query()
            ->select(['created_at', 'total'])
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled])
            ->where('created_at', '>=', today()->subDays(29))
            ->get();

        $data = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($orders as $order) {
            $index = ($order->created_at->dayOfWeek + 6) % 7;
            $hour = $order->created_at->hour;

            if ($mode === 'count') {
                $data[$index][$hour]++;
            } else {
                $data[$index][$hour] += $order->total;
            }
        }

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $colors = ['#fef3c7', '#fde68a', '#fcd34d', '#fbbf24', '#f59e0b', '#d97706', '#b45309'];

        $datasets = [];

        foreach ($dayKeys as $index => $key) {
            $datasets[] = [
                'label' => __("dashboard.day_labels.$key"),
                'data' => $data[$index],
                'backgroundColor' => $colors[$index],
                'borderColor' => $colors[$index],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_map(
                fn (int $hour): string => str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
                range(0, 23),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                ],
            ],
        ];
    }
}
