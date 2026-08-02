@if ($promo ?? null)
<div id="promo-banner" data-promo-id="{{ $promo->id }}" class="border-b border-stone-950/10 bg-amber-500 text-stone-950">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-2 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            @if ($promo->badge)
            <span class="shrink-0 rounded-full bg-stone-950 px-2.5 py-0.5 text-xs font-bold uppercase tracking-widest text-amber-400">{{ $promo->badge }}</span>
            @endif
            <p class="truncate text-sm font-semibold">
                {{ $promo->title }}
                @if ($promo->subtitle)
                <span class="font-normal text-stone-800">&mdash; {{ $promo->subtitle }}</span>
                @endif
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
            @if ($promo->cta_text && $promo->cta_url)
            <a href="{{ $promo->cta_url }}" class="shrink-0 rounded-full bg-stone-950 px-4 py-1.5 text-xs font-bold text-amber-400 transition hover:bg-stone-800">{{ $promo->cta_text }}</a>
            @endif
            <button type="button" data-dismiss-promo aria-label="{{ __('site.banner.dismiss_aria') }}" class="shrink-0 rounded-full px-1.5 text-xl leading-none transition hover:bg-stone-950/10">&times;</button>
        </div>
    </div>
</div>
<script>
    (function () {
        var banner = document.getElementById('promo-banner');
        if (!banner) {
            return;
        }

        var key = 'promo-dismissed-' + banner.dataset.promoId;

        if (localStorage.getItem(key) === '1') {
            banner.remove();
            return;
        }

        var dismiss = banner.querySelector('[data-dismiss-promo]');
        if (dismiss) {
            dismiss.addEventListener('click', function () {
                localStorage.setItem(key, '1');
                banner.remove();
            });
        }
    })();
</script>
@endif
