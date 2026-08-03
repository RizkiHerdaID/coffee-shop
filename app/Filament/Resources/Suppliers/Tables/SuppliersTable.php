<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Models\Supplier;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('suppliers.fields.name'))
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->label(__('suppliers.fields.contact_person')),
                TextColumn::make('phone')
                    ->label(__('suppliers.fields.phone')),
                TextColumn::make('email')
                    ->label(__('suppliers.fields.email')),
                TextColumn::make('po_count')
                    ->label(__('suppliers.scorecard.orders_count'))
                    ->state(fn (Supplier $record): int => $record->poCount()),
                TextColumn::make('total_spend')
                    ->label(__('suppliers.scorecard.total_spend'))
                    ->state(fn (Supplier $record): int => $record->receivedTotal())
                    ->formatStateUsing(fn (int $state): string => 'Rp '.number_format($state, 0, ',', '.')),
                TextColumn::make('outstanding_count')
                    ->label(__('suppliers.scorecard.outstanding'))
                    ->state(fn (Supplier $record): int => $record->outstandingCount()),
                TextColumn::make('avg_lead_days')
                    ->label(__('suppliers.scorecard.avg_lead_time'))
                    ->state(fn (Supplier $record): ?float => $record->avgLeadDays())
                    ->formatStateUsing(fn (?float $state): string => $state === null
                        ? '—'
                        : number_format($state, 1, ',', '.').' '.__('suppliers.scorecard.days')),
                TextColumn::make('on_time_rate')
                    ->label(__('suppliers.scorecard.on_time_rate'))
                    ->state(fn (Supplier $record): ?int => $record->onTimeRate())
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.'%'),
                TextColumn::make('created_at')
                    ->label(__('suppliers.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('suppliers.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (Collection $records): bool => $records->isEmpty() || $records->contains(fn (Supplier $record): bool => ! $record->purchaseOrders()->exists())),
                ]),
            ]);
    }
}
