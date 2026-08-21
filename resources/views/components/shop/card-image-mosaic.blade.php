@props(['images' => []])

{{--
    Square card image for the storefront grid. One product shows its single
    photo; a product GROUP shows every available member's photo tiled as one
    mosaic (2 = split, 3 = 1+2, 4 = 2x2, 5+ = 2x2 with a "+N" tile), so no
    single member reads as the lead. Callers pass only the members that are
    actually available, each as ['url' => ..., 'alt' => ...]; entries without
    a url are dropped and duplicate photos are collapsed.
--}}
@php
    $imgs = collect($images)
        ->filter(fn ($i) => ! empty($i['url']))
        ->unique('url')
        ->values();
    $n = $imgs->count();

    // Cell plan. For 5+ the fourth tile carries the "+N" overflow badge over
    // the fourth photo; row-span-2 makes the 3-photo layout a tall-left mosaic.
    $cells = match (true) {
        $n <= 1 => [],
        $n === 2 => [['i' => 0], ['i' => 1]],
        $n === 3 => [['i' => 0, 'span' => 'row-span-2'], ['i' => 1], ['i' => 2]],
        $n === 4 => [['i' => 0], ['i' => 1], ['i' => 2], ['i' => 3]],
        default => [['i' => 0], ['i' => 1], ['i' => 2], ['i' => 3, 'more' => $n - 3]],
    };
@endphp

@if ($n === 0)
    <span class="flex h-full w-full items-center justify-center text-shop-muted" aria-hidden="true">
        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
    </span>
@elseif ($n === 1)
    <img src="{{ $imgs[0]['url'] }}" alt="{{ $imgs[0]['alt'] ?? '' }}" width="600" height="600" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" loading="lazy" />
@else
    <div class="grid h-full w-full grid-cols-2 gap-px bg-shop-border {{ $n >= 3 ? 'grid-rows-2' : '' }}">
        @foreach ($cells as $cell)
            @php $img = $imgs[$cell['i']]; @endphp
            <div class="relative overflow-hidden {{ $cell['span'] ?? '' }}">
                <img src="{{ $img['url'] }}" alt="{{ $img['alt'] ?? '' }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" loading="lazy" />
                @if (! empty($cell['more']))
                    <div class="absolute inset-0 flex items-center justify-center bg-shop-foreground/55">
                        <span class="font-mono text-base tracking-widest text-shop-paper">+{{ $cell['more'] }}</span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
