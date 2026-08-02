<?php

namespace App\Filament\Resources\StockItems\Tables;

use App\Models\StockItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading(__('stock.empty.heading'))
            ->emptyStateDescription(__('stock.empty.description'))
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
                    ->color(fn (StockItem $record): string => $record->isLowStock() ? 'warning' : 'success')
                    ->formatStateUsing(fn (StockItem $record): string => number_format($record->quantity, 0, ',', '.').($record->isLowStock() ? ' '.__('stock.badges.low') : '')),
                TextColumn::make('min_threshold')
                    ->label(__('stock.fields.min_threshold'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('cost')
                    ->label(__('stock.fields.cost'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('note')
                    ->label(__('stock.fields.note'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('stock.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('stock.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
                            ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                            ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))
                            ->required(),
                        TextInput::make('note')
                            ->label(__('stock.fields.note')),
                    ])
                    ->action(function (array $data, StockItem $record): void {
                        $record->stockIn((int) $data['quantity'], $data['note'] ?? null);
                    }),
                Action::make('stockOut')
                    ->label(__('stock.actions.stock_out'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading(__('stock.actions.stock_out'))
                    ->modalSubmitActionLabel(__('stock.actions.submit'))
                    ->successNotificationTitle(__('stock.notifications.stock_out_success'))
                    ->failureNotificationTitle(__('stock.notifications.stock_out_failed'))
                    ->form([
                        TextInput::make('quantity')
                            ->label(__('stock.fields.quantity'))
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                            ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))
                            ->required(),
                        TextInput::make('note')
                            ->label(__('stock.fields.note')),
                    ])
                    ->action(function (array $data, StockItem $record): void {
                        if (! $record->stockOut((int) $data['quantity'], $data['note'] ?? null)) {
                            throw new \Exception(__('stock.notifications.stock_out_failed'));
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
