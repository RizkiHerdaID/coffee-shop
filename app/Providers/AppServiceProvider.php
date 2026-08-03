<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Public loyalty lookups are phone-enumeration surfaces (the response
        // differentiates found vs not-found): bound the per-IP rate so a script
        // cannot verify registered numbers at high speed. Must be registered in
        // a provider boot — the bootstrap/app.php withMiddleware closure runs
        // before any service provider registers, so facades and the cache
        // store are not resolvable there.
        RateLimiter::for('points', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        Export::polymorphicUserRelationship();

        Order::observe(OrderObserver::class);

        $this->app->booted(fn () => Gate::define('viewPulse', fn (): bool => auth('admin')->check()));
    }
}
