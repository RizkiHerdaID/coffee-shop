<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Enums\ReservationStatus;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

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
                    ->required()
                    ->rule(function (TimePicker $component): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                            // Mirror the public booking form: same-day
                            // reservations must be for a time still ahead.
                            $date = $component->getContainer()->getComponent('date')?->getState();

                            if (blank($date) || blank($value)) {
                                return;
                            }

                            $reservationDateTime = Carbon::parse($date.' '.$value);

                            if ($reservationDateTime->isToday() && $reservationDateTime->isPast()) {
                                $fail(__('reservation.form.past_time'));
                            }
                        };
                    }),
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
