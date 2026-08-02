<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    /**
     * Orders attached to a closed shift are frozen: editing them would
     * mutate the already-generated Z-report. Block the save with a
     * notification instead of silently changing history.
     */
    protected function beforeSave(): void
    {
        if ($this->record->shift?->closed_at !== null) {
            Notification::make()
                ->title(__('orders.shift_closed_edit_blocked'))
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
