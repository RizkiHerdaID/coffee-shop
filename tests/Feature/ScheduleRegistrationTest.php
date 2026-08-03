<?php

namespace Tests\Feature;

use Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleRegistrationTest extends TestCase
{
    /**
     * @var string[] The exact schedule:list command signatures that must be
     *               registered in bootstrap/app.php's withSchedule().
     */
    private const REGISTERED_COMMANDS = [
        'summary:send --period=daily',
        'summary:send --period=weekly',
        'stock:alert-low',
        'pulse:check',
    ];

    public function test_schedule_list_registers_all_expected_commands(): void
    {
        Artisan::call('schedule:list', ['--no-interaction' => true]);

        $output = Artisan::output();

        foreach (self::REGISTERED_COMMANDS as $signature) {
            $this->assertStringContainsString(
                $signature,
                $output,
                "Scheduled command [{$signature}] missing from schedule:list.",
            );
        }
    }

    public function test_schedule_registrations_match_bootstrap_app_config(): void
    {
        $app = file_get_contents(base_path('bootstrap/app.php'));

        foreach (self::REGISTERED_COMMANDS as $signature) {
            $this->assertStringContainsString(
                $signature,
                $app,
                "Schedule registration for [{$signature}] missing from bootstrap/app.php.",
            );
        }
    }

    public function test_scheduled_events_use_bounded_mutex_expiry(): void
    {
        // The Laravel default mutex expiry is 1440 minutes: one crashed run
        // would suppress a full day of heartbeat pings/alerts. Both summary
        // events expire after 120 minutes, the high-frequency commands
        // after 60.
        $app = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertSame(
            2,
            substr_count($app, '->withoutOverlapping(120)'),
            'Both summary:send events must expire their mutex after 120 minutes.',
        );

        $this->assertSame(
            2,
            substr_count($app, '->withoutOverlapping(60)'),
            'stock:alert-low and pulse:check must expire their mutex after 60 minutes.',
        );
    }

    public function test_scheduled_events_apply_mutex_expiry_at_runtime(): void
    {
        // Runtime proof (not just file content): every registered event
        // carries the bounded mutex expiry so one crashed run can never
        // suppress a full day of heartbeat pings / alerts.
        Artisan::call('schedule:list', ['--no-interaction' => true]);

        $events = collect(app(Schedule::class)->events());

        $expiresAt = function (string $command) use ($events): ?int {
            $event = $events->first(fn ($event) => str_contains((string) $event->command, $command));

            return $event?->expiresAt;
        };

        $this->assertSame(120, $expiresAt('summary:send --period=daily'));
        $this->assertSame(120, $expiresAt('summary:send --period=weekly'));
        $this->assertSame(60, $expiresAt('stock:alert-low'));
        $this->assertSame(60, $expiresAt('pulse:check'));
    }

    public function test_weekly_summary_day_is_driven_by_config(): void
    {
        // The weekly summary day must come from config/summary.php (the
        // dead 'day' key is wired up), not a hardcoded weekday. The value
        // must be cron-valid: dragonmantank cron-expression rejects day
        // NAMES in the day-of-week field (weeklyOn('monday') throws).
        $this->assertIsInt(config('summary.weekly.day'));

        $app = file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString(
            "weeklyOn(config('summary.weekly.day'), config('summary.weekly.time'))",
            $app,
            'The weekly summary must take its day from config/summary.php.',
        );
        $this->assertStringNotContainsString('weeklyOn(1,', $app);
        $this->assertStringNotContainsString('->withoutOverlapping()', $app);
    }
}
