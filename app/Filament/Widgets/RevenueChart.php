<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class RevenueChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.revenue_chart_heading');
    }

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
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled])
            ->whereDate('created_at', '>=', today()->subDays(13))
            ->get(['created_at', 'total', 'discount_type', 'discount_amount'])
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString())
            ->map(fn ($orders): int => $orders->sum(fn (Order $order): int => $order->net_total));

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
                    'label' => __('dashboard.revenue'),
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
