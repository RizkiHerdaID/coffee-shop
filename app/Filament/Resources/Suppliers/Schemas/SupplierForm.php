<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('suppliers.fields.name'))
                    ->required(),
                TextInput::make('contact_person')
                    ->label(__('suppliers.fields.contact_person')),
                TextInput::make('phone')
                    ->label(__('suppliers.fields.phone')),
                TextInput::make('email')
                    ->label(__('suppliers.fields.email'))
                    ->email(),
                Textarea::make('address')
                    ->label(__('suppliers.fields.address')),
                Textarea::make('note')
                    ->label(__('suppliers.fields.note')),
            ]);
    }
}
