<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Throwable;

/**
 * Shared ESC/POS rendering for the POS print jobs.
 *
 * The printer connection is read from config/pos.php; when it is not
 * enabled/configured the job logs and returns without failing — the
 * browser-print receipt view is the fallback path.
 */
trait PrintsThermal
{
    /**
     * Send pre-rendered lines to the thermal printer.
     *
     * @param  list<string>  $lines  each entry must end with "\n"
     */
    protected function printLines(array $lines): bool
    {
        $printer = config('pos.printer');
        $printer = is_array($printer) ? $printer : [];

        if (! ($printer['enabled'] ?? false) || blank($printer['address'] ?? null)) {
            Log::info('pos.printer_disabled', ['order' => $this->order->order_number]);

            return false;
        }

        $connection = $printer['connection'] ?? 'network';
        $address = $printer['address'];
        $port = (int) ($printer['port'] ?? 9100);

        try {
            $connector = match ($connection) {
                'network' => new NetworkPrintConnector($address, $port),
                'file' => new FilePrintConnector($address),
                'windows' => new WindowsPrintConnector($address),
                default => throw new InvalidArgumentException("Unsupported printer connection [{$connection}]"),
            };

            $thermal = new Printer($connector);
            $thermal->initialize();

            foreach ($lines as $line) {
                $thermal->text($line);
            }

            $thermal->feed(2);
            $thermal->cut();
            $thermal->close();
        } catch (Throwable $e) {
            Log::warning('pos.print_failed', [
                'order' => $this->order->order_number,
                'connection' => $connection,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Two-column line: left label, right value, padded to the full width.
     *
     * Both columns are truncated so the rendered line NEVER exceeds the
     * configured width (a line that overflows makes the ESC/POS printer wrap
     * mid-line, which looks broken on thermal rolls).
     */
    protected function formatLine(string $left, string $right, int $width): string
    {
        $right = mb_strimwidth($right, 0, $width, '');
        $left = mb_strimwidth($left, 0, max($width - mb_strlen($right) - 1, 0), '');

        return $left.str_repeat(' ', max($width - mb_strlen($left) - mb_strlen($right), 0)).$right."\n";
    }

    protected function centerText(string $text, int $width): string
    {
        $text = mb_strimwidth($text, 0, $width, '');

        $padding = max(intdiv($width - mb_strlen($text), 2), 0);

        return str_repeat(' ', $padding).$text."\n";
    }

    protected function rule(string $char, int $width): string
    {
        return str_repeat($char, $width)."\n";
    }
}
