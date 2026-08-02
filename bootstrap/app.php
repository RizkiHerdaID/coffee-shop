<?php

use App\Http\Middleware\SecurityHeaders;
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
        // REQUIRED for HTTPS behind the Caddy proxy (host network, port 3004):
        // trusting X-Forwarded-Proto makes Laravel generate https:// asset and
        // form URLs. Without it, asset()/url() fall back to http:// and browsers
        // block the CSS as mixed content (site renders unstyled). Trust scopes:
        // - 127.0.0.1: direct loopback connections
        // - 172.16.0.0/12: Docker bridge range — the coffee-shop container's
        //   REMOTE_ADDR is the Docker bridge gateway (e.g. 172.19.0.1), NOT the
        //   client IP, because docker-proxy forwards the host-published port.
        // Do NOT widen this to '*' (review note in AGENTS.md); do NOT remove it,
        // the deploy script's asset check depends on https URLs.
        $middleware->trustProxies(
            at: ['127.0.0.1', '172.16.0.0/12'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
        // Redirect unauthenticated web guests (e.g. /pos/receipt) to the
        // Filament admin login — the route is registered by the admin panel.
        $middleware->redirectGuestsTo(fn (Request $request) => route('filament.admin.auth.login'));
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Optional scheduler heartbeat: when UPTIME_HEARTBEAT_URL is set, each
        // command pings it after a successful run (and never overlaps), so an
        // external monitor (healthchecks.io / Uptime Kuma) can alert when the
        // scheduler stops. Laravel 13 has no pingWithoutOverlapping(); the
        // withoutOverlapping() + pingOnSuccessIf() pair is the equivalent.
        $heartbeatUrl = config('uptime.heartbeat_url');

        $schedule->command('summary:send --period=daily')
            ->dailyAt(config('summary.daily.time'))
            ->withoutOverlapping()
            ->pingOnSuccessIf(filled($heartbeatUrl), $heartbeatUrl);

        $schedule->command('summary:send --period=weekly')
            ->weeklyOn(1, config('summary.weekly.time'))
            ->withoutOverlapping()
            ->pingOnSuccessIf(filled($heartbeatUrl), $heartbeatUrl);

        $schedule->command('stock:alert-low')
            ->hourly()
            ->withoutOverlapping()
            ->pingOnSuccessIf(filled($heartbeatUrl), $heartbeatUrl);

        $schedule->command('pulse:check')
            ->everyMinute()
            ->withoutOverlapping()
            ->pingOnSuccessIf(filled($heartbeatUrl), $heartbeatUrl);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
