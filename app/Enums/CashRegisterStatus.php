<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CashRegisterStatus: string implements HasLabel
{
    case Open = 'open';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __("expenses.status.{$this->value}");
    }
}
