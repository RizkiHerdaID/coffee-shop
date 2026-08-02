<?php

namespace App\Filament\Resources\Promos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PromosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('promos.empty.heading'))
            ->emptyStateDescription(__('promos.empty.description'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('promos.fields.title'))
                    ->searchable(),
                TextColumn::make('badge')
                    ->label(__('promos.fields.badge'))
                    ->badge()
                    ->color('warning'),
                TextColumn::make('starts_at')
                    ->label(__('promos.fields.starts_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('promos.fields.ends_at'))
                    ->dateTime()
                    ->sortable(),
                ToggleColumn::make('active')
                    ->label(__('promos.fields.active'))
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('promos.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('promos.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('promos.fields.updated_at'))
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
