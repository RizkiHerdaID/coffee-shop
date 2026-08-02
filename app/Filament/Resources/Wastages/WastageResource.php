<?php

namespace App\Filament\Resources\Wastages;

use App\Filament\Resources\Wastages\Pages\CreateWastage;
use App\Filament\Resources\Wastages\Pages\EditWastage;
use App\Filament\Resources\Wastages\Pages\ListWastages;
use App\Filament\Resources\Wastages\Schemas\WastageForm;
use App\Filament\Resources\Wastages\Tables\WastagesTable;
use App\Models\Wastage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WastageResource extends Resource
{
    protected static ?string $model = Wastage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    public static function getModelLabel(): string
    {
        return __('wastage.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('wastage.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('wastage.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return WastageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WastagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWastages::route('/'),
            'create' => CreateWastage::route('/create'),
            'edit' => EditWastage::route('/{record}/edit'),
        ];
    }
}
