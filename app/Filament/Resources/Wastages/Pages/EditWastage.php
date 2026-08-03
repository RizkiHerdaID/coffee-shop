<?php

namespace App\Filament\Resources\Wastages\Pages;

use App\Filament\Resources\Wastages\WastageResource;
use App\Models\StockItem;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditWastage extends EditRecord
{
    protected static string $resource = WastageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $original = static::getModel()::query()->lockForUpdate()->find($record->getKey());

            $oldStockItemId = (int) ($original?->stock_item_id ?? $record->getOriginal('stock_item_id'));
            $oldQuantity = (int) ($original?->quantity ?? $record->getOriginal('quantity'));
            $newStockItemId = (int) $data['stock_item_id'];
            $newQuantity = (int) $data['quantity'];

            $movementNote = __('wastage.notifications.movement_edit_note', ['id' => $record->getKey()]);

            if ($newStockItemId === $oldStockItemId) {
                $this->adjustSameItemStock($oldQuantity, $newQuantity, $newStockItemId, $movementNote);
            } else {
                $this->adjustSwappedItemStock($oldStockItemId, $oldQuantity, $newStockItemId, $newQuantity, $movementNote);
            }

            $record->update($data);

            return $record;
        });
    }

    private function adjustSameItemStock(int $oldQuantity, int $newQuantity, int $stockItemId, string $movementNote): void
    {
        $delta = $newQuantity - $oldQuantity;

        if ($delta === 0) {
            return;
        }

        $stockItem = StockItem::query()->lockForUpdate()->find($stockItemId);

        if ($stockItem === null) {
            return;
        }

        if ($delta > 0) {
            if ($stockItem->quantity < $delta) {
                throw ValidationException::withMessages([
                    'data.quantity' => __('wastage.validation.quantity_exceeds_stock'),
                ]);
            }

            $stockItem->stockOut($delta, $movementNote);

            return;
        }

        $stockItem->stockIn(-$delta, $movementNote);
    }

    private function adjustSwappedItemStock(int $oldStockItemId, int $oldQuantity, int $newStockItemId, int $newQuantity, string $movementNote): void
    {
        $stockItem = StockItem::query()->lockForUpdate()->find($newStockItemId);

        if ($stockItem === null || $stockItem->quantity < $newQuantity) {
            throw ValidationException::withMessages([
                'data.quantity' => __('wastage.validation.quantity_exceeds_stock'),
            ]);
        }

        StockItem::query()->find($oldStockItemId)?->stockIn($oldQuantity, $movementNote);

        $stockItem->stockOut($newQuantity, $movementNote);
    }
}
