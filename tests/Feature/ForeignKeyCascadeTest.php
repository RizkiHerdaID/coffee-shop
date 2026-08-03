<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\WasteReason;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\Wastage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DB-level foreign key behavior for audit tables (Vikunja 140).
 *
 * Audit rows must survive the deletion of their admin; suppliers and stock
 * items that have related rows must be protected by a FK restriction. These
 * tests delete via the query builder on purpose: they pin the DATABASE
 * contract, not any Eloquent guard.
 */
class ForeignKeyCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_admin_keeps_audit_rows_and_nulls_admin_id(): void
    {
        $admin = Admin::factory()->create();
        $stockItem = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 1000,
            'min_threshold' => 500,
        ]);

        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'AUDIT-001',
            'status' => OrderStatus::Paid,
            'total' => 25000,
            'created_by' => $admin->id,
        ]));

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 25000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        $shift = Shift::create([
            'opened_at' => now()->subHours(2),
            'opening_cash' => 100000,
            'admin_id' => $admin->id,
        ]);

        $movement = ShiftCashMovement::create([
            'shift_id' => $shift->id,
            'type' => 'in',
            'amount' => 50000,
            'admin_id' => $admin->id,
        ]);

        $wastage = Wastage::create([
            'stock_item_id' => $stockItem->id,
            'quantity' => 5,
            'reason' => WasteReason::Spilled,
            'admin_id' => $admin->id,
            'recorded_at' => now(),
        ]);

        DB::table('admins')->where('id', $admin->id)->delete();

        $this->assertDatabaseMissing('admins', ['id' => $admin->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'created_by' => null]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'admin_id' => null]);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'admin_id' => null]);
        $this->assertDatabaseHas('shift_cash_movements', ['id' => $movement->id, 'admin_id' => null]);
        $this->assertDatabaseHas('wastages', ['id' => $wastage->id, 'admin_id' => null]);
    }

    public function test_deleting_supplier_with_purchase_orders_is_restricted_at_db_level(): void
    {
        $supplier = Supplier::create([
            'name' => 'PT Kopi Nusantara',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
            'note' => 'Supplier utama',
        ]);
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Pending,
            'total' => 50000,
        ]);

        try {
            DB::table('suppliers')->where('id', $supplier->id)->delete();
            $this->fail('Expected a foreign key restriction when deleting a supplier that has purchase orders.');
        } catch (QueryException) {
            // FK constraint failed: the audit trail is protected.
        }

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);
    }

    public function test_deleting_supplier_without_purchase_orders_is_allowed_at_db_level(): void
    {
        $supplier = Supplier::create([
            'name' => 'PT Kopi Nusantara',
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka 1',
            'note' => 'Supplier utama',
        ]);

        DB::table('suppliers')->where('id', $supplier->id)->delete();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_deleting_stock_item_with_movements_is_restricted_at_db_level(): void
    {
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 1000,
            'min_threshold' => 500,
        ]);
        $item->stockIn(50);

        try {
            DB::table('stock_items')->where('id', $item->id)->delete();
            $this->fail('Expected a foreign key restriction when deleting a stock item that has movements.');
        } catch (QueryException) {
            // FK constraint failed: the movement history is protected.
        }

        $this->assertDatabaseHas('stock_items', ['id' => $item->id]);
        $this->assertDatabaseHas('stock_movements', ['stock_item_id' => $item->id]);
    }

    public function test_deleting_stock_item_without_movements_is_allowed_at_db_level(): void
    {
        $item = StockItem::create([
            'name' => 'Biji Kopi',
            'unit' => 'gram',
            'quantity' => 0,
            'min_threshold' => 500,
        ]);

        DB::table('stock_items')->where('id', $item->id)->delete();

        $this->assertDatabaseMissing('stock_items', ['id' => $item->id]);
    }

    public function test_deleting_order_at_db_level_still_cascades_its_payments(): void
    {
        // Documented contract: orders are undeletable IN-APP (model guard),
        // but the payments.order_id FK intentionally keeps its DB cascade —
        // a payment can never outlive its order at the database level.
        $admin = Admin::factory()->create();
        $order = Order::withoutEvents(fn () => Order::create([
            'order_number' => 'AUDIT-002',
            'status' => OrderStatus::Paid,
            'total' => 25000,
            'created_by' => $admin->id,
        ]));
        Payment::create([
            'order_id' => $order->id,
            'method' => 'cash',
            'amount' => 25000,
            'paid_at' => now(),
            'admin_id' => $admin->id,
        ]);

        DB::table('orders')->where('id', $order->id)->delete();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseCount('payments', 0);
    }
}
