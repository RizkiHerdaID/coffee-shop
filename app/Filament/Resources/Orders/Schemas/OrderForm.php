<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\OrderNumber;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->label(__('orders.fields.order_number'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled()
                    ->dehydrated()
                    ->default(fn (): string => OrderNumber::generate()),
                TextInput::make('customer_phone')
                    ->label(__('orders.customer_phone'))
                    ->tel()
                    ->placeholder(__('orders.customer_phone_placeholder')),
                Select::make('status')
                    ->label(__('orders.status'))
                    ->required()
                    ->options(OrderStatus::class)
                    ->default(OrderStatus::Pending),
                TextInput::make('total')
                    ->label(__('orders.total'))
                    ->required()
                    ->prefix('Rp')
                    ->minValue(1)
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->formatStateUsing(fn ($state) => self::formatTotal($state))
                    ->dehydrateStateUsing(fn (?string $state): ?int => $state === null ? null : (int) str_replace('.', '', $state))
                    ->rule('regex:/^([1-9]\d{0,2}(\.\d{3})*|[1-9]\d*)$/'),
                Select::make('shift_id')
                    ->label(__('orders.fields.shift_id'))
                    ->relationship('shift', 'id', function (Select $component, Builder $query): Builder {
                        $query->open();

                        // Keep the order's current shift selectable on the
                        // edit page even when it is already closed (the
                        // save is blocked separately); only NEW attachments
                        // are restricted to open shifts.
                        if (($record = $component->getRecord()) instanceof Order && filled($record->shift_id)) {
                            $query->orWhere('id', $record->shift_id);
                        }

                        return $query;
                    })
                    ->nullable(),
                Select::make('created_by')
                    ->label(__('orders.fields.created_by'))
                    ->relationship('admin', 'name')
                    ->required(),
            ]);
    }

    /**
     * Idempotent money formatting: already-dotted state (failed-validation
     * re-renders) passes through untouched so 25.000 never becomes
     * 25.000.000; raw integers get Indonesian thousand separators.
     */
    public static function formatTotal(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return $state;
        }

        if (str_contains((string) $state, '.')) {
            return $state;
        }

        return number_format((int) $state, 0, ',', '.');
    }
}
