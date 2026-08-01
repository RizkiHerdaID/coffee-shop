<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TopItemsChart extends ChartWidget
{
    protected ?string $heading = 'Top items by revenue';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = OrderItem::query()
            ->select('order_items.name')
            ->selectRaw('SUM(order_items.subtotal) as revenue')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Pending)
            ->groupBy('order_items.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->pluck('revenue', 'name');

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_values($rows->all()),
                ],
            ],
            'labels' => array_keys($rows->all()),
        ];
    }
}
