<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_id')
                    ->label(__('suppliers.fields.supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                DatePicker::make('ordered_at')
                    ->label(__('suppliers.fields.ordered_at')),
                DatePicker::make('expected_at')
                    ->label(__('suppliers.fields.expected_at')),
                Select::make('status')
                    ->label(__('suppliers.fields.status'))
                    ->options(fn (): array => collect(PurchaseOrderStatus::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => __("suppliers.statuses.$case->value")])
                        ->all())
                    ->default(PurchaseOrderStatus::Pending->value),
                TextInput::make('total')
                    ->label(__('suppliers.fields.total'))
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
                Textarea::make('note')
                    ->label(__('suppliers.fields.note')),
            ]);
    }
}
