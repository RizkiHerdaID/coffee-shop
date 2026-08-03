<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\FonnteWhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $locale) {}

    public function handle(FonnteWhatsApp $whatsapp): void
    {
        if (! config('whatsapp.enabled')) {
            return;
        }

        // Re-read the order from the database: the serialized model may be
        // stale, and with a sync queue the order may not have been
        // committed at dispatch time. If the order no longer exists, skip
        // silently.
        $order = $this->order->fresh();

        if ($order === null || blank($order->customer_phone)) {
            return;
        }

        $whatsapp->send($order->customer_phone, $this->message($order));
    }

    protected function message(Order $order): string
    {
        $items = $order->items()
            ->orderBy('id')
            ->limit(3)
            ->pluck('name')
            ->implode(', ');

        // The locale is captured at dispatch time: a queued job runs on
        // the queue worker, where app()->getLocale() is the config default
        // and would ignore the visitor's language.
        return __($items === '' ? 'whatsapp.confirmation' : 'whatsapp.confirmation_with_items', [
            'order_number' => $order->order_number,
            'shop' => config('shop.name'),
            'items' => $items,
            'total' => 'Rp '.number_format($order->net_total, 0, ',', '.'),
            'phone' => config('shop.phone'),
        ], $this->locale);
    }
}
