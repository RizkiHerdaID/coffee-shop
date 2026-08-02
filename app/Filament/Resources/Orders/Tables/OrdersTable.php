<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('shift'))
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
                        OrderStatus::Refunded => 'danger',
                        OrderStatus::Cancelled => 'gray',
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
                Action::make('refund')
                    ->label(__('pos.actions.refund'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->visible(fn (Order $record): bool => $record->canBeRefunded())
                    ->authorize(fn (Order $record): bool => auth('admin')->check() && $record->canBeRefunded())
                    ->modalHeading(__('pos.refund.title'))
                    ->modalSubmitActionLabel(__('pos.actions.refund'))
                    ->successNotificationTitle(fn (Order $record) => __('pos.actions.refunded', ['order_number' => $record->order_number]))
                    ->schema([
                        TextInput::make('amount')
                            ->label(__('pos.refund.amount'))
                            ->required()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                            ->formatStateUsing(fn ($state) => $state === null || $state === '' ? $state : (is_string($state) && str_contains($state, '.') ? $state : number_format((int) $state, 0, ',', '.')))
                            ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))
                            ->rule(function (TextInput $component): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                                    $record = $component->getRecord();
                                    $amount = (int) str_replace('.', '', (string) $value);

                                    if ($record instanceof Order && $amount > $record->paid_total) {
                                        $fail(__('pos.refund.exceeds_paid'));
                                    }
                                };
                            }),
                        Select::make('method')
                            ->label(__('pos.refund.method'))
                            ->options(PaymentMethod::class)
                            ->default(PaymentMethod::Cash),
                        Textarea::make('reason')
                            ->label(__('pos.refund.reason'))
                            ->placeholder(__('pos.refund.reason_placeholder'))
                            ->rows(3)
                            ->maxLength(255),
                    ])
                    ->action(function (Action $action, array $data, Order $record): void {
                        if (! $record->refund(
                            (int) $data['amount'],
                            $data['method'],
                            filled($data['reason']) ? $data['reason'] : null,
                        )) {
                            Notification::make()
                                ->title(__('pos.refund.failed'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
                Action::make('void')
                    ->label(__('pos.actions.void'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (Order $record): bool => $record->canBeVoided())
                    ->authorize(fn (Order $record): bool => auth('admin')->check() && $record->canBeVoided())
                    ->requiresConfirmation()
                    ->modalHeading(__('pos.void.title'))
                    ->modalDescription(__('pos.void.confirm'))
                    ->successNotificationTitle(fn (Order $record) => __('pos.actions.voided', ['order_number' => $record->order_number]))
                    ->action(function (Action $action, Order $record): void {
                        if (! $record->void()) {
                            Notification::make()
                                ->title(__('pos.void.failed'))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
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
