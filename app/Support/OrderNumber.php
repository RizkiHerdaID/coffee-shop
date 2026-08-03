<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Next order number ORD-YYYYMMDD-####, handed out atomically from a
 * per-day counter row (order_counters). The counter row is locked FOR
 * UPDATE inside the surrounding transaction, so concurrent creators
 * serialize on it — including the very first order of the day, which
 * has no previous order row to lock (the seed is derived from any
 * orders that already exist for the day).
 *
 * Shared by the POS cashier and the Orders resource create form so both
 * draw from the same sequence.
 */
class OrderNumber
{
    public static function generate(): string
    {
        $date = now()->format('Ymd');

        $sequence = DB::transaction(function () use ($date): int {
            $counter = DB::table('order_counters')
                ->where('date', $date)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                $last = Order::query()
                    ->where('order_number', 'like', 'ORD-'.$date.'-%')
                    ->orderByDesc('order_number')
                    ->value('order_number');

                $next = $last === null ? 1 : ((int) substr($last, -4)) + 1;

                try {
                    DB::table('order_counters')->insert([
                        'date' => $date,
                        'last_number' => $next,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Another creator seeded the counter concurrently — fall
                    // back to incrementing the committed value under lock.
                    $counter = DB::table('order_counters')
                        ->where('date', $date)
                        ->lockForUpdate()
                        ->first();

                    $next = ((int) ($counter->last_number ?? $next)) + 1;

                    if ($counter !== null) {
                        DB::table('order_counters')->where('date', $date)->update(['last_number' => $next]);
                    }
                }

                return $next;
            }

            $next = $counter->last_number + 1;
            DB::table('order_counters')->where('date', $date)->update(['last_number' => $next]);

            return $next;
        });

        return 'ORD-'.$date.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
