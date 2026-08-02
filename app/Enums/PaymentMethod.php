<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Ewallet = 'ewallet';

    public function getLabel(): string
    {
        return __("pos.payment.method.{$this->value}");
    }
}
