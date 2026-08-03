@extends('layouts.app')

@section('title', __('menu.title'))

@section('promo-banner')
    @include('partials.promo-banner')
@endsection

@section('content')
@php
    $pickupI18n = [
        'shop' => config('shop.name'),
        'wa_message' => __('site.wa_message'),
        'item_line' => __('menu.pickup.item_line'),
        'message_title' => __('menu.pickup.message_title'),
        'message_total' => __('menu.pickup.message_total'),
        'message_pickup' => __('menu.pickup.message_pickup'),
        'remove_aria' => __('menu.pickup.remove_aria'),
    ];
@endphp
<section class="mx-auto max-w-6xl px-4 pt-32 pb-44 sm:px-6 sm:pt-40">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">{{ __('menu.eyebrow') }}</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ __('menu.heading') }}</h1>
    <p class="mt-4 max-w-xl text-stone-400">{{ __('menu.intro') }}</p>

    @php
        $categories = collect(['coffee', 'non-coffee', 'snack', 'food'])
            ->filter(fn (string $category) => $menu->contains(fn ($item) => $item->category === $category));
    @endphp

    <div class="mt-12 flex flex-wrap gap-3" id="category-filter" role="group" aria-label="{{ __('menu.categories.all') }}">
        <button type="button" data-filter="all" class="category-filter-btn rounded-full border border-amber-500/60 px-5 py-2 text-sm font-semibold text-amber-400 transition hover:bg-amber-500/10">
            {{ __('menu.categories.all') }}
        </button>
        @foreach ($categories as $category)
        <button type="button" data-filter="{{ $category }}" class="category-filter-btn rounded-full border border-stone-700 px-5 py-2 text-sm font-semibold text-stone-300 transition hover:border-amber-500 hover:text-amber-400">
            {{ \Illuminate\Support\Facades\Lang::has("menu.categories.$category") ? __("menu.categories.$category") : $category }}
        </button>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" id="menu-grid">
        @foreach ($menu as $item)
        <article data-category="{{ $item->category }}" data-item-id="{{ $item->id }}" data-item-name="{{ $item->name }}" data-item-price="{{ $item->price }}" data-available="{{ $item->available ? '1' : '0' }}" class="menu-card overflow-hidden rounded-2xl border border-stone-800 bg-stone-950 {{ $item->available ? '' : 'opacity-50' }}">
            <div class="relative aspect-[4/3] w-full bg-stone-800/60">
                @if ($item->photo)
                @php
                    $photoSize = @getimagesize(Storage::disk('public')->path($item->photo));
                    $photoWidth = $photoSize[0] ?? 800;
                    $photoHeight = $photoSize[1] ?? 600;
                @endphp
                <img src="{{ Storage::disk('public')->url($item->photo) }}" alt="{{ $item->name }}" loading="lazy" decoding="async" width="{{ $photoWidth }}" height="{{ $photoHeight }}" class="h-full w-full object-cover">
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
                @include('partials.badge-chips', ['item' => $item])
                <p class="mt-3 text-lg font-semibold text-amber-500">Rp {{ number_format($item->price, 0, ",", ".") }}</p>
                @if ($item->available)
                <div class="mt-4 flex items-center gap-3">
                    <button type="button" data-add="{{ $item->id }}" class="pickup-add rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400">
                        {{ __('menu.pickup.add') }}
                    </button>
                    <div data-stepper="{{ $item->id }}" class="hidden items-center gap-2">
                        <button type="button" data-dec="{{ $item->id }}" aria-label="{{ __('menu.pickup.decrease_aria', ['item' => $item->name]) }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-700 text-stone-200 transition hover:border-amber-500 hover:text-amber-400">−</button>
                        <span data-qty="{{ $item->id }}" class="w-8 text-center text-sm font-bold text-white">0</span>
                        <button type="button" data-inc="{{ $item->id }}" aria-label="{{ __('menu.pickup.increase_aria', ['item' => $item->name]) }}" class="flex h-9 w-9 items-center justify-center rounded-full border border-stone-700 text-stone-200 transition hover:border-amber-500 hover:text-amber-400">+</button>
                    </div>
                </div>
                @endif
            </div>
        </article>
        @endforeach
    </div>

    <div class="mt-10 rounded-2xl border border-stone-800 bg-stone-950 p-12 text-center {{ $menu->isEmpty() ? '' : 'hidden' }}" id="menu-empty">
        <p class="text-5xl" aria-hidden="true">&#9749;</p>
        <h2 class="mt-4 text-xl font-semibold text-white">{{ __('menu.empty_heading') }}</h2>
        <p class="mt-2 text-sm text-stone-400">{{ __('menu.empty_description') }}</p>
    </div>
</section>

@include('partials.menu-schema')

<script type="application/json" id="wa-pickup-i18n">
@json($pickupI18n)
</script>

<aside id="pickup-cart" class="fixed inset-x-0 bottom-0 z-40 border-t border-stone-800 bg-stone-950/95 backdrop-blur" data-wa-phone="{{ preg_replace('/\D/', '', config('shop.phone')) }}">
    <div class="mx-auto max-w-6xl px-4 py-3 sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-white">{{ __('menu.pickup.cart_title') }}</h2>
                <ul id="pickup-cart-items" class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-stone-400"></ul>
                <p id="pickup-cart-empty" class="mt-1 text-sm text-stone-400">{{ __('menu.pickup.cart_empty') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-4">
                <p class="text-sm text-stone-400">{{ __('menu.pickup.total') }} <span id="pickup-cart-total" class="text-lg font-bold text-amber-500">Rp 0</span></p>
                <a id="pickup-wa-link" href="#" target="_blank" rel="noopener" aria-disabled="true" class="pointer-events-none inline-flex items-center gap-2 rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-stone-950 opacity-40 transition hover:bg-emerald-400">
                    {{ __('menu.pickup.order') }}
                </a>
            </div>
        </div>
    </div>
</aside>

<script>
    (function () {
        var buttons = document.querySelectorAll('.category-filter-btn');
        var cards = document.querySelectorAll('.menu-card');
        var empty = document.getElementById('menu-empty');
        var activeClass = ['border-amber-500/60', 'bg-amber-500/10', 'text-amber-400'];
        var baseClass = ['border-stone-700', 'text-stone-300'];

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

        function setActive(button) {
            buttons.forEach(function (b) {
                var tokens = b === button ? activeClass : baseClass;
                b.classList.remove.apply(b.classList, b === button ? baseClass : activeClass);
                b.classList.add.apply(b.classList, tokens);
            });
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                setActive(button);
                apply(button.dataset.filter);
            });
        });
    })();

    (function () {
        var cart = document.getElementById('pickup-cart');
        if (!cart) {
            return;
        }

        var i18n = JSON.parse(document.getElementById('wa-pickup-i18n').textContent);
        var phone = cart.dataset.waPhone;
        var items = {};
        var cartState = {};

        document.querySelectorAll('.menu-card[data-available="1"]').forEach(function (card) {
            items[card.dataset.itemId] = {
                name: card.dataset.itemName,
                price: parseInt(card.dataset.itemPrice, 10),
            };
        });

        function fill(template, values) {
            return template.replace(/:(\w+)/g, function (_, key) {
                return values[key] !== undefined ? values[key] : '';
            });
        }

        function formatPrice(value) {
            return 'Rp ' + value.toLocaleString('id-ID');
        }

        var cartItems = document.getElementById('pickup-cart-items');
        var cartEmpty = document.getElementById('pickup-cart-empty');
        var cartTotal = document.getElementById('pickup-cart-total');
        var waLink = document.getElementById('pickup-wa-link');

        function buildMessage() {
            var lines = [];
            var grand = 0;

            Object.keys(cartState).forEach(function (id) {
                var quantity = cartState[id];
                var item = items[id];
                var lineTotal = item.price * quantity;
                grand += lineTotal;
                lines.push(fill(i18n.item_line, {
                    name: item.name,
                    qty: quantity,
                    total: formatPrice(lineTotal),
                }));
            });

            return [
                i18n.wa_message,
                fill(i18n.message_title, { shop: i18n.shop }),
                '',
                lines.join('\n'),
                '',
                fill(i18n.message_total, { total: formatPrice(grand) }),
                i18n.message_pickup,
            ].join('\n');
        }

        function updateCart() {
            var grand = 0;

            cartItems.innerHTML = '';
            Object.keys(cartState).forEach(function (id) {
                var quantity = cartState[id];
                var item = items[id];
                grand += item.price * quantity;

                var li = document.createElement('li');
                li.className = 'flex items-center gap-2';
                li.textContent = fill(i18n.item_line, {
                    name: item.name,
                    qty: quantity,
                    total: formatPrice(item.price * quantity),
                });
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'flex h-5 w-5 items-center justify-center rounded-full border border-stone-700 text-xs text-stone-400 transition hover:border-red-500 hover:text-red-400';
                remove.textContent = '×';
                remove.setAttribute('aria-label', fill(i18n.remove_aria, { item: item.name }));
                remove.addEventListener('click', function () {
                    removeFromCart(id);
                });
                li.appendChild(remove);
                cartItems.appendChild(li);
            });

            var hasItems = Object.keys(cartState).length > 0;
            cartEmpty.classList.toggle('hidden', hasItems);
            cartTotal.textContent = formatPrice(grand);

            if (hasItems) {
                waLink.href = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(buildMessage());
                waLink.classList.remove('pointer-events-none', 'opacity-40');
                waLink.setAttribute('aria-disabled', 'false');
            } else {
                waLink.removeAttribute('href');
                waLink.classList.add('pointer-events-none', 'opacity-40');
                waLink.setAttribute('aria-disabled', 'true');
            }
        }

        function setQuantity(id, quantity) {
            var add = document.querySelector('[data-add="' + id + '"]');
            var stepper = document.querySelector('[data-stepper="' + id + '"]');
            var qtyLabel = document.querySelector('[data-qty="' + id + '"]');

            if (quantity > 0) {
                cartState[id] = quantity;
                add.classList.add('hidden');
                stepper.classList.remove('hidden');
            } else {
                delete cartState[id];
                add.classList.remove('hidden');
                stepper.classList.add('hidden');
            }
            qtyLabel.textContent = quantity;
            updateCart();
        }

        function removeFromCart(id) {
            setQuantity(id, 0);
        }

        document.querySelectorAll('[data-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                setQuantity(button.dataset.add, 1);
            });
        });

        document.querySelectorAll('[data-inc]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.dataset.inc;
                setQuantity(id, (cartState[id] || 0) + 1);
            });
        });

        document.querySelectorAll('[data-dec]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.dataset.dec;
                setQuantity(id, Math.max(0, (cartState[id] || 0) - 1));
            });
        });

        updateCart();
    })();
</script>

@endsection
