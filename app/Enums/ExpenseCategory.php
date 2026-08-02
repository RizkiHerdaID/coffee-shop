<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExpenseCategory: string implements HasLabel
{
    case Ingredients = 'ingredients';
    case Supplies = 'supplies';
    case Utilities = 'utilities';
    case Equipment = 'equipment';
    case Marketing = 'marketing';
    case Salaries = 'salaries';
    case Rent = 'rent';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("expenses.categories.{$this->value}");
    }
}
