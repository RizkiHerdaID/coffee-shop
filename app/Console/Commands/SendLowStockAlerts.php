<?php

namespace App\Console\Commands;

use App\Models\StockItem;
use App\Services\FonnteWhatsApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendLowStockAlerts extends Command
{
    protected $signature = 'stock:alert-low';

    public function __construct()
    {
        parent::__construct();

        $this->description = __('stock.command.description');
    }

    public function handle(FonnteWhatsApp $whatsapp): int
    {
        $phone = config('whatsapp.low_stock.phone');

        if (blank($phone)) {
            Log::warning(__('stock.alert.no_phone'));

            $this->warn(__('stock.alert.no_phone'));

            return self::SUCCESS;
        }

        // An item is alertable when it was never notified or its last
        // alert is older than 24 hours, so items that stay low are
        // re-alerted daily while oscillating items can never re-alert
        // more than once per day.
        $items = StockItem::query()
            ->lowStock()
            ->where(function ($query) {
                $query->whereNull('low_stock_notified_at')
                    ->orWhere('low_stock_notified_at', '<', now()->subDay());
            })
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
