<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PosReceiptController;
use App\Http\Controllers\PosZReportController;
use App\Http\Controllers\RobotsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/menu', [PageController::class, 'menu'])->name('menu');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/cek-poin', [PageController::class, 'points'])
    ->middleware('throttle:points')
    ->name('points');

Route::get('/reservasi', [PageController::class, 'reservation'])->name('reservation');

Route::post('/reservasi', [PageController::class, 'reservation'])
    ->middleware('throttle:5,1');

Route::get('/qr/{table}', [PageController::class, 'qr'])->where('table', '\d+')->name('qr.menu');

Route::get('/lang/{locale}', function (string $locale, Request $request) {
    if (! in_array($locale, ['id', 'en'], true)) {
        abort(404);
    }

    session(['locale' => $locale]);

    // Rebuild the back URL WITHOUT the `lang` query param, otherwise the
    // redirect keeps `?lang=old` and the next request overrides the newly
    // chosen locale (the switcher is defeated by its own query string).
    $previous = url()->previous();
    $sameHost = is_string($previous)
        && parse_url($previous, PHP_URL_HOST) === $request->getHost();

    $query = [];

    if ($sameHost && is_string($previous)) {
        parse_str((string) parse_url($previous, PHP_URL_QUERY), $query);
    }

    unset($query['lang']);

    $path = $sameHost && is_string($previous)
        ? (string) parse_url($previous, PHP_URL_PATH)
        : '/';

    return redirect($path.(count($query) ? '?'.http_build_query($query) : ''));
})->name('lang.switch');

Route::get('/pos/receipt/{order}', [PosReceiptController::class, 'show'])
    ->middleware('auth:admin')
    ->name('pos.receipt');

Route::get('/pos/z-report/{shift}', [PosZReportController::class, 'show'])
    ->middleware('auth:admin')
    ->name('pos.zreport');

Route::get('/sitemap.xml', function () {
    $urls = [route('home'), route('menu'), route('contact'), route('reservation'), route('points')];

    for ($table = 1; $table <= config('shop.tables'); $table++) {
        $urls[] = route('qr.menu', ['table' => $table]);
    }

    $lastmod = now()->toDateString();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($urls as $url) {
        $xml .= "\n  <url>\n    <loc>".e($url)."</loc>\n    <lastmod>".$lastmod."</lastmod>\n    <changefreq>weekly</changefreq>\n  </url>";
    }

    $xml .= "\n</urlset>\n";

    return response($xml)->header('Content-Type', 'text/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', RobotsController::class)->name('robots');
