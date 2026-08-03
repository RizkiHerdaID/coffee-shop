<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderForm
{
    /**
     * Whether the form must be read-only: received purchase orders are
     * immutable stock-in records.
     */
    private static function isReceived(?Model $record): bool
    {
        return $record !== null && $record->received_at !== null;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_id')
                    ->label(__('purchase-orders.fields.supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (?Model $record): bool => self::isReceived($record)),
                DatePicker::make('ordered_at')
                    ->label(__('purchase-orders.fields.ordered_at'))
                    ->disabled(fn (?Model $record): bool => self::isReceived($record)),
                DatePicker::make('expected_at')
                    ->label(__('purchase-orders.fields.expected_at'))
                    ->disabled(fn (?Model $record): bool => self::isReceived($record)),
                Select::make('status')
                    ->label(__('purchase-orders.fields.status'))
                    ->options(fn (): array => collect(PurchaseOrderStatus::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => __("purchase-orders.statuses.$case->value")])
                        ->all())
                    ->default(PurchaseOrderStatus::Pending->value)
                    ->disabled(fn (?Model $record): bool => self::isReceived($record)),
                TextInput::make('total')
                    ->label(__('purchase-orders.fields.total'))
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
                    })
                    ->disabled(fn (?Model $record): bool => self::isReceived($record)),
                Textarea::make('note')
                    ->label(__('purchase-orders.fields.note'))
                    ->disabled(fn (?Model $record): bool => self::isReceived($record)),
            ]);
    }
}
