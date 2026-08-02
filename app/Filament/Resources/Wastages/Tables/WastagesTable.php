<?php

namespace App\Filament\Resources\Wastages\Tables;

use App\Enums\WasteReason;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WastagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('recorded_at', 'desc')
            ->emptyStateHeading(__('wastage.empty.heading'))
            ->emptyStateDescription(__('wastage.empty.description'))
            ->columns([
                TextColumn::make('stockItem.name')
                    ->label(__('wastage.fields.stock_item'))
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label(__('wastage.fields.quantity'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('reason')
                    ->label(__('wastage.fields.reason'))
                    ->badge()
                    ->color(fn (WasteReason $state): string => match ($state) {
                        WasteReason::Spilled => 'warning',
                        WasteReason::Expired => 'danger',
                        WasteReason::Damaged => 'danger',
                        WasteReason::Other => 'gray',
                    }),
                TextColumn::make('recorded_at')
                    ->label(__('wastage.fields.recorded_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('admin.name')
                    ->label(__('wastage.fields.admin')),
                TextColumn::make('note')
                    ->label(__('wastage.fields.note'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('wastage.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('wastage.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
