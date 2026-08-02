<?php

namespace App\Filament\Resources\MenuItems\RelationManagers;

use App\Models\StockItem;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecipesRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('recipes.label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('recipes.empty.heading'))
            ->emptyStateDescription(__('recipes.empty.description'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('recipes.fields.stock_item'))
                    ->searchable(),
                TextColumn::make('pivot.quantity')
                    ->label(__('recipes.fields.quantity'))
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('cost')
                    ->label(__('recipes.fields.cost'))
                    ->money('IDR'),
                TextColumn::make('line_cogs')
                    ->label(__('recipes.fields.cogs'))
                    ->state(fn (StockItem $record): int => (int) $record->cost * (int) $record->pivot->quantity)
                    ->money('IDR'),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                AttachAction::make()
                    ->recordTitleAttribute('name')
                    ->form([
                        Select::make('recordId')
                            ->label(__('recipes.fields.stock_item'))
                            ->options(StockItem::query()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        TextInput::make('quantity')
                            ->label(__('recipes.fields.quantity'))
                            ->helperText(__('recipes.fields.quantity_help'))
                            ->numeric()
                            ->required()
                            ->default(1),
                    ]),
            ]);
    }
}
