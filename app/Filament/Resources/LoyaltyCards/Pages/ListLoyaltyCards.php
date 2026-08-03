<?php

namespace App\Filament\Resources\LoyaltyCards\Pages;

use App\Filament\Resources\LoyaltyCards\LoyaltyCardsResource;
use App\Models\LoyaltyCard;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\RawJs;

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
                        ->maxLength(255),
                    TextInput::make('qty')
                        ->label(__('loyalty.actions.grant_qty'))
                        ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                        ->rule('regex:/^(\d{1,3}(\.\d{3})*|\d+)$/')
                        ->dehydrateStateUsing(fn ($state) => (int) str_replace('.', '', (string) $state))
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
