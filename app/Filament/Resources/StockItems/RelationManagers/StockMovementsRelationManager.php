<?php

namespace App\Filament\Resources\StockItems\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stock.movements.label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('stock.movements.empty_heading'))
            ->columns([
                TextColumn::make('type')
                    ->label(__('stock.fields.type'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'in' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'in' ? __('stock.movements.in') : __('stock.movements.out')),
                TextColumn::make('quantity')
                    ->label(__('stock.fields.quantity'))
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('note')
                    ->label(__('stock.fields.note')),
                TextColumn::make('created_at')
                    ->label(__('stock.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
