<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('customer_phone')
                    ->label(__('orders.customer_phone'))
                    ->tel()
                    ->placeholder(__('orders.customer_phone_placeholder')),
                Select::make('status')
                    ->required()
                    ->options(OrderStatus::class)
                    ->default(OrderStatus::Pending),
                TextInput::make('total')
                    ->label(__('orders.total'))
                    ->required()
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : number_format($state, 0, ',', '.'))
                    ->dehydrateStateUsing(fn (?string $state): ?int => $state === null ? null : (int) str_replace('.', '', $state))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/'),
                Select::make('shift_id')
                    ->relationship('shift', 'id')
                    ->nullable(),
                Select::make('created_by')
                    ->relationship('admin', 'name')
                    ->required(),
            ]);
    }
}
