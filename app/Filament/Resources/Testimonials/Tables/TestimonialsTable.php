<?php

namespace App\Filament\Resources\Testimonials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('testimonials.empty.heading'))
            ->emptyStateDescription(__('testimonials.empty.description'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('testimonials.fields.name'))
                    ->searchable(),
                TextColumn::make('rating')
                    ->label(__('testimonials.fields.rating'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 5 => 'success',
                        $state >= 4 => 'info',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('text')
                    ->label(__('testimonials.fields.text'))
                    ->limit(60)
                    ->wrap(),
                ToggleColumn::make('visible')
                    ->label(__('testimonials.fields.visible'))
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('testimonials.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('testimonials.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('testimonials.fields.updated_at'))
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
