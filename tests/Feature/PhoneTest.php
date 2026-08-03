<?php

namespace Tests\Feature;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phone number normalization (Vikunja 111): every consumer (Fonnte sends,
 * order confirmations, loyalty card lookups) keys on the same canonical
 * form, so any Indonesian format converges to the E.164-ish 62 prefix.
 */
class PhoneTest extends TestCase
{
    #[DataProvider('phoneFormats')]
    public function test_normalize_converts_formats_to_the_canonical_key(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function phoneFormats(): array
    {
        return [
            'plain leading zero' => ['081234567890', '6281234567890'],
            'dashes' => ['0812-3456-7890', '6281234567890'],
            'spaces' => ['0812 3456 7890', '6281234567890'],
            'plus country code' => ['+6281234567890', '6281234567890'],
            'plus with formatting' => ['+62 812-3456-7890', '6281234567890'],
            'already international' => ['6281234567890', '6281234567890'],
            'short local' => ['0812', '62812'],
            'missing country prefix stays untouched' => ['81234567890', '81234567890'],
            'empty string' => ['', ''],
            'non-digit garbage' => ['()-', ''],
        ];
    }
}
