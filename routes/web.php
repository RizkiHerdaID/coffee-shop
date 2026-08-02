<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PosReceiptController;
use App\Http\Controllers\PosZReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/menu', [PageController::class, 'menu'])->name('menu');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/cek-poin', [PageController::class, 'points'])->name('points');

Route::match(['get', 'post'], '/reservasi', [PageController::class, 'reservation'])
    ->name('reservation');

Route::get('/qr/{table}', [PageController::class, 'qr'])->where('table', '\d+')->name('qr.menu');

Route::get('/lang/{locale}', function (string $locale) {
    if (! in_array($locale, ['id', 'en'], true)) {
        abort(404);
    }

    session(['locale' => $locale]);

    return redirect()->back();
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

    $lastmod = date('Y-m-d');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($urls as $url) {
        $xml .= "\n  <url>\n    <loc>".e($url)."</loc>\n    <lastmod>".$lastmod."</lastmod>\n    <changefreq>weekly</changefreq>\n  </url>";
    }

    $xml .= "\n</urlset>\n";

    return response($xml)->header('Content-Type', 'text/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n")
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');
