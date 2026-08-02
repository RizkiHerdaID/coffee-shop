<?php

namespace App\Filament\Pages;

use App\Models\Shift;
use Filament\Pages\Page;
use Filament\Panel;

class ShiftReport extends Page
{
    protected string $view = 'filament.pages.shift-report';

    protected static ?string $slug = 'shift-report';

    protected static bool $shouldRegisterNavigation = false;

    public ?Shift $shift = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/shift-report/{record}';
    }

    public function mount(int|string $record): void
    {
        $this->shift = Shift::query()
            ->with('admin')
            ->findOrFail($record);
    }

    public function getTitle(): string
    {
        return __('pos.zreport.title');
    }
}
