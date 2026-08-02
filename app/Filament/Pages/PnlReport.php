<?php

namespace App\Filament\Pages;

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PnlReport extends Page
{
    protected string $view = 'filament.pages.pnl-report';

    protected static ?string $slug = 'pnl-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'pnl.navigation';

    protected static ?int $navigationSort = 3;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->fromDate ??= now()->startOfMonth()->toDateString();
        $this->toDate ??= now()->toDateString();
    }

    public function updatedFromDate(): void
    {
        $this->validatePeriod();
    }

    public function updatedToDate(): void
    {
        $this->validatePeriod();
    }

    public function validatePeriod(): void
    {
        if ($this->fromDate !== null && $this->toDate !== null && $this->fromDate > $this->toDate) {
            $this->error = __('pnl.period.invalid');
        } else {
            $this->error = null;
        }
    }

    public function getTitle(): string
    {
        return __('pnl.title');
    }

    /**
     * P&L figures for the given period (inclusive dates, 'Y-m-d' strings).
     *
     * @return array<string, int|array<string, int>|float>
     */
    protected function getReportData(?string $from = null, ?string $to = null): array
    {
        $from ??= $this->fromDate ?? now()->startOfMonth()->toDateString();
        $to ??= $this->toDate ?? now()->toDateString();

        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        $paidOrders = Order::query()
            ->whereNotIn('status', [
                OrderStatus::Pending,
                OrderStatus::Refunded,
                OrderStatus::Cancelled,
            ])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end);

        // NET revenue of paid/served orders only — same definition as
        // Shift::salesTotal() so P&L reconciles with shift Z-reports.
        $paidOrdersCollection = $paidOrders->get();
        $revenue = $paidOrdersCollection->sum(fn (Order $order): int => $order->net_total);
        $ordersCount = $paidOrdersCollection->count();

        $items = OrderItem::query()
            ->whereHas('order', function (Builder $query) use ($start, $end): void {
                $query
                    ->whereNotIn('status', [
                        OrderStatus::Pending,
                        OrderStatus::Refunded,
                        OrderStatus::Cancelled,
                    ])
                    ->whereDate('created_at', '>=', $start)
                    ->whereDate('created_at', '<=', $end);
            })
            ->with('menuItem.ingredients')
            ->get();

        // COGS uses the CURRENT recipe-ingredient cost (menu_item_stock_item
        // pivot quantity × stock item cost) at report time — there is no
        // per-order cost snapshot column, so historical ingredient price
        // changes are reflected in current reports (documented current-cost
        // approach; a snapshot would need a schema change).
        $cogs = $items->sum(fn (OrderItem $item): int => (int) $item->qty * (int) $item->menuItem?->ingredients?->sum(
            fn (StockItem $ingredient): int => (int) $ingredient->cost * (int) $ingredient->pivot->quantity
        ));

        $itemsSold = $items->sum('qty');

        $expenseRows = Expense::query()
            ->whereDate('spent_at', '>=', $start)
            ->whereDate('spent_at', '<=', $end)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $expenses = [];

        foreach (ExpenseCategory::cases() as $case) {
            $expenses[$case->value] = (int) ($expenseRows[$case->value] ?? 0);
        }

        $expensesTotal = array_sum($expenses);

        $grossMargin = $revenue - $cogs;
        $netMargin = $grossMargin - $expensesTotal;

        return [
            'revenue' => $revenue,
            'orders_count' => $ordersCount,
            'items_sold' => $itemsSold,
            'cogs' => $cogs,
            'expenses' => $expenses,
            'expenses_total' => $expensesTotal,
            'gross_margin' => $grossMargin,
            'net_margin' => $netMargin,
            'gross_margin_percent' => $revenue > 0 ? round($grossMargin / $revenue * 100, 1) : 0.0,
            'net_margin_percent' => $revenue > 0 ? round($netMargin / $revenue * 100, 1) : 0.0,
            'inventory_value' => (int) StockItem::query()->sum(DB::raw('cost * quantity')),
        ];
    }
}
