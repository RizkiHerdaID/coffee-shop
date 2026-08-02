<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', __('site.meta.default_description', ['shop' => config('shop.name')]))">
    <title>@yield('title', __('site.brand'))</title>

    <meta property="og:title" content="@yield('title', __('site.brand'))">
    <meta property="og:description" content="@yield('description', __('site.meta.default_description', ['shop' => config('shop.name')]))">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/favicon.ico') }}">
    <meta property="og:site_name" content="{{ config('shop.name') }}">
    <meta name="twitter:card" content="summary">

    @php
        $days = [
            'mon_fri' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'sat' => ['Saturday'],
            'sun' => ['Sunday'],
        ];

        $openingHours = collect(config('shop.hours'))->flatMap(function ($hours, $label) use ($days) {
            [$opens, $closes] = array_map('trim', explode('—', $hours));

            return array_map(fn ($day) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $day,
                'opens' => $opens,
                'closes' => $closes,
            ], $days[$label] ?? []);
        })->values();

        $localBusiness = [
            '@context' => 'https://schema.org',
            '@type' => 'Cafe',
            'name' => config('shop.name'),
            'telephone' => config('shop.phone'),
            'email' => config('shop.email'),
            'url' => url('/'),
            'hasMap' => config('shop.maps_url'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => config('shop.address'),
            ],
            'openingHoursSpecification' => $openingHours,
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($localBusiness, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        $fontsManifest = json_decode((string) file_get_contents(public_path('build/fonts-manifest.json')), true);
        $heroFontVariant = collect($fontsManifest['families']['instrument-sans']['variants'] ?? [])->get('600:normal');
        $heroFont = collect($heroFontVariant['files'] ?? [])->firstWhere('format', 'woff2');
    @endphp
    @if ($heroFont)
    <link rel="preload" href="{{ asset('build/'.$heroFont['file']) }}" as="font" type="font/woff2" crossorigin="anonymous" fetchpriority="high">
    @endif
    @fonts('instrument-sans')
</head>
<body class="bg-stone-950 text-stone-200 antialiased font-sans">
    <header class="fixed inset-x-0 top-0 z-50 border-b border-stone-800/60 bg-stone-950/80 backdrop-blur">
        @hasSection('promo-banner')
            @yield('promo-banner')
        @endif
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold tracking-tight text-amber-500">
                <span class="text-2xl">&#9749;</span>
                Coffee Shop
            </a>
            <div class="hidden items-center gap-8 text-sm font-medium text-stone-300 sm:flex">
                <a href="{{ route('home') }}" class="transition hover:text-amber-400">{{ __('site.nav.home') }}</a>
                <a href="{{ route('menu') }}" class="transition hover:text-amber-400">{{ __('site.nav.menu') }}</a>
                <a href="{{ route('points') }}" class="transition hover:text-amber-400">{{ __('site.nav.points') }}</a>
                <a href="{{ route('reservation') }}" class="transition hover:text-amber-400">{{ __('site.nav.reservation') }}</a>
                <a href="{{ route('contact') }}" class="transition hover:text-amber-400">{{ __('site.nav.contact') }}</a>
                @include('partials.language-switcher')
                <a href="{{ route('reservation') }}" class="rounded-full bg-amber-500 px-5 py-2 font-semibold text-stone-950 transition hover:bg-amber-400">
                    {{ __('site.nav.reserve') }}
                </a>
            </div>
            <button class="text-stone-300 sm:hidden" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" aria-label="{{ __('site.nav.toggle_aria') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </nav>
        <div id="mobile-menu" class="hidden border-t border-stone-800 px-4 py-3 text-sm font-medium sm:hidden">
            <div class="mb-3">@include('partials.language-switcher')</div>
            <a href="{{ route('home') }}" class="block py-2 text-stone-300">{{ __('site.nav.home') }}</a>
            <a href="{{ route('menu') }}" class="block py-2 text-stone-300">{{ __('site.nav.menu') }}</a>
            <a href="{{ route('points') }}" class="block py-2 text-stone-300">{{ __('site.nav.points') }}</a>
            <a href="{{ route('reservation') }}" class="block py-2 text-stone-300">{{ __('site.nav.reservation') }}</a>
            <a href="{{ route('contact') }}" class="block py-2 text-stone-300">{{ __('site.nav.contact') }}</a>
            <a href="{{ route('reservation') }}" class="mt-2 block rounded-full bg-amber-500 px-5 py-2 text-center font-semibold text-stone-950">{{ __('site.nav.reserve') }}</a>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-stone-800 bg-stone-950 py-10">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 text-sm text-stone-500 sm:flex-row sm:px-6">
            <p>{{ __('site.footer.tagline', ['year' => date('Y'), 'shop' => config('shop.name')]) }}</p>
            <div class="flex gap-6">
                <a href="{{ route('menu') }}" class="transition hover:text-amber-400">{{ __('site.footer.menu') }}</a>
                <a href="{{ route('contact') }}" class="transition hover:text-amber-400">{{ __('site.footer.hours_location') }}</a>
            </div>
        </div>
    </footer>
</body>
</html>
