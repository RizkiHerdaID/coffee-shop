<?php

namespace App\Filament\Resources\MenuItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('photo')
                    ->label(__('menu-items.fields.photo'))
                    ->disk('public'),
                TextColumn::make('name')
                    ->label(__('menu-items.fields.name'))
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('menu-items.fields.category')),
                TextColumn::make('available')
                    ->label(__('menu-items.fields.available'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? __('menu-items.available') : __('menu-items.unavailable'))
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
                TextColumn::make('price')
                    ->label(__('menu-items.fields.price'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('cogs')
                    ->label(__('recipes.cogs.label'))
                    ->state(fn (Model $record): int => $record->cogs())
                    ->money('IDR')
                    ->tooltip(__('recipes.cogs.tooltip')),
                TextColumn::make('margin')
                    ->label(__('recipes.margin.label'))
                    ->state(fn (Model $record): int => $record->price - $record->cogs())
                    ->money('IDR')
                    ->tooltip(__('recipes.margin.tooltip'))
                    ->color(fn (Model $record): string => $record->price - $record->cogs() >= 0 ? 'success' : 'danger'),
                TextColumn::make('note')
                    ->label(__('menu-items.fields.note'))
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label(__('menu-items.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('menu-items.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('menu-items.fields.updated_at'))
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
