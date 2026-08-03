@extends('layouts.app')

@section('title', __('qr.title'))

@section('content')
<section class="mx-auto max-w-sm px-4 pt-32 pb-20 sm:px-6">
    <p class="text-center text-sm font-semibold uppercase tracking-widest text-amber-500">{{ config('shop.name') }}</p>
    <h1 class="mt-2 text-center text-3xl font-extrabold tracking-tight text-white">{{ __('qr.table_name', ['number' => $table]) }}</h1>
    <p class="mt-3 text-center text-sm text-stone-400">{{ __('qr.intro') }}</p>

    <div class="mt-8 divide-y divide-stone-800 rounded-2xl border border-stone-800 bg-stone-950">
        @forelse ($menu as $item)
        <div class="flex items-center justify-between gap-4 px-5 py-4">
            <h2 class="font-semibold text-white">{{ $item->name }}</h2>
            <p class="shrink-0 font-semibold text-amber-500">Rp {{ number_format($item->price, 0, ",", ".") }}</p>
        </div>
        @empty
        <div class="px-5 py-10 text-center">
            <p class="text-4xl" aria-hidden="true">&#9749;</p>
            <p class="mt-4 text-sm text-stone-400">{{ __('qr.empty') }}</p>
        </div>
        @endforelse
    </div>

    <a href="{{ route('menu') }}" class="mt-8 block rounded-full bg-amber-500 py-3 text-center font-semibold text-stone-950 transition hover:bg-amber-400">
        {{ __('qr.open_full_menu') }}
    </a>
</section>

@endsection
