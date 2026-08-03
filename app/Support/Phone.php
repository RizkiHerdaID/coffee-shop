<?php

namespace App\Support;

final class Phone
{
    /**
     * Normalize an Indonesian phone number into a stable, E.164-ish key:
     * drop all non-digits, convert a leading 0 into the 62 country code,
     * and leave already-qualified numbers untouched. Inputs like
     * "0812-3456-7890", "+6281234567890" and "081234567890" all converge
     * to "6281234567890" so lookups key consistently everywhere.
     */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }
}
