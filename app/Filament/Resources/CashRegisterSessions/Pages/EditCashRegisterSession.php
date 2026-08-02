<?php

namespace App\Filament\Resources\CashRegisterSessions\Pages;

use App\Filament\Resources\CashRegisterSessions\CashRegisterSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCashRegisterSession extends EditRecord
{
    protected static string $resource = CashRegisterSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
