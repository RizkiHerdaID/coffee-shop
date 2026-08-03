<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['supplier_id', 'ordered_at', 'expected_at', 'received_at', 'status', 'total', 'note'])]
class PurchaseOrder extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'total' => 'integer',
            'ordered_at' => 'date',
            'expected_at' => 'date',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Recalculate the order total from its line items (quantity × unit price).
     */
    public function recalculateTotal(): void
    {
        $total = (int) $this->items()->sum(DB::raw('quantity * unit_price'));

        if ($total > 0) {
            $this->total = $total;
            $this->save();
        }
    }

    /**
     * Stock in every linked line item. Returns how many lines were stocked.
     */
    public function receiveStock(?string $note = null): int
    {
        $stocked = 0;

        foreach ($this->items as $item) {
            $stockItem = $item->stockItem;

            if ($stockItem === null || ! $stockItem->stockIn($item->quantity, $note)) {
                continue;
            }

            $stocked++;
        }

        return $stocked;
    }

    /**
     * Atomically receive the order: the row is locked, the received state is
     * re-checked under the lock (so a concurrent receive can never stock twice),
     * and an order whose line items sum to zero is refused. Returns how many
     * lines were stocked, or 0 when nothing happened (already received,
     * cancelled, or zero-total order — in which case status is left untouched).
     */
    public function receive(?string $note = null): int
    {
        return DB::transaction(function () use ($note): int {
            $locked = static::query()->lockForUpdate()->find($this->getKey());

            if ($locked === null || $locked->status !== PurchaseOrderStatus::Pending || $locked->received_at !== null) {
                return 0;
            }

            $total = (int) $locked->items()->sum(DB::raw('quantity * unit_price'));

            if ($total < 1) {
                return 0;
            }

            $stocked = 0;

            foreach ($locked->items as $item) {
                $stockItem = $item->stockItem;

                if ($stockItem === null || ! $stockItem->stockIn($item->quantity, $note)) {
                    continue;
                }

                $stocked++;
            }

            $locked->total = $total;
            $locked->status = PurchaseOrderStatus::Received;
            $locked->received_at = now();
            $locked->save();

            return $stocked;
        });
    }
}
