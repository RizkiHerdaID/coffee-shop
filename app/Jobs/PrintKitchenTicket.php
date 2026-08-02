<?php

namespace App\Jobs;

use App\Jobs\Concerns\PrintsThermal;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrintKitchenTicket implements ShouldQueue
{
    use PrintsThermal;
    use Queueable;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $width = (int) data_get(config('pos.printer'), 'chars_per_line', 32);
        $order = $this->order->loadMissing(['items']);
        $date = $order->created_at->format('d/m/Y H:i');

        $lines = [
            $this->rule('=', $width),
            $this->centerText(__('pos.receipt.kitchen'), $width),
            $this->centerText(config('shop.name'), $width),
            $this->rule('=', $width),
            $this->formatLine(__('pos.receipt.order'), $order->order_number, $width),
            $this->formatLine(__('pos.receipt.date'), $date, $width),
            $this->rule('-', $width),
        ];

        foreach ($order->items as $item) {
            $lines[] = mb_strimwidth($item->name, 0, $width, '')."\n";
            $lines[] = '  x'.$item->qty."\n";
        }

        $lines[] = $this->rule('-', $width);
        $lines[] = $this->centerText(__('pos.receipt.thank_you'), $width);

        $this->printLines($lines);
    }
}
