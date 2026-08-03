<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected const SUPPORTED_LOCALES = ['id', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // Validate the query param first so an invalid `?lang=xx` can never
        // shadow the session locale; then fall back to session, then config.
        $locale = $request->query('lang');

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = $request->session()->get('locale');
        }

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
