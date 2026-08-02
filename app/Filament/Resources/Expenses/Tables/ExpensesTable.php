<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Enums\ExpenseCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('spent_at', 'desc')
            ->emptyStateHeading(__('expenses.empty.expenses_heading'))
            ->emptyStateDescription(__('expenses.empty.expenses_description'))
            ->columns([
                TextColumn::make('category')
                    ->label(__('expenses.fields.category'))
                    ->badge()
                    ->color(fn (ExpenseCategory $state): string => match ($state) {
                        ExpenseCategory::Ingredients => 'success',
                        ExpenseCategory::Supplies => 'info',
                        ExpenseCategory::Utilities => 'warning',
                        ExpenseCategory::Equipment => 'danger',
                        ExpenseCategory::Marketing => 'primary',
                        ExpenseCategory::Salaries => 'success',
                        ExpenseCategory::Rent => 'info',
                        ExpenseCategory::Other => 'gray',
                    }),
                TextColumn::make('description')
                    ->label(__('expenses.fields.description'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('amount')
                    ->label(__('expenses.fields.amount'))
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('spent_at')
                    ->label(__('expenses.fields.spent_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('note')
                    ->label(__('expenses.fields.note'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('expenses.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('expenses.fields.updated_at'))
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
