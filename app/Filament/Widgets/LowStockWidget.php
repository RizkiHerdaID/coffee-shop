<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockWidget extends TableWidget
{
    protected function getTableQuery(): Builder
    {
        return StockItem::query()->lowStock()->orderBy('quantity');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.low_stock_heading'))
            ->query(fn (): Builder => $this->getTableQuery())
            ->emptyStateHeading(__('dashboard.low_stock_empty_heading'))
            ->emptyStateDescription(__('dashboard.low_stock_empty_description'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('stock.fields.name'))
                    ->searchable(),
                TextColumn::make('unit')
                    ->label(__('stock.fields.unit')),
                TextColumn::make('quantity')
                    ->label(__('stock.fields.quantity'))
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (StockItem $record): string => number_format($record->quantity, 0, ',', '.').' '.__('stock.badges.low')),
                TextColumn::make('min_threshold')
                    ->label(__('stock.fields.min_threshold'))
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
            ])
            ->recordActions([
                Action::make('stockIn')
                    ->label(__('stock.actions.stock_in'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->modalHeading(__('stock.actions.stock_in'))
                    ->modalSubmitActionLabel(__('stock.actions.submit'))
                    ->successNotificationTitle(__('stock.notifications.stock_in_success'))
                    ->form([
                        TextInput::make('quantity')
                            ->label(__('stock.fields.quantity'))
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->rule('regex:/^([1-9]\d{0,2}(\.\d{3})*|[1-9]\d*)$/')
                            ->minValue(1)
                            ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))
                            ->required(),
                        TextInput::make('note')
                            ->label(__('stock.fields.note')),
                    ])
                    ->action(function (array $data, StockItem $record): void {
                        if (! $record->stockIn((int) $data['quantity'], $data['note'] ?? null)) {
                            throw new \Exception(__('stock.notifications.stock_in_failed'));
                        }
                    }),
            ])
            ->toolbarActions([
                Action::make('manageStock')
                    ->label(__('dashboard.low_stock_manage'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(StockItemResource::getUrl('index')),
            ]);
    }
}
