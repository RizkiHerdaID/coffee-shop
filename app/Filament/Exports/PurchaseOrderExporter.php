<?php

namespace App\Filament\Exports;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseOrderExporter extends Exporter
{
    protected static ?string $model = PurchaseOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('supplier.name')
                ->label(__('purchase-orders.fields.supplier')),
            ExportColumn::make('ordered_at')
                ->label(__('purchase-orders.fields.ordered_at'))
                ->formatStateUsing(fn ($state) => $state instanceof CarbonInterface ? $state->format('Y-m-d') : $state),
            ExportColumn::make('expected_at')
                ->label(__('purchase-orders.fields.expected_at'))
                ->formatStateUsing(fn ($state) => $state instanceof CarbonInterface ? $state->format('Y-m-d') : $state),
            ExportColumn::make('status')
                ->label(__('purchase-orders.fields.status'))
                ->formatStateUsing(fn ($state) => __(
                    'purchase-orders.statuses.'.($state instanceof PurchaseOrderStatus ? $state->value : $state)
                )),
            ExportColumn::make('total')
                ->label(__('purchase-orders.fields.total')),
            ExportColumn::make('note')
                ->label(__('purchase-orders.fields.note')),
            ExportColumn::make('created_at')
                ->label(__('purchase-orders.fields.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('purchase-orders.fields.updated_at')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('purchase-orders.exports.completed', ['count' => number_format($export->successful_rows)]);

        if ($export->getFailedRowsCount() > 0) {
            $body .= ' '.__('purchase-orders.exports.failed', ['failed_count' => number_format($export->getFailedRowsCount())]);
        }

        return $body;
    }
}
