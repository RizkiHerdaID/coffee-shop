<?php

namespace App\Http\Controllers;

use App\Jobs\SendReservationConfirmation;
use App\Models\LoyaltyCard;
use App\Models\MenuItem;
use App\Models\Promo;
use App\Models\Reservation;
use App\Models\Testimonial;
use App\Support\Phone;
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
        // Reject leading-zero ids (/qr/01) so every table has exactly one URL.
        if ((strlen($table) > 1 && $table[0] === '0') || $table < 1 || $table > config('shop.tables')) {
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
        // Guard against non-string queries (?phone[]=...) before trimming.
        $query = $request->query('phone');
        $phone = is_string($query) && filled($query) ? trim($query) : null;

        return view('points', [
            'card' => $phone ? LoyaltyCard::findByPhone($phone) : null,
            'phone' => $phone,
        ]);
    }

    public function reservation(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                // Relaxed pre-validation: dash/space/plus formats are allowed;
                // the value is normalized and re-checked before persisting.
                'phone' => ['required', 'string', 'regex:/^[\d+\-\s]+$/'],
                'party_size' => ['required', 'integer', 'min:1', 'max:20'],
                'date' => ['required', 'date', 'after_or_equal:today'],
                'time' => ['required', 'date_format:H:i'],
                'notes' => ['nullable', 'string', 'max:500'],
                // Honeypot: a real human never fills this hidden field.
                'website' => ['prohibited'],
            ], [
                // A relaxed pre-validation that fails for junk like letters:
                // surface it with the same localized message as the
                // post-normalization check below.
                'phone.regex' => __('reservation.form.invalid_phone'),
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

            // Bookings must fall within the shop's opening hours for that
            // weekday (config('shop.hours') windows, codes mon_fri/sat/sun).
            $minutes = $reservationDateTime->hour * 60 + $reservationDateTime->minute;
            $window = $this->hoursWindow($reservationDateTime);

            if ($window !== null && ($minutes < $window[0] || $minutes > $window[1])) {
                return back()
                    ->withErrors(['time' => __('reservation.form.closed')])
                    ->withInput();
            }

            // Bookings are accepted at most 90 days ahead.
            if ($reservationDateTime->gt(now()->addDays(90))) {
                return back()
                    ->withErrors(['date' => __('reservation.form.too_far')])
                    ->withInput();
            }

            // Persist the normalized phone (consistent with loyalty keys);
            // reject values that do not normalize to a valid Indonesian
            // mobile number (e.g. "62 8xx" landlines or short digits).
            $normalizedPhone = Phone::normalize($validated['phone']);

            if (! preg_match('/^62(8)\d{8,12}$/', $normalizedPhone)) {
                return back()
                    ->withErrors(['phone' => __('reservation.form.invalid_phone')])
                    ->withInput();
            }

            $validated['phone'] = $normalizedPhone;

            $reservation = Reservation::create($validated);

            SendReservationConfirmation::dispatch($reservation, app()->getLocale());

            $flash = config('whatsapp.enabled')
                ? __('reservation.flash.success')
                : __('reservation.flash.success_no_wa');

            // Keep the visitor's locale on the round-trip. A ?lang= query is
            // transient (not persisted in the session), so it must be carried
            // to the redirect URL. NOTE: comparing against config('app.locale')
            // is unreliable here — App::setLocale() overwrites that config
            // value, so it always equals the current locale.
            $redirectParams = $request->query('lang') === app()->getLocale()
                ? ['lang' => app()->getLocale()]
                : [];

            return redirect()->route('reservation', $redirectParams)->with('success', $flash);
        }

        return view('reservation');
    }

    /**
     * Resolve the opening-hours window (open/close minutes since midnight)
     * for the booking's weekday from the config('shop.hours') map, or null
     * when the configured value is missing or malformed.
     *
     * @return array{0: int, 1: int}|null
     */
    private function hoursWindow(Carbon $dateTime): ?array
    {
        $key = match ($dateTime->dayOfWeek) {
            1, 2, 3, 4, 5 => 'mon_fri',
            6 => 'sat',
            0 => 'sun',
            default => null,
        };

        $hours = is_string($key) ? config("shop.hours.$key") : null;

        if (! is_string($hours) || trim($hours) === '') {
            return null;
        }

        // Extract both endpoints regardless of the separator used in config
        // ('07:00 - 18:00', '07:00 — 18:00', ...). Splitting the raw string
        // on the (multi-byte) em-dash without the /u flag would match each
        // UTF-8 byte separately and mangle the parts.
        preg_match_all('/\d{1,2}:\d{2}/', $hours, $matches);

        if (count($matches[0]) < 2) {
            return null;
        }

        $open = Carbon::parse($matches[0][0]);
        $close = Carbon::parse($matches[0][1]);

        return [$open->hour * 60 + $open->minute, $close->hour * 60 + $close->minute];
    }
}
