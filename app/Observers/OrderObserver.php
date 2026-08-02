<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\LoyaltyCard;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    /**
     * Credit one loyalty stamp EXACTLY once per order lifecycle: whenever
     * a saved order is paid, carries a customer phone, and has not been
     * credited yet (tracked by orders.loyalty_credited_at). This covers
     * orders created directly as paid and pending → paid transitions in
     * both the POS and the admin edit paths, and never re-credits after
     * re-saves, refunds, or refunded → paid edits.
     *
     * The order row is locked inside the transaction so two concurrent
     * saves of the same order cannot both observe the unset flag and
     * credit twice; the loyalty card row lock (LoyaltyCard::credit)
     * serializes the actual stamp increment against admin adjustments.
     */
    public function saved(Order $order): void
    {
        if ($order->status !== OrderStatus::Paid || blank($order->customer_phone)) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->loyalty_credited_at !== null) {
                return;
            }

            LoyaltyCard::credit($locked->customer_phone);

            $locked->forceFill(['loyalty_credited_at' => now()])->saveQuietly();
        });
    }
}
