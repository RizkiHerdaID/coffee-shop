<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Filament\Pages\PnlReport;
use App\Models\Admin;
use App\Models\Expense;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * P&L report page (App\Filament\Pages\PnlReport, route filament.admin.pages.pnl-report).
 *
 * The Filament page is Livewire-lazy, so the figure math is asserted through
 * the protected getReportData($from, $to) method via reflection (same pattern
 * as DashboardWidgetsTest), with inclusive 'Y-m-d' period filtering on
 * orders.created_at / expenses.spent_at:
 *
 * - revenue      sum of order.total for paid orders (status NOT IN pending,
 *                refunded, cancelled) inside the period
 * - cogs         sum of order_items.qty x recipe cogs (ingredient cost x
 *                pivot quantity); lines without a menu_item_id contribute 0
 * - expenses     per-category sums for all 8 ExpenseCategory cases (0-filled)
 * - gross/net margin = revenue - cogs - expenses; percents of revenue
 * - inventory_value = sum of stock_items.cost x quantity (period-independent)
 */
class PnlReportTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    private int $orderSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    public function test_revenue_sums_paid_orders_within_period_only(): void
    {
        $this->createOrderAt(25000, '2026-07-05', OrderStatus::Paid);
        $this->createOrderAt(15000, '2026-07-20', OrderStatus::Served);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(40000, $data['revenue']);
        $this->assertSame(2, $data['orders_count']);
    }

    public function test_revenue_excludes_pending_refunded_and_cancelled_orders(): void
    {
        $this->createOrderAt(25000, '2026-07-05', OrderStatus::Paid);
        $this->createOrderAt(99999, '2026-07-06', OrderStatus::Pending);
        $this->createOrderAt(99999, '2026-07-07', OrderStatus::Refunded);
        $this->createOrderAt(99999, '2026-07-08', OrderStatus::Cancelled);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(25000, $data['revenue']);
        $this->assertSame(1, $data['orders_count']);
    }

    public function test_period_bounds_are_inclusive_and_outside_orders_excluded(): void
    {
        $this->createOrderAt(10000, '2026-07-01', OrderStatus::Paid);
        $this->createOrderAt(20000, '2026-07-31', OrderStatus::Paid);
        $this->createOrderAt(99999, '2026-06-30', OrderStatus::Paid);
        $this->createOrderAt(99999, '2026-08-01', OrderStatus::Paid);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(30000, $data['revenue']);
        $this->assertSame(2, $data['orders_count']);
    }

    public function test_cogs_sums_line_quantity_times_recipe_cost(): void
    {
        $espresso = $this->menuWithCogs('Espresso', 20000, 18000);
        $latte = $this->menuWithCogs('Latte', 25000, 26000);

        $order = $this->createOrderAt(90000, '2026-07-05', OrderStatus::Paid);
        $this->addLine($order, $espresso, 2, 20000);
        $this->addLine($order, $latte, 1, 25000);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(2 * 18000 + 1 * 26000, $data['cogs']);
        $this->assertSame(3, $data['items_sold']);
    }

    public function test_cogs_lines_without_menu_item_contribute_zero(): void
    {
        $espresso = $this->menuWithCogs('Espresso', 20000, 18000);

        $order = $this->createOrderAt(25000, '2026-07-05', OrderStatus::Paid);
        $this->addLine($order, $espresso, 1, 20000);

        $order->items()->create([
            'menu_item_id' => null,
            'name' => 'Produk Dihapus',
            'price' => 5000,
            'qty' => 3,
            'subtotal' => 15000,
        ]);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(18000, $data['cogs']);
        $this->assertSame(4, $data['items_sold']);
    }

    public function test_cogs_only_counts_paid_orders_inside_period(): void
    {
        $espresso = $this->menuWithCogs('Espresso', 20000, 18000);

        $inside = $this->createOrderAt(20000, '2026-07-05', OrderStatus::Paid);
        $this->addLine($inside, $espresso, 1, 20000);

        $pending = $this->createOrderAt(20000, '2026-07-06', OrderStatus::Pending);
        $this->addLine($pending, $espresso, 5, 20000);

        $outside = $this->createOrderAt(20000, '2026-06-30', OrderStatus::Paid);
        $this->addLine($outside, $espresso, 7, 20000);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(18000, $data['cogs']);
    }

    public function test_expenses_aggregated_by_category_within_period(): void
    {
        Expense::create([
            'category' => ExpenseCategory::Ingredients,
            'description' => 'Kopi robusta',
            'amount' => 50000,
            'spent_at' => '2026-07-03',
        ]);
        Expense::create([
            'category' => ExpenseCategory::Ingredients,
            'description' => 'Susu UHT',
            'amount' => 25000,
            'spent_at' => '2026-07-15',
        ]);
        Expense::create([
            'category' => ExpenseCategory::Utilities,
            'description' => 'Listrik',
            'amount' => 100000,
            'spent_at' => '2026-07-10',
        ]);
        Expense::create([
            'category' => ExpenseCategory::Rent,
            'description' => 'Sewa Juni',
            'amount' => 99999,
            'spent_at' => '2026-06-30',
        ]);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(75000, $data['expenses'][ExpenseCategory::Ingredients->value]);
        $this->assertSame(100000, $data['expenses'][ExpenseCategory::Utilities->value]);
        $this->assertSame(0, $data['expenses'][ExpenseCategory::Rent->value]);
        $this->assertSame(175000, $data['expenses_total']);
    }

    public function test_expenses_array_has_all_category_keys_defaulted_to_zero(): void
    {
        $data = $this->reportData('2026-07-01', '2026-07-31');

        $expected = [
            'ingredients', 'supplies', 'utilities', 'equipment',
            'marketing', 'salaries', 'rent', 'other',
        ];

        $this->assertSame($expected, array_keys($data['expenses']));

        foreach ($expected as $key) {
            $this->assertSame(0, $data['expenses'][$key]);
        }
    }

    public function test_expense_period_bounds_are_inclusive(): void
    {
        Expense::create([
            'category' => ExpenseCategory::Other,
            'description' => 'Batas awal',
            'amount' => 5000,
            'spent_at' => '2026-07-01',
        ]);
        Expense::create([
            'category' => ExpenseCategory::Other,
            'description' => 'Batas akhir',
            'amount' => 6000,
            'spent_at' => '2026-07-31',
        ]);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(11000, $data['expenses_total']);
    }

    public function test_gross_and_net_margin_math(): void
    {
        $espresso = $this->menuWithCogs('Espresso', 20000, 18000);

        $order = $this->createOrderAt(40000, '2026-07-05', OrderStatus::Paid);
        $this->addLine($order, $espresso, 2, 20000);

        Expense::create([
            'category' => ExpenseCategory::Utilities,
            'description' => 'Listrik',
            'amount' => 5000,
            'spent_at' => '2026-07-10',
        ]);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(40000, $data['revenue']);
        $this->assertSame(36000, $data['cogs']);
        $this->assertSame(4000, $data['gross_margin']);
        $this->assertSame(5000, $data['expenses_total']);
        $this->assertSame(-1000, $data['net_margin']);
        $this->assertSame(10.0, $data['gross_margin_percent']);
        $this->assertSame(-2.5, $data['net_margin_percent']);
    }

    public function test_margin_percent_is_zero_when_no_revenue(): void
    {
        Expense::create([
            'category' => ExpenseCategory::Rent,
            'description' => 'Sewa',
            'amount' => 100000,
            'spent_at' => '2026-07-10',
        ]);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(0, $data['revenue']);
        $this->assertSame(0.0, $data['gross_margin_percent']);
        $this->assertSame(0.0, $data['net_margin_percent']);
    }

    public function test_empty_period_returns_zeroed_report(): void
    {
        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(0, $data['revenue']);
        $this->assertSame(0, $data['orders_count']);
        $this->assertSame(0, $data['items_sold']);
        $this->assertSame(0, $data['cogs']);
        $this->assertSame(0, $data['expenses_total']);
        $this->assertSame(0, $data['gross_margin']);
        $this->assertSame(0, $data['net_margin']);
    }

    public function test_inventory_value_sums_stock_cost_times_quantity(): void
    {
        StockItem::create(['name' => 'Biji Kopi', 'unit' => 'gram', 'quantity' => 10, 'cost' => 1500]);
        StockItem::create(['name' => 'Susu', 'unit' => 'ml', 'quantity' => 3, 'cost' => 2000]);

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(10 * 1500 + 3 * 2000, $data['inventory_value']);
    }

    /**
     * Card 104: revenue is NET of paid/served orders. The discounted order
     * (gross 65.000 − 10.000) counts 55.000, so a gross-summing report
     * (85.000) would fail this.
     */
    public function test_revenue_uses_net_totals_of_discounted_orders(): void
    {
        $this->createOrderAt(65000, '2026-07-05', OrderStatus::Paid, 'fixed', 10000); // net 55.000
        $this->createOrderAt(20000, '2026-07-06', OrderStatus::Served);               // net 20.000
        $this->createOrderAt(99999, '2026-07-07', OrderStatus::Pending, 'fixed', 50000); // excluded

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(75000, $data['revenue']);
        $this->assertSame(2, $data['orders_count']);
    }

    public function test_revenue_uses_percent_discount_values(): void
    {
        $this->createOrderAt(20000, '2026-07-05', OrderStatus::Paid, 'percent', 10); // net 18.000
        $this->createOrderAt(65000, '2026-07-06', OrderStatus::Served, 'percent', 15); // net 55.250

        $data = $this->reportData('2026-07-01', '2026-07-31');

        $this->assertSame(73250, $data['revenue']);
    }

    public function test_page_requires_authentication(): void
    {
        $this->get(route('filament.admin.pages.pnl-report'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('filament.admin.pages.pnl-report'))
            ->assertOk();
    }

    public function test_pnl_title_is_localized(): void
    {
        app()->setLocale('id');
        $this->assertSame('Laporan Laba Rugi', __('pnl.title'));

        app()->setLocale('en');
        $this->assertSame('Profit & Loss Report', __('pnl.title'));
    }

    /**
     * Card 152: the sidebar navigation label resolves the translated
     * pnl.navigation key in the current locale (a raw 'pnl.navigation'
     * literal must never surface in the admin nav).
     */
    public function test_navigation_label_resolves_to_the_translated_value(): void
    {
        app()->setLocale('id');
        $this->assertSame(__('pnl.navigation'), PnlReport::getNavigationLabel());

        app()->setLocale('en');
        $this->assertSame(__('pnl.navigation'), PnlReport::getNavigationLabel());
    }

    public function test_pnl_lang_files_share_the_same_key_structure(): void
    {
        $id = require lang_path('id/pnl.php');
        $en = require lang_path('en/pnl.php');

        $this->assertSame(
            $this->flattenKeys($id),
            $this->flattenKeys($en)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportData(string $from, string $to): array
    {
        $page = new PnlReport;

        return (new ReflectionClass(PnlReport::class))
            ->getMethod('getReportData')
            ->invoke($page, $from, $to);
    }

    private function createOrderAt(int $total, string $date, OrderStatus $status, ?string $discountType = null, ?int $discountAmount = null): Order
    {
        $this->orderSeq++;

        return Order::withoutEvents(function () use ($total, $date, $status, $discountType, $discountAmount): Order {
            $order = new Order([
                'order_number' => 'PNL-'.str_pad((string) $this->orderSeq, 3, '0', STR_PAD_LEFT),
                'status' => $status,
                'total' => $total,
                'discount_type' => $discountType,
                'discount_amount' => $discountAmount,
                'created_by' => $this->admin->id,
            ]);

            $order->created_at = "$date 10:30:00";
            $order->save();

            return $order;
        });
    }

    private function addLine(Order $order, MenuItem $menuItem, int $qty, int $price): void
    {
        $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'name' => $menuItem->name,
            'price' => $price,
            'qty' => $qty,
            'subtotal' => $price * $qty,
        ]);
    }

    /**
     * Menu item whose recipe cogs come to exactly $cogs per unit.
     */
    private function menuWithCogs(string $name, int $price, int $cogs): MenuItem
    {
        $item = MenuItem::create(['name' => $name, 'price' => $price]);

        $ingredient = StockItem::create([
            'name' => "Bahan $name",
            'unit' => 'unit',
            'quantity' => 100,
            'cost' => $cogs,
        ]);

        $item->ingredients()->attach($ingredient->id, ['quantity' => 1]);

        return $item;
    }

    /**
     * @param  array<mixed>  $array
     * @return array<mixed>
     */
    private function flattenKeys(array $array): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $keys[] = (string) $key;

            if (is_array($value)) {
                foreach ($this->flattenKeys($value) as $nested) {
                    $keys[] = "$key.$nested";
                }
            }
        }

        return $keys;
    }
}
