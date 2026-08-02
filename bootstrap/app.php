<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // REQUIRED for HTTPS behind the Caddy proxy (host network, 127.0.0.1:3004):
        // trusting X-Forwarded-Proto from the local proxy makes Laravel generate
        // https:// asset and form URLs. Without it, asset()/url() fall back to
        // http:// and browsers block the CSS as mixed content (site renders
        // unstyled). Scoped to 127.0.0.1 only — do NOT widen to '*' (review note
        // in AGENTS.md); do NOT remove, the deploy guard depends on https URLs.
        $middleware->trustProxies(
            at: '127.0.0.1',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->redirectGuestsTo(fn (Request $request) => route('admin.login'));
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('summary:send --period=daily')->dailyAt(config('summary.daily.time'));
        $schedule->command('summary:send --period=weekly')->weeklyOn(1, config('summary.weekly.time'));
        $schedule->command('stock:alert-low')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
