<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\FonnteWhatsApp;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendReservationConfirmation implements ShouldQueue
{
    use Queueable;

    public function __construct(public Reservation $reservation, public string $locale) {}

    public function handle(FonnteWhatsApp $whatsapp): void
    {
        if (! config('whatsapp.enabled')) {
            return;
        }

        // Re-read the reservation from the database: with a database queue
        // a reservation deleted before the job runs is restored as null by
        // SerializesModels, so guard like SendOrderConfirmation does.
        $reservation = $this->reservation->fresh();

        if ($reservation === null || blank($reservation->phone)) {
            return;
        }

        $whatsapp->send($reservation->phone, $this->message($reservation));
    }

    protected function message(Reservation $reservation): string
    {
        // The locale is captured at dispatch time: a queued job runs on
        // the queue worker, where app()->getLocale() is the config default
        // and would ignore the visitor's language.
        return __('whatsapp.reservation', [
            'name' => $reservation->name,
            'shop' => config('shop.name'),
            'date' => Carbon::parse($reservation->date)
                ->locale($this->locale)
                ->translatedFormat('d M Y'),
            'time' => Carbon::parse($reservation->time)->format('H:i'),
            'party_size' => $reservation->party_size,
            'phone' => config('shop.phone'),
        ], $this->locale);
    }
}
