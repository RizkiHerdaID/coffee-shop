<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\FonnteWhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function handle(FonnteWhatsApp $whatsapp): void
    {
        if (! config('whatsapp.enabled') || ! filled($this->order->customer_phone)) {
            return;
        }

        $whatsapp->send($this->order->customer_phone, $this->message());
    }

    protected function message(): string
    {
        $items = $this->order->items()
            ->orderBy('id')
            ->limit(3)
            ->pluck('name')
            ->implode(', ');

        return __($items === '' ? 'whatsapp.confirmation' : 'whatsapp.confirmation_with_items', [
            'order_number' => $this->order->order_number,
            'shop' => config('shop.name'),
            'items' => $items,
            'total' => 'Rp '.number_format($this->order->net_total, 0, ',', '.'),
            'phone' => config('shop.phone'),
        ]);
    }
}
