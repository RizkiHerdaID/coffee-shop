<?php

namespace App\Filament\Resources\CashRegisterSessions\Tables;

use App\Enums\CashRegisterStatus;
use App\Models\CashRegisterSession;
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
            ->emptyStateHeading(__('cash-register-sessions.empty.sessions_heading'))
            ->emptyStateDescription(__('cash-register-sessions.empty.sessions_description'))
            ->columns([
                TextColumn::make('status')
                    ->label(__('cash-register-sessions.fields.status'))
                    ->badge()
                    ->color(fn (CashRegisterStatus $state): string => $state === CashRegisterStatus::Open ? 'success' : 'gray'),
                TextColumn::make('opened_at')
                    ->label(__('cash-register-sessions.fields.opened_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label(__('cash-register-sessions.fields.closed_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('opening_float')
                    ->label(__('cash-register-sessions.fields.opening_float'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('expected_amount')
                    ->label(__('cash-register-sessions.fields.expected_amount'))
                    ->state(fn (CashRegisterSession $record): int => $record->expectedAmount())
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('counted_amount')
                    ->label(__('cash-register-sessions.fields.counted_amount'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('discrepancy')
                    ->label(__('cash-register-sessions.fields.discrepancy'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('admin.name')
                    ->label(__('cash-register-sessions.fields.admin'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('cash-register-sessions.fields.created_at'))
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
