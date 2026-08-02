<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receive')
                ->label(__('purchase-orders.actions.receive'))
                ->icon('heroicon-o-arrow-down-tray')
                ->modalHeading(__('purchase-orders.actions.receive'))
                ->modalDescription(__('purchase-orders.actions.receive_confirm'))
                ->modalSubmitActionLabel(__('purchase-orders.actions.receive_submit'))
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === PurchaseOrderStatus::Pending)
                ->action(function (): void {
                    $this->record->recalculateTotal();

                    $stocked = $this->record->receiveStock(__('purchase-orders.notifications.receive_note', ['id' => $this->record->id]));

                    $this->record->status = PurchaseOrderStatus::Received;
                    $this->record->received_at = now();
                    $this->record->save();

                    Notification::make()
                        ->title(__('purchase-orders.notifications.received_success', ['count' => $stocked]))
                        ->success()
                        ->send();

                    $this->redirect(PurchaseOrderResource::getUrl('index'));
                }),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotal();
    }
}
