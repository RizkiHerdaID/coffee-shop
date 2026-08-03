<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact_person', 'phone', 'email', 'address', 'note'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Deleting a supplier would cascade-destroy its purchase orders and
        // the stock-in audit trail they carry.
        static::deleting(function (Supplier $supplier) {
            if ($supplier->purchaseOrders()->exists()) {
                throw new \RuntimeException('Suppliers with purchase orders cannot be deleted: the purchase order audit trail would be destroyed.');
            }
        });
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Total number of purchase orders, any status.
     */
    public function poCount(): int
    {
        return $this->purchaseOrders()->count();
    }

    /**
     * Sum of order totals across received purchase orders.
     */
    public function receivedTotal(): int
    {
        return (int) $this->purchaseOrders()
            ->where('status', PurchaseOrderStatus::Received)
            ->sum('total');
    }

    /**
     * Number of purchase orders still pending arrival.
     */
    public function outstandingCount(): int
    {
        return $this->purchaseOrders()
            ->where('status', PurchaseOrderStatus::Pending)
            ->count();
    }

    /**
     * Average lead time in days (ordered_at to received_at) across received
     * orders with both timestamps set, rounded to one decimal. Null when no
     * received order has a measurable lead time.
     */
    public function avgLeadDays(): ?float
    {
        $leadTimes = $this->purchaseOrders()
            ->where('status', PurchaseOrderStatus::Received)
            ->whereNotNull('ordered_at')
            ->whereNotNull('received_at')
            ->get()
            ->map(fn (PurchaseOrder $po): float => ($po->received_at->getTimestamp() - $po->ordered_at->getTimestamp()) / 86400);

        if ($leadTimes->isEmpty()) {
            return null;
        }

        return round($leadTimes->avg(), 1);
    }

    /**
     * Percentage of received orders that arrived on or before the expected
     * date (among received orders that have an expected date). Null when no
     * received order has an expected date.
     */
    public function onTimeRate(): ?int
    {
        $received = $this->purchaseOrders()
            ->where('status', PurchaseOrderStatus::Received)
            ->whereNotNull('expected_at')
            ->whereNotNull('received_at')
            ->get();

        if ($received->isEmpty()) {
            return null;
        }

        $onTime = $received->filter(fn (PurchaseOrder $po): bool => $po->received_at->startOfDay()->lessThanOrEqualTo($po->expected_at))->count();

        return (int) round($onTime / $received->count() * 100);
    }
}
