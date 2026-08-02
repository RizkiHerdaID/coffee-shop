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
        $query = Order::query()
            ->whereDate('created_at', today())
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled]);

        $count = (clone $query)->count();
        $revenue = (clone $query)->sum('total');
        $average = $count > 0 ? intdiv($revenue, $count) : 0;

        return [
            Stat::make('Today Revenue', 'Rp '.number_format($revenue, 0, ',', '.'))
                ->description('Paid & served orders')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Today Orders', $count)
                ->description('Paid & served orders')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('primary'),
            Stat::make('Average Order Value', 'Rp '.number_format($average, 0, ',', '.'))
                ->description('Paid & served orders')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('warning'),
        ];
    }
}
