<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — Coffee Shop Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-950 text-stone-200 antialiased font-sans">
    <header class="border-b border-stone-800 bg-stone-900/60">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-lg font-bold tracking-tight text-amber-500">
                <span class="text-2xl">&#9749;</span>
                Coffee Shop
                <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-semibold uppercase tracking-widest text-amber-500">Admin</span>
            </a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="rounded-full border border-stone-700 px-5 py-2 text-sm font-semibold text-stone-200 transition hover:border-amber-500 hover:text-amber-400">
                    Log out
                </button>
            </form>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        @yield('content')
    </main>
</body>
</html>
