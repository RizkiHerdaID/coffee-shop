<?php

namespace App\Filament\Resources\CashRegisterSessions\Pages;

use App\Filament\Resources\CashRegisterSessions\CashRegisterSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashRegisterSessions extends ListRecords
{
    protected static string $resource = CashRegisterSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
