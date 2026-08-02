<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category')
                    ->label(__('expenses.fields.category'))
                    ->options(ExpenseCategory::class)
                    ->required(),
                TextInput::make('description')
                    ->label(__('expenses.fields.description'))
                    ->required(),
                TextInput::make('amount')
                    ->label(__('expenses.fields.amount'))
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(fn ($state) => self::formatMoney($state))
                    ->dehydrateStateUsing(fn ($state) => self::rawMoney($state))
                    ->required(),
                DatePicker::make('spent_at')
                    ->label(__('expenses.fields.spent_at'))
                    ->default(now())
                    ->required(),
                Textarea::make('note')
                    ->label(__('expenses.fields.note')),
            ]);
    }

    public static function formatMoney(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return $state;
        }

        if (str_contains((string) $state, '.')) {
            return $state;
        }

        return number_format((int) $state, 0, ',', '.');
    }

    public static function rawMoney(mixed $state): int
    {
        return (int) str_replace('.', '', (string) ($state ?? 0));
    }
}
