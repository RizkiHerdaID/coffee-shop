<?php

namespace App\Filament\Resources\Wastages\Schemas;

use App\Enums\WasteReason;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class WastageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stock_item_id')
                    ->label(__('wastage.fields.stock_item'))
                    ->relationship('stockItem', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('wastage.fields.quantity'))
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(fn ($state) => self::formatQuantity($state))
                    ->dehydrateStateUsing(fn ($state) => self::rawQuantity($state))
                    ->required(),
                Select::make('reason')
                    ->label(__('wastage.fields.reason'))
                    ->options(WasteReason::class)
                    ->required(),
                DateTimePicker::make('recorded_at')
                    ->label(__('wastage.fields.recorded_at'))
                    ->default(now())
                    ->required(),
                Textarea::make('note')
                    ->label(__('wastage.fields.note')),
                Hidden::make('admin_id')
                    ->default(auth('admin')->id()),
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
