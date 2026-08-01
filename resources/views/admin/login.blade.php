<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — Coffee Shop</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-stone-950 px-4 font-sans text-stone-200 antialiased">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <p class="text-4xl">&#9749;</p>
            <h1 class="mt-4 text-2xl font-bold text-white">Admin Login</h1>
            <p class="mt-1 text-sm text-stone-500">Coffee Shop back office</p>
        </div>

        <form method="POST" action="{{ route('admin.login.attempt') }}" class="rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
            @csrf

            @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="mb-5">
                <label for="email" class="mb-2 block text-sm font-semibold text-stone-300">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5 text-stone-100 placeholder-stone-600 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
            </div>

            <div class="mb-6">
                <label for="password" class="mb-2 block text-sm font-semibold text-stone-300">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                    class="w-full rounded-xl border border-stone-700 bg-stone-950 px-4 py-2.5 text-stone-100 placeholder-stone-600 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20">
            </div>

            <button type="submit" class="w-full rounded-full bg-amber-500 px-8 py-3 font-semibold text-stone-950 transition hover:bg-amber-400">
                Sign in
            </button>
        </form>
    </div>
</body>
</html>
