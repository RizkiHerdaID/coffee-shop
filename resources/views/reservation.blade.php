@extends('layouts.app')

@section('title', __('reservation.form.heading'))

@section('content')
<section class="mx-auto max-w-4xl px-4 pt-32 pb-20 sm:px-6 sm:pt-40">
    <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">{{ __('reservation.label') }}</p>
    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ __('reservation.form.heading') }}</h1>
    <p class="mt-4 max-w-lg text-sm leading-relaxed text-stone-400">{{ __('reservation.form.subheading') }}</p>

    @if (session('success'))
    <div class="mt-8 flex items-start gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-6" role="status" aria-live="polite">
        <svg class="mt-0.5 h-6 w-6 shrink-0 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12.5 2.5 2.5 5-5.5"></path>
        </svg>
        <p class="text-sm leading-relaxed font-medium text-emerald-300">{{ session('success') }}</p>
    </div>
    @endif

    <form method="POST" action="{{ route('reservation') }}" class="mt-12 rounded-2xl border border-stone-800 bg-stone-900/60 p-8">
        @csrf

        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden">

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="name" class="block text-sm font-medium text-stone-300">{{ __('reservation.form.name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" placeholder="{{ __('reservation.form.name_placeholder') }}"
                    class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-white placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                @error('name')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-stone-300">{{ __('reservation.form.phone') }}</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" placeholder="{{ __('reservation.form.phone_placeholder') }}"
                    class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-white placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                @error('phone')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="party_size" class="block text-sm font-medium text-stone-300">{{ __('reservation.form.party_size') }}</label>
                <input type="number" id="party_size" name="party_size" value="{{ old('party_size') }}" required min="1" max="20" inputmode="numeric" placeholder="{{ __('reservation.form.party_size_placeholder') }}"
                    class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-white placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                @error('party_size')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="time" class="block text-sm font-medium text-stone-300">{{ __('reservation.form.time') }}</label>
                <input type="time" id="time" name="time" value="{{ old('time') }}" required
                    class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-white focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                @error('time')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="date" class="block text-sm font-medium text-stone-300">{{ __('reservation.form.date') }}</label>
                <input type="date" id="date" name="date" value="{{ old('date') }}" required min="{{ date('Y-m-d') }}"
                    class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-white focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                @error('date')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="notes" class="block text-sm font-medium text-stone-300">{{ __('reservation.form.notes') }}</label>
                <textarea id="notes" name="notes" rows="3" maxlength="500" placeholder="{{ __('reservation.form.notes_placeholder') }}"
                    class="mt-2 w-full rounded-lg border border-stone-700 bg-stone-950 px-4 py-2.5 text-sm text-white placeholder-stone-500 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">{{ old('notes') }}</textarea>
                @error('notes')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <button type="submit" class="mt-8 inline-flex w-full items-center justify-center gap-2 rounded-full bg-amber-500 px-6 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 sm:w-auto sm:px-10">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"></path>
            </svg>
            {{ __('reservation.form.submit') }}
        </button>
    </form>
</section>

<script>
    (function () {
        var dateInput = document.getElementById('date');
        if (!dateInput) {
            return;
        }
        var now = new Date();
        var today = now.getFullYear() + '-' +
            String(now.getMonth() + 1).padStart(2, '0') + '-' +
            String(now.getDate()).padStart(2, '0');
        dateInput.setAttribute('min', today);
    })();
</script>
@endsection
