<?php

namespace Tests\Feature;

use Artisan;
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
}
