<?php

namespace App\Providers;

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

        $this->app->booted(fn () => Gate::define('viewPulse', fn (): bool => auth('admin')->check()));
    }
}
