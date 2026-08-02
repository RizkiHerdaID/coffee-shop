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
        $this->printLines($this->renderLines());
    }

    /**
     * Render the ticket as thermal lines, one entry per printed line.
     *
     * Line notes print indented under their item; the order-level note prints
     * under all items, prefixed by the localized "Notes" header.
     *
     * @return list<string>
     */
    public function renderLines(): array
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

            if (filled($item->notes)) {
                foreach ($this->wrapNotes($item->notes, $width) as $note) {
                    $lines[] = '  - '.$note."\n";
                }
            }
        }

        if (filled($order->notes)) {
            $lines[] = $this->rule('-', $width);
            $lines[] = __('orders.notes').':'."\n";

            foreach ($this->wrapNotes($order->notes, $width) as $note) {
                $lines[] = '  - '.$note."\n";
            }
        }

        $lines[] = $this->rule('-', $width);
        $lines[] = $this->centerText(__('pos.receipt.thank_you'), $width);

        return $lines;
    }

    /**
     * Wrap a note to the printable width, splitting on existing line breaks.
     *
     * @return list<string>
     */
    protected function wrapNotes(string $note, int $width): array
    {
        $available = max($width - 4, 8);

        return collect(explode("\n", $note))
            ->flatMap(fn (string $segment): array => mb_str_split($segment, $available) ?: [''])
            ->map(fn (string $segment): string => mb_strimwidth($segment, 0, $available, ''))
            ->all();
    }
}
