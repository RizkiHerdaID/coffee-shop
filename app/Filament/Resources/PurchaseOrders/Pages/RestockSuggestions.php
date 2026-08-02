<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RestockSuggestions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = PurchaseOrderResource::class;

    protected string $view = 'filament.pages.restock-suggestions';

    protected static ?string $slug = 'restock';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('purchase-orders.restock.navigation');
    }

    public function getTitle(): string
    {
        return __('purchase-orders.restock.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => StockItem::query()->lowStock()->orderBy('quantity'))
            ->emptyStateHeading(__('purchase-orders.restock.empty_heading'))
            ->emptyStateDescription(__('purchase-orders.restock.empty_description'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('stock.fields.name'))
                    ->searchable(),
                TextColumn::make('unit')
                    ->label(__('stock.fields.unit')),
                TextColumn::make('quantity')
                    ->label(__('stock.fields.quantity'))
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (StockItem $record): string => number_format($record->quantity, 0, ',', '.').' '.__('stock.badges.low')),
                TextColumn::make('min_threshold')
                    ->label(__('stock.fields.min_threshold'))
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('suggested')
                    ->label(__('stock.restock.suggested_quantity'))
                    ->formatStateUsing(fn (StockItem $record): string => number_format(max(0, $record->min_threshold * 2 - $record->quantity), 0, ',', '.')),
                TextColumn::make('cost')
                    ->label(__('stock.fields.cost'))
                    ->money('IDR'),
            ])
            ->toolbarActions([
                Action::make('createPo')
                    ->label(__('purchase-orders.restock.create_po'))
                    ->icon('heroicon-o-plus')
                    ->url(PurchaseOrderResource::getUrl('create')),
                Action::make('manageStock')
                    ->label(__('purchase-orders.restock.manage_stock'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->url(StockItemResource::getUrl('index')),
            ]);
    }
}
