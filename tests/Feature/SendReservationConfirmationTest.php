<?php

namespace Tests\Feature;

use App\Jobs\SendReservationConfirmation;
use App\Models\Reservation;
use App\Services\FonnteWhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendReservationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_job_skips_when_whatsapp_is_disabled(): void
    {
        config(['whatsapp.enabled' => false]);

        SendReservationConfirmation::dispatchSync($this->makeReservation(), app()->getLocale());

        Http::assertNothingSent();
    }

    public function test_job_sends_confirmation_with_localized_date_for_indonesian_locale(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake([config('whatsapp.fonnte.url') => Http::response(['status' => true], 200)]);
        app()->setLocale('id');

        $reservation = $this->makeReservation();

        SendReservationConfirmation::dispatchSync($reservation, app()->getLocale());

        Http::assertSent(function (Request $request) use ($reservation): bool {
            return $request->url() === config('whatsapp.fonnte.url')
                && $request->hasHeader('Authorization', 'test-token')
                && $request['target'] === '6281234567890'
                && str_contains($request['message'], $reservation->name)
                && str_contains($request['message'], '19:30')
                && str_contains($request['message'], config('shop.name'))
                && ! str_contains($request['message'], ':name')
                && ! str_contains($request['message'], 'Aug');
        });
    }

    public function test_job_uses_english_month_when_app_locale_is_english(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake([config('whatsapp.fonnte.url') => Http::response(['status' => true], 200)]);
        app()->setLocale('en');

        SendReservationConfirmation::dispatchSync($this->makeReservation(), app()->getLocale());

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['message'], 'Aug');
        });
    }

    public function test_job_skips_silently_when_reservation_no_longer_exists(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake([config('whatsapp.fonnte.url') => Http::response(['status' => true], 200)]);
        $reservation = $this->makeReservation();

        DB::table('reservations')->where('id', $reservation->id)->delete();

        (new SendReservationConfirmation($reservation, app()->getLocale()))
            ->handle(app(FonnteWhatsApp::class));

        Http::assertNothingSent();
    }

    public function test_job_renders_message_in_locale_captured_at_dispatch(): void
    {
        // The locale is captured when the job is constructed (dispatch
        // time); the queue worker's default locale must not leak in.
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake([config('whatsapp.fonnte.url') => Http::response(['status' => true], 200)]);
        $reservation = $this->makeReservation();

        app()->setLocale('en');
        $job = new SendReservationConfirmation($reservation, 'en');
        app()->setLocale('id');
        $job->handle(app(FonnteWhatsApp::class));

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['message'], 'Hello Budi Santoso!')
                && ! str_contains($request['message'], 'Halo');
        });
    }

    public function test_reservation_dispatch_captures_current_locale(): void
    {
        // PageController::reservation must capture the request locale at
        // dispatch time; a queued job would otherwise render with the
        // worker's config-default locale.
        Queue::fake();
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        app()->setLocale('en');

        $this->post(url('/reservasi'), [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'party_size' => 2,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '19:30',
        ])->assertRedirect(url('/reservasi'));

        Queue::assertPushed(
            SendReservationConfirmation::class,
            fn (SendReservationConfirmation $job): bool => $job->locale === 'en',
        );
    }

    protected function makeReservation(array $attributes = []): Reservation
    {
        return Reservation::create([
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'party_size' => 2,
            'date' => '2026-08-15',
            'time' => '19:30',
            'notes' => 'Meja dekat jendela',
            ...$attributes,
        ]);
    }
}
