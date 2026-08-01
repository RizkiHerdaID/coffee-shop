<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AverageOrderValue extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $query = Order::query()
            ->whereDate('created_at', today())
            ->where('status', '!=', OrderStatus::Pending);

        $count = (clone $query)->count();
        $revenue = (clone $query)->sum('total');
        $average = $count > 0 ? intdiv($revenue, $count) : 0;

        return [
            Stat::make('Average Order Value', 'Rp '.number_format($average, 0, ',', '.'))
                ->description('Paid & served orders')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('warning'),
        ];
    }
}
