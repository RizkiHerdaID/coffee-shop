<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty card keyed by customer phone. Every PAID order with a phone
 * number earns one stamp; every 10 stamps can be redeemed for a free
 * drink (redeem() decrements stamps by 10 and increments redeemed).
 *
 * All balance mutations run inside a transaction with a row lock
 * (SELECT ... FOR UPDATE) so concurrent cashier and admin operations
 * serialize instead of losing updates.
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
     * Negative quantities are ignored (a credit can never remove stamps).
     */
    public static function credit(string $phone, int $qty = 1): static
    {
        $phone = trim($phone);

        return DB::transaction(function () use ($phone, $qty): static {
            $card = static::query()->where('phone', $phone)->lockForUpdate()->first()
                ?? static::query()->create(['phone' => $phone]);

            if ($qty > 0) {
                $card->increment('stamps', $qty);
            }

            return $card->refresh();
        });
    }

    /**
     * Grant or remove stamps in one go (negative deltas clamp at zero).
     * The row lock makes the read-modify-write race-free.
     */
    public static function adjustStamps(string $phone, int $delta): static
    {
        $phone = trim($phone);

        return DB::transaction(function () use ($phone, $delta): static {
            $card = static::query()->where('phone', $phone)->lockForUpdate()->first()
                ?? static::query()->create(['phone' => $phone]);

            $card->update(['stamps' => max($card->stamps + $delta, 0)]);

            return $card->refresh();
        });
    }

    /**
     * Redeem one free drink when the card holds at least 10 stamps.
     * Returns false when the balance is insufficient. The balance is
     * re-checked under the row lock, so concurrent redeems can never
     * drive the stamp balance negative.
     */
    public static function redeem(string $phone): bool
    {
        $phone = trim($phone);

        return DB::transaction(function () use ($phone): bool {
            $card = static::query()->where('phone', $phone)->lockForUpdate()->first();

            if (! $card || $card->stamps < 10) {
                return false;
            }

            $card->update([
                'stamps' => $card->stamps - 10,
                'redeemed' => $card->redeemed + 1,
            ]);

            return true;
        });
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
