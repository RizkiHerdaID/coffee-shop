<?php

namespace App\Filament\Resources\StockItems\RelationManagers;

use App\Enums\WasteReason;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WastagesRelationManager extends RelationManager
{
    protected static string $relationship = 'wastages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('wastage.plural_label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('recorded_at', 'desc')
            ->emptyStateHeading(__('wastage.empty.relation_heading'))
            ->columns([
                TextColumn::make('reason')
                    ->label(__('wastage.fields.reason'))
                    ->badge()
                    ->color(fn (WasteReason $state): string => match ($state) {
                        WasteReason::Spilled => 'warning',
                        WasteReason::Expired => 'danger',
                        WasteReason::Damaged => 'danger',
                        WasteReason::Other => 'gray',
                    }),
                TextColumn::make('quantity')
                    ->label(__('wastage.fields.quantity'))
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('note')
                    ->label(__('wastage.fields.note')),
                TextColumn::make('recorded_at')
                    ->label(__('wastage.fields.recorded_at'))
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
