<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class PaymentSplitChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string|Htmlable|null
    {
        return __('dashboard.payment_split_heading');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->selectRaw('payments.method, SUM(payments.amount) as total')
            ->whereDate('payments.paid_at', today())
            ->whereNotIn('orders.status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled])
            ->groupBy('payments.method')
            ->pluck('total', 'method');

        $labels = [];
        $data = [];

        foreach (PaymentMethod::cases() as $method) {
            $labels[] = $method->getLabel();
            $data[] = (int) ($rows[$method->value] ?? 0);
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
