<?php

namespace App\Filament\Resources\LoyaltyCards\Pages;

use App\Filament\Resources\LoyaltyCards\LoyaltyCardsResource;
use App\Models\LoyaltyCard;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyCards extends ListRecords
{
    protected static string $resource = LoyaltyCardsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('grantStamps')
                ->label(__('loyalty.actions.grant'))
                ->icon('heroicon-o-plus')
                ->modalHeading(__('loyalty.actions.grant_heading'))
                ->modalSubmitActionLabel(__('loyalty.actions.grant_submit'))
                ->successNotificationTitle(__('loyalty.notifications.granted'))
                ->form([
                    TextInput::make('phone')
                        ->label(__('loyalty.fields.phone'))
                        ->tel()
                        ->required()
                        ->maxLength(20),
                    TextInput::make('qty')
                        ->label(__('loyalty.actions.grant_qty'))
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    LoyaltyCard::credit($data['phone'], (int) $data['qty']);
                }),
        ];
    }
}
