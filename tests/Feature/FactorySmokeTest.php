<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Admin;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\LoyaltyCard;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Reservation;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Wastage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * All 16 domain-model factories are wired via the HasFactory trait and
 * interlock with each other (nested factories create their own required
 * relations). This guards against factory rot: a factory that stops
 * matching its model's fillable/columns breaks the suite at create() time
 * instead of rotting silently.
 */
class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_domain_factory_creates_its_model(): void
    {
        $menuItem = MenuItem::factory()->create();
        $stockItem = StockItem::factory()->create();
        $stockMovement = StockMovement::factory()->create(['stock_item_id' => $stockItem->id]);
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'stock_item_id' => $stockItem->id,
        ]);
        $expense = Expense::factory()->create();
        $shift = Shift::factory()->create();
        $shiftCashMovement = ShiftCashMovement::factory()->create(['shift_id' => $shift->id]);
        $order = Order::factory()->create();
        $orderItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
        ]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $cashRegisterSession = CashRegisterSession::factory()->create();
        $loyaltyCard = LoyaltyCard::factory()->create();
        $reservation = Reservation::factory()->create();
        $wastage = Wastage::factory()->create(['stock_item_id' => $stockItem->id]);

        $this->assertDatabaseHas('menu_items', ['id' => $menuItem->id]);
        $this->assertDatabaseHas('stock_items', ['id' => $stockItem->id]);
        $this->assertDatabaseHas('stock_movements', ['id' => $stockMovement->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id]);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $purchaseOrderItem->id]);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
        $this->assertDatabaseHas('shift_cash_movements', ['id' => $shiftCashMovement->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['id' => $orderItem->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('cash_register_sessions', ['id' => $cashRegisterSession->id]);
        $this->assertDatabaseHas('loyalty_cards', ['id' => $loyaltyCard->id]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseHas('wastages', ['id' => $wastage->id]);

        $this->assertSame($stockItem->id, $stockMovement->stock_item_id);
        $this->assertSame($supplier->id, $purchaseOrder->supplier_id);
        $this->assertSame($purchaseOrder->id, $purchaseOrderItem->purchase_order_id);
        $this->assertSame($shift->id, $shiftCashMovement->shift_id);
        $this->assertSame($order->id, $orderItem->order_id);
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame($stockItem->id, $wastage->stock_item_id);
    }

    public function test_factory_states_interlock_for_pos_and_purchasing_chains(): void
    {
        $admin = Admin::factory()->create();
        $shift = Shift::factory()->create(['admin_id' => $admin->id]);

        $paid = Order::factory()->onShift($shift)->paid()->create(['created_by' => $admin->id]);
        $this->assertSame(OrderStatus::Paid, $paid->status);
        $this->assertSame($shift->id, $paid->shift_id);
        $this->assertDatabaseHas('payments', ['order_id' => $paid->id, 'amount' => $paid->net_total]);

        $closed = Shift::factory()->closed()->create(['admin_id' => $admin->id]);
        $this->assertNotNull($closed->closed_at);
        $this->assertNotNull($closed->closing_cash);

        $deposit = ShiftCashMovement::factory()->deposit()->create(['shift_id' => $shift->id]);
        $pettyOut = ShiftCashMovement::factory()->pettyOut()->create(['shift_id' => $shift->id]);
        $this->assertTrue($deposit->isDeposit());
        $this->assertTrue($pettyOut->isPettyOut());

        $supplier = Supplier::factory()->create();
        $received = PurchaseOrder::factory()->received()->create(['supplier_id' => $supplier->id]);
        $this->assertSame(PurchaseOrderStatus::Received, $received->status);

        $stockItem = StockItem::factory()->create();
        $stockIn = StockMovement::factory()->stockIn()->create(['stock_item_id' => $stockItem->id]);
        $this->assertSame('in', $stockIn->type);
    }

    public function test_nested_factories_create_their_own_relations(): void
    {
        $orderItem = OrderItem::factory()->create();
        $this->assertNotNull($orderItem->order_id);
        $this->assertNotNull($orderItem->menu_item_id);

        $payment = Payment::factory()->create();
        $this->assertNotNull($payment->order_id);
        $this->assertNotNull($payment->admin_id);

        $stockMovement = StockMovement::factory()->stockOut()->create();
        $this->assertSame('out', $stockMovement->type);
        $this->assertNotNull($stockMovement->order_item_id);

        $wastage = Wastage::factory()->create();
        $this->assertNotNull($wastage->stock_item_id);
        $this->assertNotNull($wastage->admin_id);
    }
}
