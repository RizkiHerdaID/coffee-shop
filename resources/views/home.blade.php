@extends('layouts.app')

@section('title', __('home.title'))

@section('content')
<section class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
    <div class="pointer-events-none absolute -top-40 right-0 h-96 w-96 rounded-full bg-amber-500/10 blur-3xl"></div>
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="max-w-2xl">
            <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-amber-500">{{ __('home.hero.eyebrow') }}</p>
            <h1 class="text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-6xl">
                {{ __('home.hero.headline_prefix') }}
                <span class="text-amber-500">{{ __('home.hero.headline_highlight') }}</span>{{ __('home.hero.headline_suffix') }}
            </h1>
            <p class="mt-6 text-lg leading-relaxed text-stone-400">
                {{ __('home.hero.subtext') }}
            </p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="{{ route('menu') }}" class="rounded-full bg-amber-500 px-8 py-3 font-semibold text-stone-950 transition hover:bg-amber-400">
                    {{ __('home.hero.cta_menu') }}
                </a>
                <a href="{{ route('contact') }}" class="rounded-full border border-stone-700 px-8 py-3 font-semibold text-stone-200 transition hover:border-amber-500 hover:text-amber-400">
                    {{ __('home.hero.cta_find') }}
                </a>
                <a href="https://wa.me/{{ preg_replace('/\D/', '', config('shop.phone')) }}?text={{ urlencode(__('site.wa_message')) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-8 py-3 font-semibold text-stone-950 transition hover:bg-emerald-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    {{ __('home.hero.cta_wa') }}
                </a>
                <a href="https://wa.me/{{ preg_replace('/\D/', '', config('shop.phone')) }}?text={{ urlencode(config('shop.wa_message')) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-8 py-3 font-semibold text-stone-950 transition hover:bg-emerald-400">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Order on WhatsApp
                </a>
            </div>
        </div>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <div class="grid gap-6 sm:grid-cols-3">
        <a href="https://wa.me/{{ preg_replace('/\D/', '', config('shop.phone')) }}?text={{ urlencode(__('site.wa_message')) }}" target="_blank" rel="noopener" class="group rounded-2xl border border-stone-800 bg-stone-900/60 p-8 transition hover:border-emerald-500/60">
            <svg class="h-8 w-8 text-emerald-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-white">{{ __('home.cards.whatsapp.title') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">{{ __('home.cards.whatsapp.body') }}</p>
        </a>
        <a href="{{ route('contact') }}" class="group rounded-2xl border border-stone-800 bg-stone-900/60 p-8 transition hover:border-amber-500/60">
            <svg class="h-8 w-8 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="3.5" height="3.5" rx="0.5"/><rect x="17.5" y="13" width="3.5" height="3.5" rx="0.5"/><rect x="13" y="17.5" width="3.5" height="3.5" rx="0.5"/><rect x="17.5" y="17.5" width="3.5" height="3.5" rx="0.5"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-white">{{ __('home.cards.qris.title') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">{{ __('home.cards.qris.body') }}</p>
        </a>
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8 transition hover:border-red-500/60">
            <svg class="h-8 w-8 text-[#ee4d2d]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-white">{{ __('home.cards.delivery.title') }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-stone-400">{{ __('home.cards.delivery.body') }}</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a href="{{ config('shop.gofood_url') }}" target="_blank" rel="noopener" class="flex-1 rounded-full bg-[#ee4d2d] px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:brightness-110">{{ __('home.cards.delivery.gofood') }}</a>
                <a href="{{ config('shop.grab_url') }}" target="_blank" rel="noopener" class="flex-1 rounded-full bg-[#00b14f] px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:brightness-110">{{ __('home.cards.delivery.grabfood') }}</a>
            </div>
        </div>
    </div>
</section>

<section class="bg-stone-900/40 py-16 sm:py-24">
    <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">{{ __('home.favorites.eyebrow') }}</p>
                <h2 class="mt-2 text-3xl font-bold text-white sm:text-4xl">{{ __('home.favorites.heading') }}</h2>
            </div>
            <a href="{{ route('menu') }}" class="font-semibold text-amber-500 transition hover:text-amber-400">{{ __('home.favorites.full_menu') }} &rarr;</a>
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach ($highlights as $item)
            <div class="flex items-start justify-between gap-4 rounded-2xl border border-stone-800 bg-stone-950 p-6">
                <div>
                    <h3 class="font-semibold text-white">{{ $item->name }}</h3>
                    <p class="mt-1 text-sm text-stone-400">{{ $item->note }}</p>
                </div>
                <p class="shrink-0 font-semibold text-amber-500">Rp {{ number_format($item->price, 0, ",", ".") }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
    <div class="rounded-3xl border border-amber-500/30 bg-gradient-to-br from-amber-500/10 to-stone-900 p-10 text-center sm:p-14">
        <h2 class="text-3xl font-bold text-white sm:text-4xl">{{ __('home.cta.heading') }}</h2>
        <p class="mx-auto mt-4 max-w-xl text-stone-400">{{ __('home.cta.body') }}</p>
        <a href="{{ route('contact') }}" class="mt-8 inline-block rounded-full bg-amber-500 px-8 py-3 font-semibold text-stone-950 transition hover:bg-amber-400">
            {{ __('home.cta.button') }}
        </a>
        <span class="mt-6 inline-flex items-center gap-2 rounded-full border border-stone-700 bg-stone-900/60 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-stone-300">
            <svg class="h-3.5 w-3.5 text-amber-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="3.5" height="3.5" rx="0.5"/><rect x="17.5" y="13" width="3.5" height="3.5" rx="0.5"/><rect x="13" y="17.5" width="3.5" height="3.5" rx="0.5"/><rect x="17.5" y="17.5" width="3.5" height="3.5" rx="0.5"/></svg>
            Terima QRIS
        </span>
    </div>
</section>

<section class="border-t border-stone-800 bg-stone-950/50">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-3 px-4 py-8 sm:px-6">
        <span class="mr-2 text-sm font-semibold uppercase tracking-widest text-stone-500">Order online</span>
        <a href="{{ config('shop.gofood_url') }}" target="_blank" rel="noopener" class="rounded-full bg-red-500 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-red-400">GoFood</a>
        <a href="{{ config('shop.grab_url') }}" target="_blank" rel="noopener" class="rounded-full bg-green-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-green-500">GrabFood</a>
    </div>
</section>

@endsection
