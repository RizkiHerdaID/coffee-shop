<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Cashier;
use App\Filament\Pages\ManageShift;
use App\Filament\Pages\PnlReport;
use App\Filament\Pages\ShiftReport;
use App\Filament\Widgets\BestSellersChart;
use App\Filament\Widgets\DemandForecastWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\PaymentSplitChart;
use App\Filament\Widgets\PeakHoursChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\TodayStats;
use App\Filament\Widgets\TopItemsChart;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->authGuard('admin')
            ->brandName('Coffee Shop')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                Cashier::class,
                ManageShift::class,
                PnlReport::class,
                ShiftReport::class,
            ])
            ->widgets([
                AccountWidget::class,
                TodayStats::class,
                LowStockWidget::class,
                RevenueChart::class,
                TopItemsChart::class,
                BestSellersChart::class,
                PeakHoursChart::class,
                DemandForecastWidget::class,
                PaymentSplitChart::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
