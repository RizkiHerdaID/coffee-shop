<?php

namespace App\Filament\Resources\CashRegisterSessions\Schemas;

use App\Enums\CashRegisterStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class CashRegisterSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('opened_at')
                    ->label(__('cash-register-sessions.fields.opened_at'))
                    ->default(now())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        self::recalculateExpected($set, $get);
                    }),
                DateTimePicker::make('closed_at')
                    ->label(__('cash-register-sessions.fields.closed_at'))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        self::recalculateExpected($set, $get);
                    }),
                TextInput::make('opening_float')
                    ->label(__('cash-register-sessions.fields.opening_float'))
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(fn ($state) => self::formatMoney($state))
                    ->dehydrateStateUsing(fn ($state) => self::dehydrateMoney($state))
                    ->default(0)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        self::recalculateExpected($set, $get);
                    }),
                TextInput::make('expected_amount')
                    ->label(__('cash-register-sessions.fields.expected_amount'))
                    ->disabled()
                    ->dehydrated()
                    ->default(0)
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->formatStateUsing(fn ($state) => self::formatMoney($state))
                    ->dehydrateStateUsing(fn ($state) => self::dehydrateMoney($state))
                    ->hint(__('cash-register-sessions.hints.expected_formula')),
                TextInput::make('counted_amount')
                    ->label(__('cash-register-sessions.fields.counted_amount'))
                    ->prefix('Rp')
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                    ->formatStateUsing(fn ($state) => self::formatMoney($state))
                    ->dehydrateStateUsing(fn ($state) => self::dehydrateMoney($state))
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        $set('discrepancy', self::discrepancy(
                            self::rawMoney($get('expected_amount')),
                            $get('counted_amount'),
                        ));
                    }),
                TextInput::make('discrepancy')
                    ->label(__('cash-register-sessions.fields.discrepancy'))
                    ->disabled()
                    ->dehydrated()
                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                    ->formatStateUsing(fn ($state) => self::formatMoney($state))
                    ->dehydrateStateUsing(fn ($state) => self::dehydrateMoney($state)),
                Select::make('status')
                    ->label(__('cash-register-sessions.fields.status'))
                    ->options(CashRegisterStatus::class)
                    ->default(CashRegisterStatus::Open)
                    ->required(),
                Select::make('admin_id')
                    ->label(__('cash-register-sessions.fields.admin'))
                    ->relationship('admin', 'name')
                    ->required(),
            ]);
    }

    /**
     * Recompute the expected amount and discrepancy after a live update to
     * any of the session inputs that feed the formula:
     * expected_amount = opening_float + order revenue within the session
     * window (opened_at .. closed_at, or up to now while the session is open).
     */
    protected static function recalculateExpected(Set $set, Get $get): void
    {
        $expected = self::expected(
            $get('opening_float'),
            $get('opened_at'),
            $get('closed_at'),
        );

        $set('expected_amount', $expected);
        $set('discrepancy', self::discrepancy(
            self::rawMoney($expected),
            $get('counted_amount'),
        ));
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

    public static function dehydrateMoney(mixed $state): ?int
    {
        if ($state === null || $state === '') {
            return null;
        }

        return self::rawMoney($state);
    }

    /**
     * Revenue formula (mirrors CashRegisterSession::revenue()): SUM of
     * paid/served orders' NET totals where orders.created_at >= opened_at
     * and (closed_at is null OR orders.created_at <= closed_at). Pending,
     * refunded and cancelled orders are excluded. For an open session the
     * window is bounded by "now" so the stored expected amount matches the
     * model's expectedAmount().
     */
    public static function revenue(?string $openedAt, ?string $closedAt): int
    {
        if (blank($openedAt)) {
            return 0;
        }

        return (int) Order::query()
            ->where('created_at', '>=', $openedAt)
            ->where('created_at', '<=', $closedAt ?? now()->toDateTimeString())
            ->whereNotIn('status', [
                OrderStatus::Pending,
                OrderStatus::Refunded,
                OrderStatus::Cancelled,
            ])
            ->get()
            ->sum(fn (Order $order): int => $order->net_total);
    }

    public static function expected(string $float, ?string $openedAt, ?string $closedAt): int
    {
        return self::rawMoney($float) + self::revenue($openedAt, $closedAt);
    }

    /**
     * Counted minus expected (same sign convention as Shift::discrepancy):
     * a positive discrepancy means the drawer holds MORE than expected.
     */
    public static function discrepancy(?int $expected, ?string $counted): ?int
    {
        if ($counted === null || $counted === '') {
            return null;
        }

        return self::rawMoney($counted) - $expected;
    }
}
