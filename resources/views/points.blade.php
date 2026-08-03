@extends('layouts.app')

@section('title', __('points.heading'))

@section('content')
<section class="mx-auto max-w-4xl px-4 pt-32 pb-20 sm:px-6 sm:pt-40">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">{{ __('points.label') }}</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ __('points.heading') }}</h1>
    <p class="mt-4 max-w-lg text-sm leading-relaxed text-stone-400">{{ __('points.subheading') }}</p>

    <form method="GET" action="{{ route('points') }}" class="mt-12 flex flex-col gap-4 rounded-2xl border border-stone-800 bg-stone-900/60 p-8 sm:flex-row sm:items-end">
        {{-- The locale is always echoed back so a ?lang= visitor does not bounce
             to the default locale on submit. Always rendering is harmless: the
             SetLocale middleware validates the value before applying it. --}}
        <input type="hidden" name="lang" value="{{ app()->getLocale() }}">
        <div class="flex-1">
            <label for="phone" class="block text-sm font-medium text-stone-300">{{ __('points.phone_label') }}</label>
            <input type="tel" id="phone" name="phone" value="{{ $phone }}" inputmode="tel" autocomplete="tel" placeholder="{{ __('points.phone_placeholder') }}"
                class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-white placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-amber-500 px-10 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 sm:w-auto">
            {{ __('points.submit') }}
        </button>
    </form>

    @if ($phone)
    <div class="mt-10">
        @if ($card)
        <h2 class="text-lg font-bold text-white">{{ __('points.result_heading') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">{{ __('points.stamps_label') }}</p>
                <p class="mt-2 text-4xl font-extrabold text-amber-500">{{ $card->stamps }}</p>
            </div>
            <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">{{ __('points.available_free') }}</p>
                <p class="mt-2 text-4xl font-extrabold text-emerald-400">{{ $card->freeDrinksAvailable() }}</p>
            </div>
            <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">{{ __('points.redeemed_label') }}</p>
                <p class="mt-2 text-4xl font-extrabold text-white">{{ $card->redeemed }}</p>
            </div>
        </div>
        @php
            $stampsPerReward = config('loyalty.stamps_per_reward', 10);
            $collected = $card->stamps % $stampsPerReward;
        @endphp
        @if ($collected !== 0)
        <p class="mt-6 text-sm text-stone-400">
            {{ __('points.progress_label') }}: {{ __('points.progress_count', ['collected' => $collected, 'total' => $stampsPerReward]) }}
            &mdash; {{ __('points.progress_remaining', ['count' => $stampsPerReward - $collected]) }}
        </p>
        @endif
        @else
        <div class="rounded-2xl border border-stone-800 bg-stone-900/60 p-6">
            <p class="text-sm leading-relaxed text-stone-300">{{ __('points.not_found') }}</p>
        </div>
        @endif
    </div>
    @endif
</section>
@endsection
