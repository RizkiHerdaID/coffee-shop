<?php

namespace App\Http\Controllers;

final class PageController extends Controller
{
    private const MENU = [
        ['name' => 'Espresso', 'price' => 25000, 'note' => 'Double shot, rich crema'],
        ['name' => 'Cappuccino', 'price' => 32000, 'note' => 'Velvet milk foam'],
        ['name' => 'Flat White', 'price' => 34000, 'note' => 'Smooth, strong, balanced'],
        ['name' => 'V60 Pour Over', 'price' => 40000, 'note' => 'Single-origin, brewed to order'],
        ['name' => 'Cold Brew', 'price' => 38000, 'note' => '18-hour slow steep'],
        ['name' => 'Matcha Latte', 'price' => 35000, 'note' => 'Ceremonial grade'],
        ['name' => 'Banana Bread', 'price' => 18000, 'note' => 'Baked fresh daily'],
        ['name' => 'Butter Croissant', 'price' => 16000, 'note' => 'Flaky, golden layers'],
    ];

    public function home(): \Illuminate\Contracts\View\View
    {
        return view('home', ['highlights' => array_slice(self::MENU, 0, 4)]);
    }

    public function menu(): \Illuminate\Contracts\View\View
    {
        return view('menu', ['menu' => self::MENU]);
    }

    public function contact(): \Illuminate\Contracts\View\View
    {
        return view('contact');
    }
}
