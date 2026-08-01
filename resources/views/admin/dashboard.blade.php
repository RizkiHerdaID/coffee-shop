@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-3xl font-bold text-white">Welcome back, {{ auth('admin')->user()->name }}.</h1>
    <p class="mt-2 text-stone-400">This is the admin dashboard. Management tools are on the way.</p>

    <div class="mt-10 grid gap-6 sm:grid-cols-3">
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <div class="text-3xl">&#127869;</div>
            <h3 class="mt-4 text-lg font-semibold text-white">Menu</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">Add, edit, and organize menu items.</p>
            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-amber-500">Coming soon</p>
        </div>
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <div class="text-3xl">&#128176;</div>
            <h3 class="mt-4 text-lg font-semibold text-white">Orders</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">Track incoming orders and reservations.</p>
            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-amber-500">Coming soon</p>
        </div>
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <div class="text-3xl">&#128202;</div>
            <h3 class="mt-4 text-lg font-semibold text-white">Reports</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">Daily sales and inventory insights.</p>
            <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-amber-500">Coming soon</p>
        </div>
    </div>
@endsection
