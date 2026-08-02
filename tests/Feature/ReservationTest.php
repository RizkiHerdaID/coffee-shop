<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
