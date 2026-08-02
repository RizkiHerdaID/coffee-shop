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

    public function __construct(public Reservation $reservation) {}

    public function handle(FonnteWhatsApp $whatsapp): void
    {
        if (! config('whatsapp.enabled') || ! filled($this->reservation->phone)) {
            return;
        }

        $whatsapp->send($this->reservation->phone, $this->message());
    }

    protected function message(): string
    {
        return __('whatsapp.reservation', [
            'name' => $this->reservation->name,
            'shop' => config('shop.name'),
            'date' => Carbon::parse($this->reservation->date)->translatedFormat('d M Y'),
            'time' => Carbon::parse($this->reservation->time)->format('H:i'),
            'party_size' => $this->reservation->party_size,
            'phone' => config('shop.phone'),
        ]);
    }
}
