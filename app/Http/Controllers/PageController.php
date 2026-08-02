<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Contracts\View\View;

final class PageController extends Controller
{
    public function home(): View
    {
        $items = MenuItem::query()->where('available', true)->orderBy('sort_order')->get();

        return view('home', ['highlights' => $items->take(4)]);
    }

    public function menu(): View
    {
        return view('menu', ['menu' => MenuItem::query()->where('available', true)->orderBy('sort_order')->get()]);
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
}
