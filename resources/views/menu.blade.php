@extends('layouts.app')

@section('title', 'Menu — Coffee Shop')

@section('content')
<section class="mx-auto max-w-4xl px-4 pt-32 pb-20 sm:px-6 sm:pt-40">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">Brewed to order</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">The Menu</h1>
    <p class="mt-4 max-w-xl text-stone-400">All prices in Indonesian Rupiah. Oat and soy milk available at no extra charge.</p>

    <div class="mt-12 divide-y divide-stone-800 rounded-2xl border border-stone-800 bg-stone-950">
        @foreach ($menu as $item)
        <div class="flex items-start justify-between gap-6 p-6">
            <div>
                <h2 class="text-lg font-semibold text-white">{{ $item->name }}</h2>
                <p class="mt-1 text-sm text-stone-400">{{ $item->note }}</p>
            </div>
            <p class="shrink-0 text-lg font-semibold text-amber-500">Rp {{ number_format($item->price, 0, ",", ".") }}</p>
        </div>
        @endforeach
    </div>
</section>
@endsection
