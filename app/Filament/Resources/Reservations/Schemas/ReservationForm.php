<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Enums\ReservationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class ReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('reservation.fields.name'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label(__('reservation.fields.phone'))
                    ->tel()
                    ->required()
                    ->maxLength(20),
                TextInput::make('party_size')
                    ->label(__('reservation.fields.party_size'))
                    ->numeric()
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->maxValue(100),
                DatePicker::make('date')
                    ->label(__('reservation.fields.date'))
                    ->required()
                    ->minDate(today()),
                TimePicker::make('time')
                    ->label(__('reservation.fields.time'))
                    ->required(),
                Textarea::make('notes')
                    ->label(__('reservation.fields.notes'))
                    ->rows(3)
                    ->maxLength(500),
                Select::make('status')
                    ->label(__('reservation.fields.status'))
                    ->options(ReservationStatus::class)
                    ->default(ReservationStatus::Pending)
                    ->required(),
            ]);
    }
}
