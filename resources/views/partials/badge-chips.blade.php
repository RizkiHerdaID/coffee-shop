@if (! empty($item->badges))
<div class="mt-2 flex flex-wrap gap-1.5">
    @foreach ($item->badges as $badge)
    <span class="inline-flex items-center rounded-full border border-amber-500/40 bg-amber-500/10 px-2.5 py-0.5 text-xs font-semibold text-amber-300">{{ \Illuminate\Support\Facades\Lang::has("menu.badges.$badge") ? __("menu.badges.$badge") : $badge }}</span>
    @endforeach
</div>
@endif
