<?php

namespace App\Filament\Resources\Wastages\Pages;

use App\Filament\Resources\Wastages\WastageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWastage extends EditRecord
{
    protected static string $resource = WastageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
