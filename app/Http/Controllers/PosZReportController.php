<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Contracts\View\View;

class PosZReportController extends Controller
{
    /**
     * Standalone printable Z-report (browser-print fallback).
     *
     * Renders a self-contained Blade document (no panel layout) that admins
     * can print with window.print().
     */
    public function show(Shift $shift): View
    {
        $shift->load('admin');

        return view('filament.pos.z-report', ['shift' => $shift]);
    }
}
