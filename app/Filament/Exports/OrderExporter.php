<?php

namespace App\Filament\Exports;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number'),
            ExportColumn::make('customer_phone')
                ->label(__('orders.customer_phone')),
            ExportColumn::make('status')
                ->label(__('orders.status'))
                ->formatStateUsing(fn ($state) => $state instanceof OrderStatus ? $state->getLabel() : $state),
            ExportColumn::make('total')
                ->label(__('orders.total')),
            ExportColumn::make('paid_total')
                ->label(__('pos.paid')),
            ExportColumn::make('shift_id')
                ->label(__('pos.shift.nav_label')),
            ExportColumn::make('admin.name')
                ->label(__('orders.created_by')),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('orders.exports.completed', ['count' => number_format($export->successful_rows)]);

        if ($export->getFailedRowsCount() > 0) {
            $body .= ' '.__('orders.exports.failed', ['failed_count' => number_format($export->getFailedRowsCount())]);
        }

        return $body;
    }
}
