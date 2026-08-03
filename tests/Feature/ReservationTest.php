<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\Pages\CreateReservation;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_page_renders_successfully(): void
    {
        $response = $this->get(url('/reservasi'));

        $response->assertOk();
    }

    public function test_reservation_page_shows_form_fields(): void
    {
        $response = $this->get(url('/reservasi'));

        $response->assertSee('name="name"', false);
        $response->assertSee('name="phone"', false);
        $response->assertSee('name="party_size"', false);
        $response->assertSee('name="date"', false);
        $response->assertSee('name="time"', false);
    }

    public function test_reservation_page_contains_honeypot_field(): void
    {
        $response = $this->get(url('/reservasi'));

        $response->assertOk();
        $response->assertSee('name="website"', false);
    }

    public function test_reservation_form_submits_and_stores_row(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'party_size' => 4,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '12:00',
            'notes' => 'Meja dekat jendela',
        ];

        $response = $this->post(url('/reservasi'), $payload);

        $response->assertRedirect(url('/reservasi'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'name' => 'Budi Santoso',
            'phone' => '6281234567890',
            'party_size' => 4,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '12:00',
            'notes' => 'Meja dekat jendela',
        ]);
    }

    public function test_reservation_form_requires_name(): void
    {
        $response = $this->post(url('/reservasi'), [
            'name' => '',
            'phone' => '081234567890',
            'party_size' => 2,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '12:00',
        ]);

        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_validates_phone_format(): void
    {
        $response = $this->post(url('/reservasi'), [
            'name' => 'Budi Santoso',
            'phone' => 'not-a-phone',
            'party_size' => 2,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '12:00',
        ]);

        $response->assertSessionHasErrors(['phone']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_rejects_zero_party_size(): void
    {
        $response = $this->post(url('/reservasi'), [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'party_size' => 0,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '12:00',
        ]);

        $response->assertSessionHasErrors(['party_size']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_status_is_string_backed_enum(): void
    {
        $reservation = Reservation::create([
            'name' => 'Siti Aminah',
            'phone' => '085712345678',
            'party_size' => 2,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '18:00',
            'status' => ReservationStatus::Confirmed,
        ]);

        $fresh = $reservation->fresh();

        $this->assertInstanceOf(ReservationStatus::class, $fresh->status);
        $this->assertSame('confirmed', $fresh->status->value);
        $this->assertDatabaseHas('reservations', ['status' => 'confirmed']);
    }

    public function test_reservation_form_rejects_filled_honeypot(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'website' => 'http://spam.example',
        ]);

        $response->assertSessionHasErrors(['website']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_rejects_past_time_on_today(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'date' => now()->format('Y-m-d'),
            'time' => '00:00',
        ]);

        $response->assertSessionHasErrors('time', __('reservation.form.past_time'));
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_accepts_future_time_on_today(): void
    {
        // Noon is inside every configured opening window (mon_fri 07:00-18:00,
        // sat 08:00-20:00, sun 08:00-16:00). When the suite runs after noon a
        // future same-day booking cannot be constructed, so skip the case.
        $proposed = now()->copy()->setTime(12, 0);

        if ($proposed->isPast()) {
            $this->markTestSkipped('A future same-day booking cannot be constructed after noon.');
        }

        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'date' => $proposed->format('Y-m-d'),
            'time' => $proposed->format('H:i'),
        ]);

        $response->assertRedirect(url('/reservasi'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_reservation_form_rejects_fractional_party_size(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'party_size' => '4.5',
        ]);

        $response->assertSessionHasErrors(['party_size']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_post_route_is_throttled_after_five_requests(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(url('/reservasi'), $this->validPayload());
        }

        $this->post(url('/reservasi'), $this->validPayload())->assertStatus(429);

        $this->assertDatabaseCount('reservations', 5);
    }

    public function test_admin_reservation_form_rejects_non_integer_party_size(): void
    {
        Livewire::test(CreateReservation::class)
            ->fillForm([
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'party_size' => 'abc',
                'date' => now()->addDay()->format('Y-m-d'),
                'time' => '19:30',
            ])
            ->call('create')
            ->assertHasFormErrors(['party_size']);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_rejects_time_outside_opening_hours(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'date' => now()->addDays(3)->format('Y-m-d'),
            'time' => '03:00',
        ]);

        $response->assertSessionHasErrors(['time' => __('reservation.form.closed')]);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_rejects_sunday_before_opening_hours(): void
    {
        $sunday = now()->next(Carbon::SUNDAY);

        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'date' => $sunday->format('Y-m-d'),
            'time' => '07:00',
        ]);

        $response->assertSessionHasErrors(['time' => __('reservation.form.closed')]);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_rejects_date_beyond_ninety_day_horizon(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'date' => now()->addMonths(6)->format('Y-m-d'),
            'time' => '12:00',
        ]);

        $response->assertSessionHasErrors(['date' => __('reservation.form.too_far')]);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_accepts_time_within_opening_hours(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'date' => now()->addDays(3)->format('Y-m-d'),
            'time' => '12:00',
        ]);

        $response->assertRedirect(url('/reservasi'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_reservation_form_accepts_dashed_phone_and_stores_normalized(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'phone' => '0812-3456-7890',
        ]);

        $response->assertRedirect(url('/reservasi'));
        $this->assertDatabaseHas('reservations', ['phone' => '6281234567890']);
        $this->assertDatabaseMissing('reservations', ['phone' => '0812-3456-7890']);
    }

    public function test_reservation_form_rejects_junk_phone_with_localized_message(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'phone' => 'not-a-phone',
        ]);

        $response->assertSessionHasErrors(['phone' => __('reservation.form.invalid_phone')]);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_form_rejects_phone_that_fails_normalized_check(): void
    {
        $response = $this->post(url('/reservasi'), [
            ...$this->validPayload(),
            'phone' => '08123',
        ]);

        $response->assertSessionHasErrors(['phone' => __('reservation.form.invalid_phone')]);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_success_redirect_preserves_lang_query(): void
    {
        $response = $this->post(url('/reservasi').'?lang=en', $this->validPayload());

        $response->assertRedirect(url('/reservasi?lang=en'));
        $response->assertSessionHas('success');
    }

    public function test_reservation_flash_message_varies_with_whatsapp_config(): void
    {
        config()->set('whatsapp.enabled', false);

        $this->post(url('/reservasi'), $this->validPayload())
            ->assertSessionHas('success', __('reservation.flash.success_no_wa'));

        config()->set('whatsapp.enabled', true);

        $this->post(url('/reservasi'), $this->validPayload())
            ->assertSessionHas('success', __('reservation.flash.success'));
    }

    protected function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'party_size' => 4,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '12:00',
            'notes' => 'Meja dekat jendela',
            ...$overrides,
        ];
    }
}
