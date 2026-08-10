<?php

namespace App\Support;

use Filament\Support\RawJs;

/**
 * Shared money formatting and parsing for Indonesian Rupiah.
 *
 * All methods expect integer-IDR values (e.g. 25000 for Rp 25.000).
 * The parse helpers accept Indonesian formatted strings and return
 * raw integers; the format helpers produce display-ready strings.
 */
class Money
{
    /**
     * Validate an Indonesian-formatted amount string (e.g. "25.000").
     */
    public static function isValidRupiah(string $value): bool
    {
        return preg_match('/^(\d{1,3}(\.\d{3})*|\d+)$/', $value) === 1;
    }

    /**
     * Parse an Indonesian-formatted string to integer (e.g. "25.000" → 25000).
     */
    public static function parseRupiah(mixed $value): int
    {
        return (int) str_replace('.', '', (string) $value);
    }

    /**
     * Format an integer as "Rp 25.000".
     */
    public static function formatRupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * Format an integer as "25.000" (no currency prefix).
     */
    public static function plain(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    /**
     * Idempotent formatStateUsing callback for Filament TextInput:
     * already-dotted strings pass through (failed-validation re-renders),
     * raw integers get thousand-separated Indonesian formatting.
     */
    public static function filamentFormat(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return $state;
        }

        if (str_contains((string) $state, '.')) {
            return $state;
        }

        return number_format((int) $state, 0, ',', '.');
    }

    /**
     * Filament $money mask for Indonesian thousand separators.
     */
    public static function filamentMask(): RawJs
    {
        return RawJs::make('$money($input, \',\', \'.\', 0)');
    }

    /**
     * Filament validation rule for Indonesian-formatted numeric input.
     *
     * @return array{0: string}
     */
    public static function filamentRule(): array
    {
        return ['regex:/^(\d{1,3}(\.\d{3})*|\d+)$/'];
    }
}
