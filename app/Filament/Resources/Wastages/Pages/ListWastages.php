<?php

namespace App\Filament\Resources\Wastages\Pages;

use App\Filament\Resources\Wastages\WastageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWastages extends ListRecords
{
    protected static string $resource = WastageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('wastage.actions.create')),
        ];
    }
}
