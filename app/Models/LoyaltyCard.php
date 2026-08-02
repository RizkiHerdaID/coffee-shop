<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Loyalty card keyed by customer phone. Every PAID order with a phone
 * number earns one stamp; every 10 stamps can be redeemed for a free
 * drink (redeem() decrements stamps by 10 and increments redeemed).
 */
#[Fillable(['phone', 'stamps', 'redeemed'])]
class LoyaltyCard extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stamps' => 'integer',
            'redeemed' => 'integer',
        ];
    }

    /**
     * Credit stamps for a phone number, creating the card on first use.
     */
    public static function credit(string $phone, int $qty = 1): static
    {
        $card = static::firstOrCreate(['phone' => trim($phone)]);

        $card->increment('stamps', $qty);

        return $card->refresh();
    }

    /**
     * Grant or remove stamps in one go (negative deltas clamp at zero).
     */
    public static function adjustStamps(string $phone, int $delta): static
    {
        $card = static::firstOrCreate(['phone' => trim($phone)]);

        $card->update(['stamps' => max($card->stamps + $delta, 0)]);

        return $card->refresh();
    }

    /**
     * Redeem one free drink when the card holds at least 10 stamps.
     * Returns false when the balance is insufficient.
     */
    public static function redeem(string $phone): bool
    {
        $card = static::where('phone', trim($phone))->first();

        if (! $card || $card->stamps < 10) {
            return false;
        }

        $card->update([
            'stamps' => $card->stamps - 10,
            'redeemed' => $card->redeemed + 1,
        ]);

        return true;
    }

    /**
     * Free drinks claimable right now (one per full block of 10 stamps).
     */
    public function freeDrinksAvailable(): int
    {
        return intdiv($this->stamps, 10);
    }

    /**
     * Stamps still needed for the next free drink.
     */
    public function remainingToNextFreeDrink(): int
    {
        return 10 - ($this->stamps % 10);
    }
}
