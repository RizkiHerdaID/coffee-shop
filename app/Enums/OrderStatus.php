<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Served = 'served';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __("pos.status.{$this->value}");
    }
}
