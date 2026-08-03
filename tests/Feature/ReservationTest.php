<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\Pages\CreateReservation;
use App\Models\Reservation;
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
            'time' => '19:30',
            'notes' => 'Meja dekat jendela',
        ];

        $response = $this->post(url('/reservasi'), $payload);

        $response->assertRedirect(url('/reservasi'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'party_size' => 4,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '19:30',
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
            'time' => '19:30',
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
            'time' => '19:30',
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
            'time' => '19:30',
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
        $proposed = now()->addMinutes(90);

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

    protected function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'party_size' => 4,
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '19:30',
            'notes' => 'Meja dekat jendela',
            ...$overrides,
        ];
    }
}
