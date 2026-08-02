<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WasteReason: string implements HasLabel
{
    case Spilled = 'spilled';
    case Expired = 'expired';
    case Damaged = 'damaged';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("wastage.reasons.{$this->value}");
    }
}
