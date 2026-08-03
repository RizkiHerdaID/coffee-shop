<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
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
     * Top-items revenue is the order-level discount apportioned across each
     * order's line items (ratio net_total/total), so the column sums to the
     * NET headline revenue even for discounted orders.
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

        // Paid/served orders only (pending, refunded and cancelled are not
        // revenue), NET totals — matches Shift::salesTotal()/P&L so the
        // summary never disagrees with the shift reports.
        $orders = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', [
                OrderStatus::Pending,
                OrderStatus::Refunded,
                OrderStatus::Cancelled,
            ])
            ->get();

        $revenue = (int) $orders->sum(fn (Order $order): int => $order->net_total);
        $count = $orders->count();
        $avg = $count > 0 ? (int) round($revenue / $count) : 0;

        $topItems = [];

        if ($count > 0) {
            $ordersById = $orders->keyBy('id');
            $byName = [];

            foreach (OrderItem::query()->whereIn('order_id', $orders->pluck('id'))->get() as $item) {
                $order = $ordersById->get($item->order_id);

                if ($order === null || $order->total < 1) {
                    // No sane ratio — items of a fully-discounted (zero-net)
                    // order contribute 0 revenue.
                    $itemRevenue = 0;
                } else {
                    // Apportion the order-level discount across the line items
                    // so the top-items revenue column stays NET-consistent with
                    // the headline. Per-item rounding half-up reconciles exactly
                    // because net_total = total - discount and the shares are
                    // ratio-based (tests assert to the penny).
                    $itemRevenue = (int) round($item->subtotal * ($order->net_total / $order->total));
                }

                if (! isset($byName[$item->name])) {
                    $byName[$item->name] = ['name' => $item->name, 'qty' => 0, 'revenue' => 0];
                }

                $byName[$item->name]['qty'] += (int) $item->qty;
                $byName[$item->name]['revenue'] += $itemRevenue;
            }

            $topItems = collect($byName)
                ->sortByDesc('qty')
                ->take(5)
                ->values()
                ->all();
        }

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
