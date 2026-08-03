<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Report-query indexes for tables that were scanned without them:
     * - every report (P&L, dashboard widgets, shift report, summary email,
     *   demand forecast, cash-register session report) filters orders,
     *   order_items, payments, expenses and shift_cash_movements by a date
     *   range and/or joins on a foreign key;
     * - every remaining foreign-key column without an index is indexed too,
     *   so join lookups never seq-scan the child table.
     *
     * All 23 indexes verified absent on 2026-08-03 against the dev DB
     * (pg_indexes showed only PKs/unique/date-key indexes beforehand).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index('created_at'); // P&L, TodayStats, Revenue/PeakHours charts, summary email, forecast
            $table->index('shift_id'); // shift report: whereIn('shift_id') + Cashier attach
            $table->index('created_by'); // FK
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->index('order_id'); // whereHas/join/in on orders; top/best sellers, summary
            $table->index('menu_item_id'); // FK, recipe ingredient lookups
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index('order_id'); // joins from orders in shift/P&L/payment-split reports
            $table->index('paid_at'); // PaymentSplitChart whereDate(paid_at)
            $table->index('admin_id'); // FK
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->index('spent_at'); // P&L expense range + groupBy category
        });

        Schema::table('shift_cash_movements', function (Blueprint $table): void {
            $table->index('shift_id'); // deposits/pettyOut sums, shift report whereIn
            $table->index('admin_id'); // FK
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->index('admin_id'); // FK
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index('stock_item_id'); // recipe stock consume, low-stock lookups
            $table->index('order_item_id'); // FK
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->index('supplier_id'); // FK, supplier PO lists
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->index('purchase_order_id'); // FK, PO relation manager
            $table->index('stock_item_id'); // FK
        });

        Schema::table('menu_item_stock_item', function (Blueprint $table): void {
            // (menu_item_id, stock_item_id) unique covers menu_item_id lookups
            // only; stock_item_id lookups (ingredient usage) need their own.
            $table->index('stock_item_id');
        });

        Schema::table('wastages', function (Blueprint $table): void {
            $table->index('stock_item_id'); // FK
            $table->index('admin_id'); // FK
        });

        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            $table->index('admin_id'); // FK
        });

        Schema::table('imports', function (Blueprint $table): void {
            $table->index('user_id'); // FK
        });

        Schema::table('failed_import_rows', function (Blueprint $table): void {
            $table->index('import_id'); // FK
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_shift_id_index');
            $table->dropIndex('orders_created_by_index');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_order_id_index');
            $table->dropIndex('order_items_menu_item_id_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_order_id_index');
            $table->dropIndex('payments_paid_at_index');
            $table->dropIndex('payments_admin_id_index');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex('expenses_spent_at_index');
        });

        Schema::table('shift_cash_movements', function (Blueprint $table): void {
            $table->dropIndex('shift_cash_movements_shift_id_index');
            $table->dropIndex('shift_cash_movements_admin_id_index');
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropIndex('shifts_admin_id_index');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_stock_item_id_index');
            $table->dropIndex('stock_movements_order_item_id_index');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_supplier_id_index');
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropIndex('purchase_order_items_purchase_order_id_index');
            $table->dropIndex('purchase_order_items_stock_item_id_index');
        });

        Schema::table('menu_item_stock_item', function (Blueprint $table): void {
            $table->dropIndex('menu_item_stock_item_stock_item_id_index');
        });

        Schema::table('wastages', function (Blueprint $table): void {
            $table->dropIndex('wastages_stock_item_id_index');
            $table->dropIndex('wastages_admin_id_index');
        });

        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            $table->dropIndex('cash_register_sessions_admin_id_index');
        });

        Schema::table('imports', function (Blueprint $table): void {
            $table->dropIndex('imports_user_id_index');
        });

        Schema::table('failed_import_rows', function (Blueprint $table): void {
            $table->dropIndex('failed_import_rows_import_id_index');
        });
    }
};
