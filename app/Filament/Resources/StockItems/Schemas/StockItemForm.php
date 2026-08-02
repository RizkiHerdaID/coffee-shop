<?php

namespace App\Filament\Resources\StockItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class StockItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('stock.fields.name'))
                    ->required(),
                TextInput::make('unit')
                    ->label(__('stock.fields.unit'))
                    ->required()
                    ->placeholder(__('stock.fields.unit_placeholder')),
                TextInput::make('quantity')
                    ->label(__('stock.fields.quantity'))
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(fn ($state) => self::formatQuantity($state))
                    ->dehydrateStateUsing(fn ($state) => self::rawQuantity($state))
                    ->default(0),
                TextInput::make('min_threshold')
                    ->label(__('stock.fields.min_threshold'))
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(fn ($state) => self::formatQuantity($state))
                    ->dehydrateStateUsing(fn ($state) => self::rawQuantity($state))
                    ->default(0),
                TextInput::make('cost')
                    ->label(__('stock.fields.cost'))
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(fn ($state) => self::formatQuantity($state))
                    ->dehydrateStateUsing(fn ($state) => self::rawQuantity($state))
                    ->default(0),
                TextInput::make('note')
                    ->label(__('stock.fields.note')),
            ]);
    }

    public static function formatQuantity(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return $state;
        }

        if (str_contains((string) $state, '.')) {
            return $state;
        }

        return number_format((int) $state, 0, ',', '.');
    }

    public static function rawQuantity(mixed $state): int
    {
        return (int) str_replace('.', '', (string) ($state ?? 0));
    }
}
