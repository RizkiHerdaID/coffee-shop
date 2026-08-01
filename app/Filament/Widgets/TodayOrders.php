<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayOrders extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $orders = Order::query()
            ->whereDate('created_at', today())
            ->where('status', '!=', OrderStatus::Pending)
            ->count();

        return [
            Stat::make('Today Orders', $orders)
                ->description('Paid & served orders')
                ->icon(Heroicon::OutlinedShoppingBag)
                ->color('primary'),
        ];
    }
}
