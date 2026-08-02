<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\LoyaltyCard;
use App\Models\Order;

class OrderObserver
{
    /**
     * Credit one loyalty stamp whenever an order becomes paid — either
     * created directly as paid or transitioned pending → paid — and a
     * customer phone is attached. Re-saves of an already-paid order
     * (edits, partial refunds) and refunds/voids never re-credit.
     */
    public function saved(Order $order): void
    {
        if ($order->status !== OrderStatus::Paid || blank($order->customer_phone)) {
            return;
        }

        $transitionedToPaid = $order->wasChanged('status')
            && $order->getOriginal('status') !== OrderStatus::Paid;

        if ($transitionedToPaid) {
            LoyaltyCard::credit($order->customer_phone);
        }
    }
}
