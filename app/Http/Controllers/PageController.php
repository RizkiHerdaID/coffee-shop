<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;

final class PageController extends Controller
{
    public function home(): \Illuminate\Contracts\View\View
    {
        $items = MenuItem::query()->orderBy('sort_order')->get();

        return view('home', ['highlights' => $items->take(4)]);
    }

    public function menu(): \Illuminate\Contracts\View\View
    {
        return view('menu', ['menu' => MenuItem::query()->orderBy('sort_order')->get()]);
    }

    public function contact(): \Illuminate\Contracts\View\View
    {
        return view('contact');
    }
}
