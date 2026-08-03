<?php

namespace App\Http\Controllers;

use App\Jobs\SendReservationConfirmation;
use App\Models\LoyaltyCard;
use App\Models\MenuItem;
use App\Models\Promo;
use App\Models\Reservation;
use App\Models\Testimonial;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PageController extends Controller
{
    public function home(): View
    {
        // Light 5-minute cache: the public home page is hit on every visit and
        // the menu/promo/testimonial data changes only via the admin panel.
        // NOTE: the cache store refuses to unserialize PHP classes by default
        // (Laravel's hardened cache serializer), so we cache PLAIN ARRAYS of
        // raw query rows and hydrate lightweight models from them.
        $itemRows = Cache::remember('home.menu_items', 300, fn () => MenuItem::query()->where('available', true)->orderBy('sort_order')->getQuery()->get()
            ->map(fn (object $row) => (array) $row)
            ->all());
        $items = $this->hydrate(MenuItem::class, $itemRows);

        $promoRow = Cache::remember('home.promo', 300, function () {
            $row = Promo::query()->visible()->orderBy('sort_order')->getQuery()->first();

            return $row ? (array) $row : null;
        });
        $promo = $promoRow ? $this->hydrate(Promo::class, [$promoRow])->first() : null;

        $testimonialRows = Cache::remember('home.testimonials', 300, fn () => Testimonial::query()->visible()->orderBy('sort_order')->orderBy('id')->getQuery()->get()
            ->map(fn (object $row) => (array) $row)
            ->all());
        $testimonials = $this->hydrate(Testimonial::class, $testimonialRows);

        return view('home', [
            'highlights' => $items->take(4),
            'promo' => $promo,
            'testimonials' => $testimonials,
        ]);
    }

    /**
     * Rebuild model instances from cached raw rows. The resolved default
     * connection name is pinned so the instances compare equal (Model::is)
     * to models created through factories/queries in the same app run.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function hydrate(string $model, array $rows): Collection
    {
        return collect($rows)->map(function (array $row) use ($model): Model {
            $instance = new $model;
            $instance->setConnection($instance->getConnection()->getName());

            return $instance->newFromBuilder($row);
        });
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
                // Honeypot: a real human never fills this hidden field.
                'website' => ['prohibited'],
            ]);

            // The honeypot field must not be persisted.
            unset($validated['website']);

            // Same-day bookings must be for a time still in the future.
            $reservationDateTime = Carbon::parse($validated['date'].' '.$validated['time']);

            if ($reservationDateTime->isToday() && $reservationDateTime->isPast()) {
                return back()
                    ->withErrors(['time' => __('reservation.form.past_time')])
                    ->withInput();
            }

            $reservation = Reservation::create($validated);

            SendReservationConfirmation::dispatch($reservation);

            return redirect()->route('reservation')->with('success', __('reservation.flash.success'));
        }

        return view('reservation');
    }
}
