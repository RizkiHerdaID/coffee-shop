<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard');
    }
}
