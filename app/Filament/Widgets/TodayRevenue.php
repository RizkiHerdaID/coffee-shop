<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayRevenue extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $revenue = Order::query()
            ->whereDate('created_at', today())
            ->where('status', '!=', OrderStatus::Pending)
            ->sum('total');

        return [
            Stat::make('Today Revenue', 'Rp '.number_format($revenue, 0, ',', '.'))
                ->description('Paid & served orders')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }
}
