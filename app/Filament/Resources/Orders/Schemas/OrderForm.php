<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->required()
                    ->options(OrderStatus::class)
                    ->default(OrderStatus::Pending),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp'),
                Select::make('shift_id')
                    ->relationship('shift', 'id')
                    ->nullable(),
                Select::make('created_by')
                    ->relationship('admin', 'name')
                    ->required(),
            ]);
    }
}
