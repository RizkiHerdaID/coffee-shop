<?php

namespace App\Filament\Exports;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ExpenseExporter extends Exporter
{
    protected static ?string $model = Expense::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('category')
                ->label(__('expenses.fields.category'))
                ->formatStateUsing(fn ($state) => $state instanceof ExpenseCategory ? $state->getLabel() : $state),
            ExportColumn::make('description')
                ->label(__('expenses.fields.description')),
            ExportColumn::make('amount')
                ->label(__('expenses.fields.amount')),
            ExportColumn::make('spent_at')
                ->label(__('expenses.fields.spent_at'))
                ->formatStateUsing(fn ($state) => $state instanceof CarbonInterface ? $state->format('Y-m-d') : $state),
            ExportColumn::make('note')
                ->label(__('expenses.fields.note')),
            ExportColumn::make('created_at')
                ->label(__('expenses.fields.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('expenses.fields.updated_at')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('expenses.exports.completed', ['count' => number_format($export->successful_rows)]);

        if ($export->getFailedRowsCount() > 0) {
            $body .= ' '.__('expenses.exports.failed', ['failed_count' => number_format($export->getFailedRowsCount())]);
        }

        return $body;
    }
}
