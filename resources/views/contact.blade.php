@extends('layouts.app')

@section('title', 'Contact &amp; Hours — Coffee Shop')

@section('content')
<section class="mx-auto max-w-4xl px-4 pt-32 pb-20 sm:px-6 sm:pt-40">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">We would love to see you</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Contact &amp; Hours</h1>

    <div class="mt-12 grid gap-8 sm:grid-cols-2">
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <h2 class="text-lg font-semibold text-white">Opening hours</h2>
            <dl class="mt-5 space-y-3 text-sm">
                @foreach (config('shop.hours') as $day => $hours)
                <div class="flex justify-between"><dt class="text-stone-400">{{ $day }}</dt><dd class="font-medium text-stone-200">{{ $hours }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <h2 class="text-lg font-semibold text-white">Find us</h2>
            <p class="mt-5 text-sm leading-relaxed text-stone-400">
                {!! nl2br(e(config('shop.address'))) !!}
            </p>
            <p class="mt-4 text-sm text-stone-400">Phone: <a href="tel:{{ config('shop.phone') }}" class="text-amber-500 hover:text-amber-400">{{ config('shop.phone_display') }}</a></p>
            <p class="mt-1 text-sm text-stone-400">Email: <a href="mailto:{{ config('shop.email') }}" class="text-amber-500 hover:text-amber-400">{{ config('shop.email') }}</a></p>
            <a href="{{ config('shop.maps_url') }}" target="_blank" rel="noopener" class="mt-6 inline-block rounded-full border border-stone-700 px-6 py-2.5 text-sm font-semibold text-stone-200 transition hover:border-amber-500 hover:text-amber-400">
                Open in Google Maps
            </a>
        </div>
    </div>

    <div class="mt-8 rounded-3xl border border-amber-500/30 bg-gradient-to-br from-amber-500/10 to-stone-900 p-10 text-center">
        <h2 class="text-2xl font-bold text-white">Group reservations</h2>
        <p class="mx-auto mt-3 max-w-lg text-sm text-stone-400">For tables of six or more, give us a call at least a day ahead and we will have the corner table ready.</p>
    </div>
</section>
@endsection
