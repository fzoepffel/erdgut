@props([
    // Optional item number rendered as a small chip on the photo. Pass an
    // int (the box item index) when rendering inside a composed-box
    // manifest; pass null when rendering a portfolio preview where
    // numbering would imply a fixed order that doesn't exist.
    'number' => null,

    // Product display name (Fraunces, text-xl).
    'name' => '',

    // Quantity + unit string e.g. "500 g", "1 Bund". Pass null to hide
    // the quantity slot entirely (portfolio preview state).
    'quantityLabel' => null,

    // Producer name + region — rendered together as a single eyebrow line
    // like "HOF APFELTRAUM · MÜNCHEBERG". Either can be empty.
    'producerName' => null,
    'producerRegion' => null,

    // Resolved image URL. Caller is responsible for the fallback chain
    // (product image → category placeholder). Passing null renders a
    // neutral cream placeholder.
    'imageUrl' => null,

    // Short 1-line descriptor (Fraunces italic). Truncated to ~3 lines
    // by line-clamp; longer text gets cut so the card height stays
    // predictable in a 3-up grid.
    'description' => null,

    // Allergen label strings (already translated). When the array is
    // non-empty, chips render below the description.
    'allergens' => [],

    // is_extra flag from BoxItem. Shows a small "Extra add-on" pill in
    // the photo's top-right corner.
    'isExtra' => false,
])

@php
    $producerLine = trim(implode(' · ', array_filter([
        $producerName !== null && $producerName !== '' ? mb_strtoupper((string) $producerName) : null,
        $producerRegion !== null && $producerRegion !== '' ? mb_strtoupper((string) $producerRegion) : null,
    ])));
@endphp

<article
    {{ $attributes->class(['flex flex-col']) }}
    data-testid="customer-item-card"
>
    {{-- Photo with optional №-chip and Extra pill --}}
    <div class="relative aspect-[4/3] w-full overflow-hidden bg-brand-paper">
        @if ($imageUrl)
            <img
                src="{{ $imageUrl }}"
                alt=""
                role="presentation"
                class="h-full w-full object-cover"
                loading="lazy"
                decoding="async"
            />
        @endif

        @if ($number !== null)
            <span
                class="absolute left-3 top-3 inline-flex items-center bg-brand-cream/95 px-2 py-1 text-[11px] font-semibold tracking-eyebrow text-brand-ink shadow-[0_1px_0_rgba(0,0,0,0.04)]"
                aria-hidden="true"
            >
                № {{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}
            </span>
        @endif

        @if ($isExtra)
            <span class="absolute right-3 top-3 inline-flex items-center border border-brand-ink/40 bg-brand-cream/95 px-2 py-1 text-[10px] font-semibold uppercase tracking-eyebrow text-brand-ink">
                {{ __('Extra add-on') }}
            </span>
        @endif
    </div>

    {{-- Name + quantity row --}}
    <div class="mt-4 flex items-baseline justify-between gap-3">
        <h3 class="font-display text-xl leading-tight text-brand-ink">{{ $name }}</h3>
        @if ($quantityLabel !== null && $quantityLabel !== '')
            <span class="shrink-0 text-xs tabular-nums text-brand-ink/65">{{ $quantityLabel }}</span>
        @endif
    </div>

    {{-- Hairline divider --}}
    <div class="mt-3 border-t border-brand-ink/15"></div>

    {{-- Producer line + 1-liner description --}}
    @if ($producerLine !== '')
        <p class="mt-3 text-[11px] font-semibold tracking-eyebrow text-chapter-producers">{{ $producerLine }}</p>
    @endif

    @if ($description !== null && $description !== '')
        <p class="mt-2 font-editorial text-sm italic leading-snug text-brand-ink/75 line-clamp-3">
            {{ $description }}
        </p>
    @endif

    @if ($allergens !== [])
        <div class="mt-3 flex flex-wrap gap-1">
            @foreach ($allergens as $label)
                <span class="inline-flex items-center border border-amber-700/30 bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-eyebrow text-amber-900">
                    {{ $label }}
                </span>
            @endforeach
        </div>
    @endif
</article>
