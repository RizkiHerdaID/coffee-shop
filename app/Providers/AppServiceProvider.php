<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Export::polymorphicUserRelationship();

        Order::observe(OrderObserver::class);

        $this->app->booted(fn () => Gate::define('viewPulse', fn (): bool => auth('admin')->check()));
    }
}
