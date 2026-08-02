@extends('layouts.app')

@section('title', __('menu.title'))

@section('content')
<section class="mx-auto max-w-6xl px-4 pt-32 pb-20 sm:px-6 sm:pt-40">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">{{ __('menu.eyebrow') }}</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ __('menu.heading') }}</h1>
    <p class="mt-4 max-w-xl text-stone-400">{{ __('menu.intro') }}</p>

    <div class="mt-12 flex flex-wrap gap-3" id="category-filter" role="group" aria-label="{{ __('menu.categories.all') }}">
        <button type="button" data-filter="all" class="category-filter-btn rounded-full border border-amber-500/60 px-5 py-2 text-sm font-semibold text-amber-400 transition hover:bg-amber-500/10">
            {{ __('menu.categories.all') }}
        </button>
        @foreach (['coffee', 'non-coffee', 'snack', 'food'] as $category)
        <button type="button" data-filter="{{ $category }}" class="category-filter-btn rounded-full border border-stone-700 px-5 py-2 text-sm font-semibold text-stone-300 transition hover:border-amber-500 hover:text-amber-400">
            {{ __("menu.categories.$category") }}
        </button>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" id="menu-grid">
        @foreach ($menu as $item)
        <article data-category="{{ $item->category }}" class="menu-card overflow-hidden rounded-2xl border border-stone-800 bg-stone-950 {{ $item->available ? '' : 'opacity-50' }}">
            <div class="relative aspect-[4/3] w-full bg-stone-800/60">
                @if ($item->photo)
                <img src="{{ Storage::disk('public')->url($item->photo) }}" alt="{{ $item->name }}" loading="lazy" class="h-full w-full object-cover">
                @else
                <div class="flex h-full w-full items-center justify-center text-5xl" aria-hidden="true">&#9749;</div>
                @endif
                @if (! $item->available)
                <span class="absolute top-3 right-3 rounded-full bg-red-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">{{ __('menu.sold_out') }}</span>
                @endif
            </div>
            <div class="p-6">
                <h2 class="text-lg font-semibold text-white">{{ $item->name }}</h2>
                <p class="mt-1 text-sm text-stone-400">{{ $item->note }}</p>
                <p class="mt-3 text-lg font-semibold text-amber-500">Rp {{ number_format($item->price, 0, ",", ".") }}</p>
            </div>
        </article>
        @endforeach
    </div>

    <p class="mt-10 rounded-2xl border border-stone-800 bg-stone-950 p-8 text-center text-stone-400 {{ $menu->isEmpty() ? '' : 'hidden' }}" id="menu-empty">{{ __('menu.empty') }}</p>
</section>

@include('partials.menu-schema')

<script>
    (function () {
        var buttons = document.querySelectorAll('.category-filter-btn');
        var cards = document.querySelectorAll('.menu-card');
        var empty = document.getElementById('menu-empty');
        var activeClass = 'border-amber-500/60 bg-amber-500/10 text-amber-400';
        var baseClass = 'border-stone-700 text-stone-300';

        function apply(filter) {
            var visible = 0;
            cards.forEach(function (card) {
                var match = filter === 'all' || card.dataset.category === filter;
                card.classList.toggle('hidden', !match);
                if (match) {
                    visible++;
                }
            });
            if (empty) {
                empty.classList.toggle('hidden', visible > 0);
            }
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                buttons.forEach(function (b) {
                    b.classList.remove(activeClass);
                    b.classList.add(baseClass);
                });
                button.classList.remove(baseClass);
                button.classList.add(activeClass);
                apply(button.dataset.filter);
            });
        });
    })();
</script>

@endsection
