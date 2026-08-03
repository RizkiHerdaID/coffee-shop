<?php

namespace App\Models;

use App\Support\Phone;
use Database\Factories\LoyaltyCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty card keyed by customer phone. Every PAID order with a phone
 * number earns one stamp; every loyalty.stamps_per_reward stamps
 * (default 10) can be redeemed for a free drink (redeem() decrements
 * stamps by that amount and increments redeemed).
 *
 * Phone numbers are normalized to a canonical key (0812... → 62812...)
 * before every lookup or mutation, so POS, admin and public lookups key
 * consistently regardless of the input format.
 *
 * All balance mutations run inside a transaction with a row lock
 * (SELECT ... FOR UPDATE) so concurrent cashier and admin operations
 * serialize instead of losing updates. The first-create race for a
 * brand-new phone is resolved atomically with an insert-or-ignore, so
 * two concurrent first credits degrade to fetch-and-increment instead
 * of hitting the unique index with a duplicate row.
 */
#[Fillable(['phone', 'stamps', 'redeemed'])]
class LoyaltyCard extends Model
{
    /** @use HasFactory<LoyaltyCardFactory> */
    use HasFactory;

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
     * Look up the card for a phone number in any format, returning null
     * when no card exists yet.
     */
    public static function findByPhone(string $phone): ?static
    {
        return static::query()->where('phone', Phone::normalize($phone))->first();
    }

    /**
     * Credit stamps for a phone number, creating the card on first use.
     * Negative quantities are ignored (a credit can never remove stamps).
     */
    public static function credit(string $phone, int $qty = 1): static
    {
        $phone = Phone::normalize($phone);

        return DB::transaction(function () use ($phone, $qty): static {
            static::query()->insertOrIgnore([
                'phone' => $phone,
                'stamps' => 0,
                'redeemed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $card = static::query()->where('phone', $phone)->lockForUpdate()->firstOrFail();

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
        $phone = Phone::normalize($phone);

        return DB::transaction(function () use ($phone, $delta): static {
            static::query()->insertOrIgnore([
                'phone' => $phone,
                'stamps' => 0,
                'redeemed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $card = static::query()->where('phone', $phone)->lockForUpdate()->firstOrFail();

            $card->update(['stamps' => max($card->stamps + $delta, 0)]);

            return $card->refresh();
        });
    }

    /**
     * Redeem one free drink when the card holds at least the configured
     * number of stamps (loyalty.stamps_per_reward, default 10). Returns
     * false when the balance is insufficient. The balance is re-checked
     * under the row lock, so concurrent redeems can never drive the
     * stamp balance negative.
     */
    public static function redeem(string $phone): bool
    {
        $phone = Phone::normalize($phone);
        $stampsPerReward = (int) config('loyalty.stamps_per_reward');

        return DB::transaction(function () use ($phone, $stampsPerReward): bool {
            $card = static::query()->where('phone', $phone)->lockForUpdate()->first();

            if (! $card || $card->stamps < $stampsPerReward) {
                return false;
            }

            $card->update([
                'stamps' => $card->stamps - $stampsPerReward,
                'redeemed' => $card->redeemed + 1,
            ]);

            return true;
        });
    }

    /**
     * Free drinks claimable right now (one per full block of
     * loyalty.stamps_per_reward stamps).
     */
    public function freeDrinksAvailable(): int
    {
        return intdiv($this->stamps, (int) config('loyalty.stamps_per_reward'));
    }

    /**
     * Stamps still needed for the next free drink.
     */
    public function remainingToNextFreeDrink(): int
    {
        $stampsPerReward = (int) config('loyalty.stamps_per_reward');

        return $stampsPerReward - ($this->stamps % $stampsPerReward);
    }
}
