<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionFunction;
use ReflectionProperty;
use Tests\TestCase;

class UptimeMonitorTest extends TestCase
{
    /** @var string[] The scheduled command needles this project must guard against overlap. */
    private const COMMANDS = ['summary:send', 'stock:alert-low', 'pulse:check'];

    protected function scheduledEvents(): array
    {
        // bootstrap/app.php registers the schedule via withSchedule(), which in
        // Laravel 13 only wires the events when the Artisan console application
        // starts (Artisan::starting). Boot it before inspecting the Schedule.
        $this->artisan('help');

        return app(Schedule::class)->events();
    }

    protected function eventFor(string $needle): Event
    {
        foreach ($this->scheduledEvents() as $event) {
            if (str_contains((string) $event->command, $needle)) {
                return $event;
            }
        }

        $this->fail("No scheduled event found for [{$needle}].");
    }

    /**
     * Extract the URLs referenced by the event's ping callbacks. Laravel 13's
     * ping helpers (pingBefore/thenPing/pingOnSuccess/pingOnFailure) capture the
     * URL as a static variable of a Closure stored in the callback lists;
     * pingOnSuccess/pingOnFailure wrap that Closure in another one, so the
     * search recurses one level into Closure-typed static variables.
     *
     * @return string[]
     */
    protected function pingUrlsOf(Event $event): array
    {
        $urls = [];

        foreach (['beforeCallbacks', 'afterCallbacks'] as $property) {
            $callbacks = (new ReflectionProperty($event, $property))->getValue($event);

            foreach ($callbacks as $callback) {
                $this->collectPingUrls($callback, $urls);
            }
        }

        return array_values(array_unique($urls));
    }

    protected function collectPingUrls(\Closure $callback, array &$urls): void
    {
        $staticVars = (new ReflectionFunction($callback))->getStaticVariables();

        if (isset($staticVars['url']) && is_string($staticVars['url'])) {
            $urls[] = $staticVars['url'];
        }

        foreach ($staticVars as $var) {
            if ($var instanceof \Closure) {
                $this->collectPingUrls($var, $urls);
            }
        }
    }

    public function test_health_endpoint_returns_200_for_plain_requests(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_reports_status_up_as_json(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertExactJson(['status' => 'up']);
    }

    public function test_heartbeat_config_key_defaults_to_null(): void
    {
        $this->assertNull(config('uptime.heartbeat_url'));
    }

    public function test_all_scheduled_commands_are_non_overlapping(): void
    {
        foreach (self::COMMANDS as $command) {
            $event = $this->eventFor($command);

            $this->assertTrue(
                $event->withoutOverlapping,
                "Scheduled event [{$event->command}] must run without overlapping (mutex), got withoutOverlapping=".var_export($event->withoutOverlapping, true),
            );
        }
    }

    public function test_scheduled_commands_ping_the_configured_heartbeat_url(): void
    {
        $heartbeat = 'https://hc-ping.example/coffee-shop/123456';

        config(['uptime.heartbeat_url' => $heartbeat]);

        foreach (self::COMMANDS as $command) {
            $event = $this->eventFor($command);

            $this->assertContains(
                $heartbeat,
                $this->pingUrlsOf($event),
                "Scheduled event [{$event->command}] must ping the configured heartbeat URL.",
            );
        }
    }

    public function test_scheduled_commands_ping_nothing_when_heartbeat_is_unconfigured(): void
    {
        config(['uptime.heartbeat_url' => null]);

        foreach (self::COMMANDS as $command) {
            $event = $this->eventFor($command);

            $this->assertSame(
                [],
                $this->pingUrlsOf($event),
                "Scheduled event [{$event->command}] must not ping a URL when heartbeat config is null.",
            );
        }
    }
}
