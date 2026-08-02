<?php

namespace App\Console\Commands;

use App\Models\StockItem;
use App\Services\FonnteWhatsApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendLowStockAlerts extends Command
{
    protected $signature = 'stock:alert-low';

    protected $description = 'Send WhatsApp alerts for low-stock items';

    public function handle(FonnteWhatsApp $whatsapp): int
    {
        StockItem::query()
            ->whereColumn('quantity', '>', 'min_threshold')
            ->whereNotNull('low_stock_notified_at')
            ->update(['low_stock_notified_at' => null]);

        $phone = config('whatsapp.low_stock.phone');

        if (blank($phone)) {
            Log::warning(__('stock.alert.no_phone'));

            $this->warn(__('stock.alert.no_phone'));

            return self::SUCCESS;
        }

        $items = StockItem::query()
            ->lowStock()
            ->whereNull('low_stock_notified_at')
            ->get();

        if ($items->isEmpty()) {
            $this->info(__('stock.alert.none'));

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($items as $item) {
            $message = __('stock.alert.subject')."\n\n".__('stock.alert.body', [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'threshold' => $item->min_threshold,
            ]);

            if (! $whatsapp->send($phone, $message)) {
                continue;
            }

            $item->forceFill(['low_stock_notified_at' => now()])->save();

            $sent++;
        }

        $this->info(__('stock.alert.sent', ['count' => $sent]));

        return self::SUCCESS;
    }
}
