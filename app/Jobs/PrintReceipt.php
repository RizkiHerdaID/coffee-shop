<?php

namespace App\Jobs;

use App\Enums\PaymentMethod;
use App\Jobs\Concerns\PrintsThermal;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrintReceipt implements ShouldQueue
{
    use PrintsThermal;
    use Queueable;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        $width = (int) data_get(config('pos.printer'), 'chars_per_line', 32);
        $order = $this->order->loadMissing(['items', 'payments']);
        $date = $order->created_at->format('d/m/Y H:i');

        $lines = [
            $this->rule('=', $width),
            $this->centerText(__('pos.receipt.receipt'), $width),
            $this->centerText(config('shop.name'), $width),
            $this->centerText(__('pos.receipt.address', ['address' => str_replace("\n", ' ', config('shop.address'))]), $width),
            $this->rule('=', $width),
            $this->formatLine(__('pos.receipt.order'), $order->order_number, $width),
            $this->formatLine(__('pos.receipt.date'), $date, $width),
            $this->rule('-', $width),
        ];

        foreach ($order->items as $item) {
            $lines[] = $this->formatLine(
                mb_strimwidth($item->name, 0, $width - mb_strlen('x'.$item->qty) - 6, ''),
                'x'.$item->qty,
                $width,
            );
            $lines[] = $this->formatLine('', 'Rp '.number_format($item->subtotal, 0, ',', '.'), $width);
        }

        $lines[] = $this->rule('-', $width);

        if ($order->discount_value > 0) {
            $lines[] = $this->formatLine(__('dashboard.discount'), '-Rp '.number_format($order->discount_value, 0, ',', '.'), $width);
        }

        $lines[] = $this->formatLine(__('pos.receipt.total'), 'Rp '.number_format($order->net_total, 0, ',', '.'), $width);

        foreach ($order->payments as $payment) {
            $lines[] = $this->formatLine(
                mb_strtoupper(($payment->method ?? PaymentMethod::Cash)->getLabel()),
                'Rp '.number_format($payment->amount, 0, ',', '.'),
                $width,
            );
        }

        if ($order->paid_total > $order->net_total) {
            $lines[] = $this->formatLine(
                __('pos.receipt.change'),
                'Rp '.number_format($order->paid_total - $order->net_total, 0, ',', '.'),
                $width,
            );
        }

        $lines[] = $this->rule('-', $width);
        $lines[] = $this->centerText(__('pos.receipt.thank_you'), $width);
        $lines[] = $this->centerText(config('shop.phone_display'), $width);

        $this->printLines($lines);
    }
}
