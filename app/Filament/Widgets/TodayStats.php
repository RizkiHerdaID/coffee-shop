<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $orders = Order::query()
            ->whereDate('created_at', today())
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled])
            ->get();

        $count = $orders->count();
        $revenue = $orders->sum(fn (Order $order): int => $order->net_total);
        $average = $count > 0 ? intdiv($revenue, $count) : 0;

        return [
            Stat::make(__('dashboard.today_revenue'), 'Rp '.number_format($revenue, 0, ',', '.'))
                ->description(__('dashboard.paid_served'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make(__('dashboard.today_orders'), $count)
                ->description(__('dashboard.paid_served'))
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('primary'),
            Stat::make(__('dashboard.avg_order_value'), 'Rp '.number_format($average, 0, ',', '.'))
                ->description(__('dashboard.paid_served'))
                ->icon(Heroicon::OutlinedChartBar)
                ->color('warning'),
        ];
    }
}
