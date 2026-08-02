<?php

namespace App\Filament\Resources\PurchaseOrders\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('purchase-orders.relation.items.label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->label(__('purchase-orders.fields.description'))
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('purchase-orders.fields.quantity'))
                    ->numeric()
                    ->required()
                    ->default(1),
                TextInput::make('unit_price')
                    ->label(__('purchase-orders.fields.unit_price'))
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(function ($state) {
                        if (blank($state)) {
                            return $state;
                        }

                        if (str_contains((string) $state, '.')) {
                            return $state;
                        }

                        return number_format((int) $state, 0, ',', '.');
                    })
                    ->dehydrateStateUsing(function ($state) {
                        $cleaned = str_replace('.', '', (string) $state);

                        return $cleaned === '' ? null : (int) $cleaned;
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('purchase-orders.relation.items.empty_heading'))
            ->columns([
                TextColumn::make('description')
                    ->label(__('purchase-orders.fields.description')),
                TextColumn::make('quantity')
                    ->label(__('purchase-orders.fields.quantity'))
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('unit_price')
                    ->label(__('purchase-orders.fields.unit_price'))
                    ->money('IDR'),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ]);
    }
}
