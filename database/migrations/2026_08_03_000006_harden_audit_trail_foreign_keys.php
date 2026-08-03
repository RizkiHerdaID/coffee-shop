<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the cascade-on-delete foreign keys on the audit tables with
     * non-destructive rules:
     *
     * - admin_id columns (orders.created_by, payments.admin_id,
     *   shifts.admin_id, shift_cash_movements.admin_id, wastages.admin_id)
     *   become nullable + nullOnDelete: deleting an admin must never
     *   silently destroy the audit trail (payments, shifts, movements,
     *   wastages) it recorded.
     * - purchase_orders.supplier_id and stock_movements.stock_item_id
     *   become restrictOnDelete: a supplier with purchase orders or a stock
     *   item with movements cannot be deleted at the DB level.
     *
     * payments.order_id intentionally keeps cascadeOnDelete — orders are
     * undeletable in-app (Order::deleting throws), so the cascade only ever
     * fires when a row is destroyed out-of-band.
     *
     * NOTE: on SQLite, altering a table rebuilds it from the blueprint
     * state, which cannot represent the partial expression index on
     * shifts ((closed_at IS NULL) WHERE closed_at IS NULL). The index is
     * therefore dropped around the rebuild and re-added afterwards — this
     * also runs unchanged on PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex('shifts_single_open_unique');
            $table->dropForeign(['admin_id']);
            $table->unsignedBigInteger('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX shifts_single_open_unique ON shifts ((closed_at IS NULL)) WHERE closed_at IS NULL');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->unsignedBigInteger('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::table('shift_cash_movements', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->unsignedBigInteger('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::table('wastages', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->unsignedBigInteger('admin_id')->nullable()->change();
            $table->foreign('admin_id')->references('id')->on('admins')->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['stock_item_id']);
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->restrictOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * The columns are deliberately NOT forced back to NOT NULL: after up()
     * ran, rows whose admin was deleted carry a NULL admin_id, and re-adding
     * NOT NULL would either fail or destroy audit rows.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex('shifts_single_open_unique');
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX shifts_single_open_unique ON shifts ((closed_at IS NULL)) WHERE closed_at IS NULL');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('admins')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });

        Schema::table('shift_cash_movements', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });

        Schema::table('wastages', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->cascadeOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['stock_item_id']);
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->cascadeOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }
};
