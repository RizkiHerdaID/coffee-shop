<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemandForecastService
{
    public const DEFAULT_MONTHS = 3;

    /**
     * Paid orders created within the last $months months (current month
     * inclusive), newest first. Only id/created_at/total/discount fields are
     * selected; net totals are computed from the loaded models.
     *
     * @return Collection<int, Order>
     */
    public function paidOrders(?int $months = null): Collection
    {
        return Order::query()
            ->select(['id', 'created_at', 'total', 'discount_type', 'discount_amount'])
            ->whereNotIn('status', [OrderStatus::Pending, OrderStatus::Refunded, OrderStatus::Cancelled])
            ->where('created_at', '>=', $this->windowStart($months))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Order count and revenue per weekday over the last $months months.
     * Every weekday is present, zero-filled when empty.
     *
     * @return array{count: array<string, int>, revenue: array<string, int>}
     */
    public function weekdayAggregate(?int $months = null): array
    {
        $aggregate = $this->emptyAggregate();

        foreach ($this->paidOrders($months) as $order) {
            $key = $this->weekdayKey($order->created_at);
            $aggregate['count'][$key]++;
            $aggregate['revenue'][$key] += $order->net_total;
        }

        return $aggregate;
    }

    /**
     * Order count and revenue per calendar month over the last $months
     * months, oldest first. Keys are 'Y-m' (e.g. '2026-06'); months without
     * orders are zero-filled.
     *
     * @return array<string, array{count: int, revenue: int}>
     */
    public function monthAggregate(?int $months = null): array
    {
        $months = $months ?? self::DEFAULT_MONTHS;
        $aggregate = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $aggregate[now()->subMonthsNoOverflow($i)->format('Y-m')] = ['count' => 0, 'revenue' => 0];
        }

        foreach ($this->paidOrders($months) as $order) {
            $key = $order->created_at->format('Y-m');

            if (isset($aggregate[$key])) {
                $aggregate[$key]['count']++;
                $aggregate[$key]['revenue'] += $order->net_total;
            }
        }

        return $aggregate;
    }

    private function windowStart(?int $months): Carbon
    {
        return now()->subMonths(($months ?? self::DEFAULT_MONTHS) - 1)->startOfMonth();
    }

    private function weekdayKey(Carbon $date): string
    {
        return ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][$date->dayOfWeek];
    }

    /**
     * @return array{count: array<string, int>, revenue: array<string, int>}
     */
    private function emptyAggregate(): array
    {
        $keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        return [
            'count' => array_fill_keys($keys, 0),
            'revenue' => array_fill_keys($keys, 0),
        ];
    }
}
