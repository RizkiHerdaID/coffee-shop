<?php

namespace App\Filament\Resources\LoyaltyCards;

use App\Filament\Resources\LoyaltyCards\Pages\ListLoyaltyCards;
use App\Filament\Resources\LoyaltyCards\Tables\LoyaltyCardsTable;
use App\Models\LoyaltyCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LoyaltyCardsResource extends Resource
{
    protected static ?string $model = LoyaltyCard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    public static function getModelLabel(): string
    {
        return __('loyalty.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return LoyaltyCardsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyCards::route('/'),
        ];
    }
}
