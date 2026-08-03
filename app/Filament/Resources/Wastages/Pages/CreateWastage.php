<?php

namespace App\Filament\Resources\Wastages\Pages;

use App\Filament\Resources\Wastages\WastageResource;
use App\Models\StockItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateWastage extends CreateRecord
{
    protected static string $resource = WastageResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['admin_id'] = auth('admin')->id();

        return DB::transaction(function () use ($data): Model {
            $stockItem = StockItem::query()->lockForUpdate()->find($data['stock_item_id']);

            $quantity = (int) $data['quantity'];

            if ($stockItem === null || $stockItem->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'data.quantity' => __('wastage.validation.quantity_exceeds_stock'),
                ]);
            }

            $record = static::getModel()::create($data);

            if (! $stockItem->stockOut($quantity, __('wastage.notifications.movement_note', ['id' => $record->id]))) {
                throw ValidationException::withMessages([
                    'data.quantity' => __('wastage.validation.quantity_exceeds_stock'),
                ]);
            }

            return $record;
        });
    }
}
