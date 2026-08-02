<?php

namespace App\Filament\Exports;

use App\Models\StockItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StockItemExporter extends Exporter
{
    protected static ?string $model = StockItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(__('stock.fields.name')),
            ExportColumn::make('unit')
                ->label(__('stock.fields.unit')),
            ExportColumn::make('quantity')
                ->label(__('stock.fields.quantity')),
            ExportColumn::make('min_threshold')
                ->label(__('stock.fields.min_threshold')),
            ExportColumn::make('cost')
                ->label(__('stock.fields.cost')),
            ExportColumn::make('note')
                ->label(__('stock.fields.note')),
            ExportColumn::make('created_at')
                ->label(__('stock.fields.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('stock.fields.updated_at')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('stock.exports.completed', ['count' => number_format($export->successful_rows)]);

        if ($export->getFailedRowsCount() > 0) {
            $body .= ' '.__('stock.exports.failed', ['failed_count' => number_format($export->getFailedRowsCount())]);
        }

        return $body;
    }
}
