@extends('layouts.app')

@section('title', 'Coffee Shop — Specialty Coffee, Brewed to Order')

@section('content')
<section class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
    <div class="pointer-events-none absolute -top-40 right-0 h-96 w-96 rounded-full bg-amber-500/10 blur-3xl"></div>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="max-w-2xl">
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-amber-500">Slow brew, since 2015</p>
            <h1 class="text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-6xl">
                Coffee worth
                <span class="text-amber-500">slowing down</span> for.
            </h1>
            <p class="mt-6 text-lg leading-relaxed text-stone-400">
                Single-origin beans, roasted in small batches and brewed to order.
                A calm corner in the middle of your day.
            </p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="{{ route('menu') }}" class="rounded-full bg-amber-500 px-8 py-3 font-semibold text-stone-950 transition hover:bg-amber-400">
                    See the Menu
                </a>
                <a href="{{ route('contact') }}" class="rounded-full border border-stone-700 px-8 py-3 font-semibold text-stone-200 transition hover:border-amber-500 hover:text-amber-400">
                    Find Us
                </a>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <div class="grid gap-6 sm:grid-cols-3">
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <div class="text-3xl">&#127850;</div>
            <h3 class="mt-4 text-lg font-semibold text-white">Single Origin</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">Ethically sourced beans from Sumatra, Ethiopia, and Brazil, roasted every week.</p>
        </div>
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <div class="text-3xl">&#127864;</div>
            <h3 class="mt-4 text-lg font-semibold text-white">Brewed to Order</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">Every cup dialed in — V60, espresso, or cold brew. No shortcuts, no stale batches.</p>
        </div>
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <div class="text-3xl">&#127853;</div>
            <h3 class="mt-4 text-lg font-semibold text-white">Fresh Bakes</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">Banana bread and croissants, baked in-house every morning. Gone by noon.</p>
        </div>
    </div>
</section>

<section class="bg-stone-900/40 py-16 sm:py-24">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">Crowd favourites</p>
                <h2 class="mt-2 text-3xl font-bold text-white sm:text-4xl">From the menu</h2>
            </div>
            <a href="{{ route('menu') }}" class="font-semibold text-amber-500 transition hover:text-amber-400">Full menu &rarr;</a>
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach ($highlights as $item)
            <div class="flex items-start justify-between gap-4 rounded-2xl border border-stone-800 bg-stone-950 p-6">
                <div>
                    <h3 class="font-semibold text-white">{{ $item['name'] }}</h3>
                    <p class="mt-1 text-sm text-stone-400">{{ $item['note'] }}</p>
                </div>
                <p class="shrink-0 font-semibold text-amber-500">{{ number_format($item['price']) }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
    <div class="rounded-3xl border border-amber-500/30 bg-gradient-to-br from-amber-500/10 to-stone-900 p-10 text-center sm:p-14">
        <h2 class="text-3xl font-bold text-white sm:text-4xl">Your table is waiting.</h2>
        <p class="mx-auto mt-4 max-w-xl text-stone-400">Reserve a spot or just walk in — either way, the kettle is already on.</p>
        <a href="{{ route('contact') }}" class="mt-8 inline-block rounded-full bg-amber-500 px-8 py-3 font-semibold text-stone-950 transition hover:bg-amber-400">
            Contact &amp; Hours
        </a>
    </div>
</section>
@endsection
