<div class="flex items-center gap-1 rounded-full border border-stone-700 px-1.5 py-1 text-xs font-semibold" role="group" aria-label="{{ __('site.lang.label') }}">
    @if (app()->getLocale() === 'id')
        <span class="rounded-full bg-amber-500 px-2.5 py-0.5 text-stone-950">ID</span>
    @else
        <a href="{{ route('lang.switch', ['locale' => 'id']) }}" class="rounded-full px-2.5 py-0.5 text-stone-300 transition hover:text-amber-400" aria-label="Ganti bahasa ke Bahasa Indonesia">ID</a>
    @endif
    @if (app()->getLocale() === 'en')
        <span class="rounded-full bg-amber-500 px-2.5 py-0.5 text-stone-950">EN</span>
    @else
        <a href="{{ route('lang.switch', ['locale' => 'en']) }}" class="rounded-full px-2.5 py-0.5 text-stone-300 transition hover:text-amber-400" aria-label="Change language to English">EN</a>
    @endif
</div>
