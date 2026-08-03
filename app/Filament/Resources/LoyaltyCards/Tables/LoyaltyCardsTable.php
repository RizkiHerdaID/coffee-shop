<?php

namespace App\Filament\Resources\LoyaltyCards\Tables;

use App\Models\LoyaltyCard;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoyaltyCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading(__('loyalty.empty.heading'))
            ->emptyStateDescription(__('loyalty.empty.description'))
            ->columns([
                TextColumn::make('phone')
                    ->label(__('loyalty.fields.phone'))
                    ->searchable(),
                TextColumn::make('stamps')
                    ->label(__('loyalty.fields.stamps'))
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('free_drinks')
                    ->label(__('loyalty.fields.free_drinks'))
                    ->badge()
                    ->color(fn (LoyaltyCard $record): string => $record->freeDrinksAvailable() > 0 ? 'success' : 'gray')
                    ->state(fn (LoyaltyCard $record): int => $record->freeDrinksAvailable()),
                TextColumn::make('redeemed')
                    ->label(__('loyalty.fields.redeemed'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('loyalty.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('loyalty.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('redeem')
                    ->label(__('loyalty.actions.redeem'))
                    ->icon('heroicon-o-gift')
                    ->modalHeading(__('loyalty.actions.redeem_heading'))
                    ->modalSubmitActionLabel(__('loyalty.actions.redeem_submit'))
                    ->modalDescription(__('loyalty.actions.redeem_confirm'))
                    ->successNotificationTitle(__('loyalty.notifications.redeemed'))
                    ->visible(fn (LoyaltyCard $record): bool => $record->freeDrinksAvailable() > 0)
                    ->requiresConfirmation()
                    ->action(function (LoyaltyCard $record): void {
                        if (! LoyaltyCard::redeem($record->phone)) {
                            throw new \Exception(__('loyalty.notifications.redeem_failed'));
                        }

                        $record->refresh();
                    }),
                Action::make('adjustStamps')
                    ->label(__('loyalty.actions.adjust'))
                    ->icon('heroicon-o-arrow-path')
                    ->modalHeading(__('loyalty.actions.adjust_heading'))
                    ->modalSubmitActionLabel(__('loyalty.actions.adjust_submit'))
                    ->modalDescription(__('loyalty.hints.adjust'))
                    ->successNotificationTitle(__('loyalty.notifications.adjusted'))
                    ->form([
                        TextInput::make('qty')
                            ->label(__('loyalty.actions.adjust_qty'))
                            ->mask(RawJs::make('(String($input).startsWith(\'-\') ? \'-\' : \'\') + $money(String($input).replace(\'-\', \'\'), \',\', \'.\', 0)'))
                            ->rule('regex:/^-?(\d{1,3}(\.\d{3})*|\d+)$/')
                            ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))
                            ->required()
                            ->helperText(__('loyalty.hints.adjust')),
                    ])
                    ->action(function (array $data, LoyaltyCard $record): void {
                        LoyaltyCard::adjustStamps($record->phone, (int) $data['qty']);

                        $record->refresh();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
