<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Revenue (last 14 days)';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = Order::query()
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->where('status', '!=', OrderStatus::Pending)
            ->whereDate('created_at', '>=', today()->subDays(13))
            ->groupBy('date')
            ->pluck('revenue', 'date');

        $labels = [];
        $data = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = today()->subDays($i)->toDateString();
            $labels[] = $date;
            $data[] = (int) ($rows[$date] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
