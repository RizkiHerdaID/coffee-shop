<?php

namespace App\Http\Controllers;

use App\Jobs\SendReservationConfirmation;
use App\Models\LoyaltyCard;
use App\Models\MenuItem;
use App\Models\Promo;
use App\Models\Reservation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PageController extends Controller
{
    public function home(): View
    {
        $items = MenuItem::query()->where('available', true)->orderBy('sort_order')->get();

        return view('home', [
            'highlights' => $items->take(4),
            'promo' => Promo::query()->visible()->orderBy('sort_order')->first(),
        ]);
    }

    public function menu(): View
    {
        return view('menu', [
            'menu' => MenuItem::query()->orderBy('sort_order')->get(),
            'promo' => Promo::query()->visible()->orderBy('sort_order')->first(),
        ]);
    }

    public function qr(string $table): View
    {
        if ($table < 1 || $table > config('shop.tables')) {
            abort(404);
        }

        return view('qr', ['table' => $table, 'menu' => MenuItem::query()->where('available', true)->orderBy('sort_order')->get()]);
    }

    public function contact(): View
    {
        return view('contact');
    }

    public function points(Request $request): View
    {
        $phone = filled($request->query('phone')) ? trim((string) $request->query('phone')) : null;

        return view('points', [
            'card' => $phone ? LoyaltyCard::where('phone', $phone)->first() : null,
            'phone' => $phone,
        ]);
    }

    public function reservation(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8\d{8,12}$/'],
                'party_size' => ['required', 'integer', 'min:1', 'max:20'],
                'date' => ['required', 'date', 'after_or_equal:today'],
                'time' => ['required', 'date_format:H:i'],
                'notes' => ['nullable', 'string', 'max:500'],
            ]);

            $reservation = Reservation::create($validated);

            SendReservationConfirmation::dispatch($reservation);

            return redirect()->route('reservation')->with('success', __('reservation.flash.success'));
        }

        return view('reservation');
    }
}
