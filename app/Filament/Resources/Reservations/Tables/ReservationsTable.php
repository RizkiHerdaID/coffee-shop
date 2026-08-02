<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\ReservationStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->emptyStateHeading(__('reservation.empty.heading'))
            ->emptyStateDescription(__('reservation.empty.description'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('reservation.fields.name'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('reservation.fields.phone'))
                    ->searchable(),
                TextColumn::make('party_size')
                    ->label(__('reservation.fields.party_size'))
                    ->badge(),
                TextColumn::make('date')
                    ->label(__('reservation.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('time')
                    ->label(__('reservation.fields.time'))
                    ->time('H:i'),
                TextColumn::make('status')
                    ->label(__('reservation.fields.status'))
                    ->badge()
                    ->color(fn (ReservationStatus $state): string => match ($state) {
                        ReservationStatus::Pending => 'warning',
                        ReservationStatus::Confirmed => 'success',
                        ReservationStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('notes')
                    ->label(__('reservation.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('reservation.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('reservation.actions.edit')),
                Action::make('confirm')
                    ->label(__('reservation.actions.confirm'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn ($record): bool => $record->status === ReservationStatus::Pending)
                    ->action(fn ($record) => $record->update(['status' => ReservationStatus::Confirmed]))
                    ->successNotificationTitle(__('reservation.notifications.confirmed')),
                Action::make('cancel')
                    ->label(__('reservation.actions.cancel'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => $record->status !== ReservationStatus::Cancelled)
                    ->action(fn ($record) => $record->update(['status' => ReservationStatus::Cancelled]))
                    ->successNotificationTitle(__('reservation.notifications.cancelled')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
