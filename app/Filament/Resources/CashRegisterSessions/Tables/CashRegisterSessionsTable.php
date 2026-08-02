<?php

namespace App\Filament\Resources\CashRegisterSessions\Tables;

use App\Enums\CashRegisterStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashRegisterSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->emptyStateHeading(__('expenses.empty.sessions_heading'))
            ->emptyStateDescription(__('expenses.empty.sessions_description'))
            ->columns([
                TextColumn::make('status')
                    ->label(__('expenses.fields.status'))
                    ->badge()
                    ->color(fn (CashRegisterStatus $state): string => $state === CashRegisterStatus::Open ? 'success' : 'gray'),
                TextColumn::make('opened_at')
                    ->label(__('expenses.fields.opened_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label(__('expenses.fields.closed_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('opening_float')
                    ->label(__('expenses.fields.opening_float'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('expected_amount')
                    ->label(__('expenses.fields.expected_amount'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('counted_amount')
                    ->label(__('expenses.fields.counted_amount'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('discrepancy')
                    ->label(__('expenses.fields.discrepancy'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('admin.name')
                    ->label(__('expenses.fields.admin'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('expenses.fields.created_at'))
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
