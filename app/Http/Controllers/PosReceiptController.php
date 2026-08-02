<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

class PosReceiptController extends Controller
{
    /**
     * Standalone printable receipt (browser-print fallback).
     *
     * Renders a self-contained Blade document (no panel layout) that admins
     * can print with window.print().
     */
    public function show(Order $order): View
    {
        $order->load(['items', 'payments']);

        return view('filament.pos.receipt', ['order' => $order]);
    }
}
