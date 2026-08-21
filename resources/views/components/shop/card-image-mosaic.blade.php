@props(['images' => [], 'fallback' => null])

{{--
    Square card image for the storefront grid. One product shows its single
    photo; a product GROUP shows every available member's photo tiled as one
    mosaic (2 = split, 3 = 1+2, 4 = 2x2, 5+ = 2x2 with a "+N" tile), so no
    single member reads as the lead.

    Each tile links to ITS OWN product detail page. The "+N" tile opens a small
    gallery of every group photo (each a link to its product) instead of going
    nowhere. Callers pass only the members available, already de-duplicated by
    image content, each as ['url' => ..., 'alt' => ..., 'href' => ...]; the
    `fallback` href is used when a product has no photo at all.
--}}
@php
    $imgs = collect($images)
        ->filter(fn ($i) => ! empty($i['url']))
        ->values();
    $n = $imgs->count();

    // Cell plan. row-span-2 makes the 3-photo layout a tall-left mosaic; for
    // 5+ the fourth tile carries the "+N" overflow badge over the 4th photo.
    $cells = match (true) {
        $n <= 1 => [],
        $n === 2 => [['i' => 0], ['i' => 1]],
        $n === 3 => [['i' => 0, 'span' => 'row-span-2'], ['i' => 1], ['i' => 2]],
        $n === 4 => [['i' => 0], ['i' => 1], ['i' => 2], ['i' => 3]],
        default => [['i' => 0], ['i' => 1], ['i' => 2], ['i' => 3, 'more' => $n - 3]],
    };
    $hasGallery = $n > 4;
@endphp

@if ($n === 0)
    @if ($fallback)
        <a href="{{ $fallback }}" class="flex h-full w-full items-center justify-center text-shop-muted">
            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
        </a>
    @else
        <span class="flex h-full w-full items-center justify-center text-shop-muted" aria-hidden="true">
            <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
        </span>
    @endif
@elseif ($n === 1)
    <a href="{{ $imgs[0]['href'] ?? $fallback }}" class="block h-full w-full">
        <img src="{{ $imgs[0]['url'] }}" alt="{{ $imgs[0]['alt'] ?? '' }}" width="600" height="600" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" loading="lazy" />
    </a>
@else
    <div @if ($hasGallery) x-data="{ galleryOpen: false }" @endif class="h-full w-full">
        <div class="grid h-full w-full grid-cols-2 gap-px bg-shop-border {{ $n >= 3 ? 'grid-rows-2' : '' }}">
            @foreach ($cells as $cell)
                @php $img = $imgs[$cell['i']]; @endphp
                @if (! empty($cell['more']))
                    <button type="button" @click="galleryOpen = true"
                        aria-label="{{ __('shop.group_show_all_photos') }}"
                        class="relative block overflow-hidden {{ $cell['span'] ?? '' }}">
                        <img src="{{ $img['url'] }}" alt="" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" loading="lazy" />
                        <span class="absolute inset-0 flex items-center justify-center bg-shop-foreground/55">
                            <span class="font-mono text-base tracking-widest text-shop-paper">+{{ $cell['more'] }}</span>
                        </span>
                    </button>
                @else
                    <a href="{{ $img['href'] ?? $fallback }}" class="relative block overflow-hidden {{ $cell['span'] ?? '' }}">
                        <img src="{{ $img['url'] }}" alt="{{ $img['alt'] ?? '' }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" loading="lazy" />
                    </a>
                @endif
            @endforeach
        </div>

        @if ($hasGallery)
            <template x-teleport="body">
                <div x-show="galleryOpen" x-cloak style="display:none"
                    class="fixed inset-0 z-[60] flex items-center justify-center bg-shop-foreground/70 p-4"
                    x-transition.opacity
                    @click="galleryOpen = false" @keydown.escape.window="galleryOpen = false">
                    <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto bg-shop-paper p-5" @click.stop>
                        <div class="mb-4 flex items-center justify-between">
                            <p class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.group_all_photos') }}</p>
                            <button type="button" @click="galleryOpen = false" aria-label="{{ __('Close') }}" class="font-mono text-xl leading-none text-shop-primary">&times;</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            @foreach ($imgs as $img)
                                <a href="{{ $img['href'] ?? $fallback }}" class="group/gitem block">
                                    <div class="aspect-square overflow-hidden bg-shop-paper">
                                        <img src="{{ $img['url'] }}" alt="{{ $img['alt'] ?? '' }}" class="h-full w-full object-cover transition duration-500 group-hover/gitem:scale-[1.03]" loading="lazy" />
                                    </div>
                                    <p class="mt-1.5 font-mono text-[0.6rem] leading-snug text-shop-foreground">{{ $img['alt'] ?? '' }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>
        @endif
    </div>
@endif
