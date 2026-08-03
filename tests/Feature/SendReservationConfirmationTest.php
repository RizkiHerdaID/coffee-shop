<?php

namespace Tests\Feature;

use App\Jobs\SendReservationConfirmation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

        SendReservationConfirmation::dispatchSync($this->makeReservation());

        Http::assertNothingSent();
    }

    public function test_job_sends_confirmation_with_localized_date_for_indonesian_locale(): void
    {
        config(['whatsapp.enabled' => true, 'whatsapp.fonnte.token' => 'test-token']);
        Http::fake();
        app()->setLocale('id');

        $reservation = $this->makeReservation();

        SendReservationConfirmation::dispatchSync($reservation);

        Http::assertSent(function (Request $request) use ($reservation): bool {
            return $request->url() === config('whatsapp.fonnte.url')
                && $request->hasHeader('Authorization', 'test-token')
                && $request['target'] === $reservation->phone
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
        Http::fake();
        app()->setLocale('en');

        SendReservationConfirmation::dispatchSync($this->makeReservation());

        Http::assertSent(function (Request $request): bool {
            return str_contains($request['message'], 'Aug');
        });
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
