@extends('layouts.app')

@section('title', __('contact.title'))

@section('content')
<section class="mx-auto max-w-4xl px-4 pt-32 pb-20 sm:px-6 sm:pt-40">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">{{ __('contact.eyebrow') }}</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ __('contact.heading') }}</h1>

    <div class="mt-12 grid gap-8 sm:grid-cols-2">
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <h2 class="text-lg font-semibold text-white">{{ __('contact.hours_heading') }}</h2>
            <dl class="mt-5 space-y-3 text-sm">
                @foreach (config('shop.hours') as $day => $hours)
                <div class="flex justify-between"><dt class="text-stone-400">{{ __("site.days.$day") }}</dt><dd class="font-medium text-stone-200">{{ $hours }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            <h2 class="text-lg font-semibold text-white">{{ __('contact.find_heading') }}</h2>
            <p class="mt-5 text-sm leading-relaxed text-stone-400">
                {!! nl2br(e(config('shop.address'))) !!}
            </p>
            <p class="mt-4 text-sm text-stone-400">{{ __('contact.phone_label') }} <a href="tel:{{ config('shop.phone') }}" class="text-amber-500 hover:text-amber-400">{{ config('shop.phone_display') }}</a></p>
            <p class="mt-1 text-sm text-stone-400">{{ __('contact.email_label') }} <a href="mailto:{{ config('shop.email') }}" class="text-amber-500 hover:text-amber-400">{{ config('shop.email') }}</a></p>
            <a href="{{ config('shop.maps_url') }}" target="_blank" rel="noopener" class="mt-6 inline-block rounded-full border border-stone-700 px-6 py-2.5 text-sm font-semibold text-stone-200 transition hover:border-amber-500 hover:text-amber-400">
                {{ __('contact.maps_button') }}
            </a>
            <a href="https://wa.me/{{ preg_replace('/\D/', '', config('shop.phone')) }}?text={{ urlencode(__('site.wa_message')) }}" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-full bg-emerald-500 px-6 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-emerald-400">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                {{ __('contact.wa_button') }}
            </a>
        </div>
    </div>

    <div class="mt-8 overflow-hidden rounded-2xl border border-stone-800 bg-stone-900/60">
        <div class="aspect-[4/3] w-full sm:aspect-video">
            <iframe
                src="{{ 'https://maps.google.com/maps?q='.urlencode(config('shop.address')).'&output=embed' }}"
                title="{{ __('contact.map_title') }}"
                loading="lazy"
                allowfullscreen
                referrerpolicy="no-referrer-when-downgrade"
                class="h-full w-full border-0"
            ></iframe>
        </div>
        <div class="flex flex-wrap items-center gap-4 p-6">
            <a href="{{ 'https://www.google.com/maps/dir/?api=1&destination='.urlencode(config('shop.address')) }}" target="_blank" rel="noopener" class="inline-block rounded-full border border-stone-700 px-6 py-2.5 text-sm font-semibold text-stone-200 transition hover:border-amber-500 hover:text-amber-400">
                {{ __('contact.directions_button') }}
            </a>
        </div>
    </div>

    <div class="mt-8 flex flex-col items-center gap-6 rounded-2xl border border-stone-800 bg-stone-900/60 p-8 text-center sm:flex-row sm:text-left">
        {{-- TODO: drop the real QRIS image at public/images/qris.png and replace the placeholder SVG below with:
             <img src="{{ asset('images/qris.png') }}" alt="QRIS QR code" class="h-28 w-28 shrink-0 rounded-lg bg-white p-1"> --}}
        <svg viewBox="0 0 64 64" class="h-28 w-28 shrink-0 rounded-lg bg-white p-1 text-stone-900" fill="currentColor" aria-hidden="true">
            <rect x="8" y="8" width="16" height="16" rx="2"/><rect x="12" y="12" width="8" height="8" rx="1"/>
            <rect x="40" y="8" width="16" height="16" rx="2"/><rect x="44" y="12" width="8" height="8" rx="1"/>
            <rect x="8" y="40" width="16" height="16" rx="2"/><rect x="12" y="44" width="8" height="8" rx="1"/>
            <rect x="28" y="12" width="4" height="4"/><rect x="36" y="12" width="4" height="4"/>
            <rect x="28" y="20" width="4" height="4"/><rect x="32" y="28" width="4" height="4"/>
            <rect x="40" y="32" width="4" height="4"/><rect x="28" y="36" width="4" height="4"/>
            <rect x="36" y="40" width="4" height="4"/><rect x="28" y="44" width="4" height="4"/>
            <rect x="44" y="44" width="4" height="4"/><rect x="32" y="52" width="4" height="4"/>
            <rect x="40" y="52" width="4" height="4"/><rect x="48" y="52" width="4" height="4"/>
        </svg>
        <div>
            <h2 class="text-lg font-semibold text-white">{{ __('contact.qris.title') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">{{ __('contact.qris.body') }}</p>
        </div>
    </div>

    <div class="mt-8 rounded-3xl border border-amber-500/30 bg-gradient-to-br from-amber-500/10 to-stone-900 p-10 text-center">
        <h2 class="text-2xl font-bold text-white">{{ __('contact.reservations.heading') }}</h2>
        <p class="mx-auto mt-3 max-w-lg text-sm text-stone-400">{{ __('contact.reservations.body') }}</p>
    </div>
</section>

@endsection
