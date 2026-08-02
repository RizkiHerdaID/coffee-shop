<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label(__('orders.customer_phone'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'gray',
                        OrderStatus::Paid => 'success',
                        OrderStatus::Served => 'info',
                    }),
                TextColumn::make('total')
                    ->label(__('orders.total'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('paid_total')
                    ->label(__('pos.paid'))
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shift_id')
                    ->label(__('pos.shift.nav_label'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('admin.name')
                    ->label(__('orders.created_by'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('markPaid')
                    ->label(__('pos.actions.mark_paid'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Pending)
                    ->authorize(fn (Order $record): bool => auth('admin')->check() && $record->status === OrderStatus::Pending)
                    ->action(fn (Order $record) => $record->markPaidIfCovered())
                    ->successNotificationTitle(fn (Order $record) => __('pos.actions.marked_paid', ['order_number' => $record->order_number])),
                Action::make('markServed')
                    ->label(__('pos.actions.mark_served'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('info')
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::Paid)
                    ->authorize(fn (Order $record): bool => auth('admin')->check() && $record->status === OrderStatus::Paid)
                    ->action(fn (Order $record) => $record->update(['status' => OrderStatus::Served]))
                    ->successNotificationTitle(fn (Order $record) => __('pos.actions.marked_served', ['order_number' => $record->order_number])),
                Action::make('viewReceipt')
                    ->label(__('pos.actions.view_receipt'))
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Order $record): string => route('pos.receipt', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
