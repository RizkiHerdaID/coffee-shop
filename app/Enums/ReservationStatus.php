<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("reservation.status.{$this->value}");
    }
}
