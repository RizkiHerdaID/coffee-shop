<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Exports\PurchaseOrderExporter;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ordered_at', 'desc')
            ->columns([
                TextColumn::make('supplier.name')
                    ->label(__('purchase-orders.fields.supplier'))
                    ->searchable(),
                TextColumn::make('ordered_at')
                    ->label(__('purchase-orders.fields.ordered_at'))
                    ->date(),
                TextColumn::make('expected_at')
                    ->label(__('purchase-orders.fields.expected_at'))
                    ->date(),
                TextColumn::make('status')
                    ->label(__('purchase-orders.fields.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state instanceof PurchaseOrderStatus ? $state->value : $state) {
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => __(
                        'purchase-orders.statuses.'.($state instanceof PurchaseOrderStatus ? $state->value : $state)
                    )),
                TextColumn::make('total')
                    ->label(__('purchase-orders.fields.total'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('note')
                    ->label(__('purchase-orders.fields.note')),
                TextColumn::make('created_at')
                    ->label(__('purchase-orders.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('purchase-orders.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('purchase-orders.fields.status'))
                    ->options(fn (): array => collect(PurchaseOrderStatus::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => __("purchase-orders.statuses.$case->value")])
                        ->all()),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label(__('purchase-orders.actions.receive'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->modalHeading(__('purchase-orders.actions.receive'))
                    ->modalDescription(__('purchase-orders.actions.receive_confirm'))
                    ->modalSubmitActionLabel(__('purchase-orders.actions.receive_submit'))
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === PurchaseOrderStatus::Pending)
                    ->action(function (PurchaseOrder $record): void {
                        $record->recalculateTotal();

                        $stocked = $record->receiveStock(__('purchase-orders.notifications.receive_note', ['id' => $record->id]));

                        $record->status = PurchaseOrderStatus::Received;
                        $record->save();

                        Notification::make()
                            ->title(__('purchase-orders.notifications.received_success', ['count' => $stocked]))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                ExportAction::make()
                    ->label(__('purchase-orders.actions.export'))
                    ->exporter(PurchaseOrderExporter::class),
            ]);
    }
}
