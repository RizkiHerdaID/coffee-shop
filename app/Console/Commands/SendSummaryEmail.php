<?php

namespace App\Console\Commands;

use App\Mail\SalesSummary;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSummaryEmail extends Command
{
    protected $signature = 'summary:send
        {--period=daily : Summary period (daily|weekly)}
        {--date= : Anchor date in YYYY-MM-DD format, defaults to today}
        {--to= : Recipient email address, defaults to config("summary.recipient")}';

    public function handle(): int
    {
        $period = $this->option('period');

        if (! in_array($period, ['daily', 'weekly'], true)) {
            $this->error(__('summary.command.error', ['error' => $period]));

            return self::FAILURE;
        }

        $recipient = $this->option('to') ?: config('summary.recipient');

        if (blank($recipient)) {
            $this->error(__('summary.command.no_recipient'));

            return self::FAILURE;
        }

        $this->info(__('summary.command.running', ['period' => $period]));

        try {
            $stats = $this->aggregate($period);

            Mail::to($recipient)->queue(new SalesSummary($period, $stats));

            $this->info(__('summary.command.queued', [
                'period' => $period,
                'recipient' => $recipient,
            ]));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error(__('summary.command.error', ['error' => $exception->getMessage()]));

            return self::FAILURE;
        }
    }

    /**
     * Aggregate sales for the period, in the app timezone (Asia/Jakarta).
     *
     * @return array{period: string, start: Carbon, end: Carbon, revenue: int, orders_count: int, avg_order: int, top_items: array<int, array{name: string, qty: int, revenue: int}>}
     */
    private function aggregate(string $period): array
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::now()->startOfDay();

        if ($period === 'weekly') {
            $start = $today->copy()->subDays(7)->startOfDay();
        } else {
            $start = $today->copy()->subDay()->startOfDay();
        }

        $end = $today->copy()->subDay()->endOfDay();

        $orders = Order::query()->whereBetween('created_at', [$start, $end])->get();

        $revenue = (int) $orders->sum('total');
        $count = $orders->count();
        $avg = $count > 0 ? (int) round($revenue / $count) : 0;

        $topItems = $count > 0 ? OrderItem::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->selectRaw('name, SUM(qty) as total_qty, SUM(subtotal) as total_subtotal')
            ->groupBy('name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(fn (OrderItem $row): array => [
                'name' => $row->name,
                'qty' => (int) $row->total_qty,
                'revenue' => (int) $row->total_subtotal,
            ])
            ->all() : [];

        return [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'revenue' => $revenue,
            'orders_count' => $count,
            'avg_order' => $avg,
            'top_items' => $topItems,
        ];
    }
}
