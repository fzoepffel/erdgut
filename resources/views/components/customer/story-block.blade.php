@props([
    // Chapter accent for the eyebrow + the drop-cap colour.
    'chapter' => 'produce',

    // Optional companion image rendered to the side of the quote.
    'image' => null,

    // Eyebrow above the quote.
    'eyebrow' => null,

    // The quote / editorial body. Plain string; first letter gets the drop-cap.
    'quote' => '',

    // Optional signature line (e.g. "— Lucille, Arthur & Milan").
    'signature' => null,
])

@php
    $eyebrowText = [
        'produce' => 'text-chapter-produce',
        'meat' => 'text-chapter-meat',
        'fish' => 'text-chapter-fish',
        'eggdairy' => 'text-chapter-eggdairy',
        'bread' => 'text-chapter-bread',
        'producers' => 'text-chapter-producers',
        'almanach' => 'text-chapter-almanach',
    ][$chapter] ?? 'text-chapter-produce';

    $softBg = [
        'produce' => 'bg-chapter-produce/[0.06]',
        'meat' => 'bg-chapter-meat/[0.06]',
        'fish' => 'bg-chapter-fish/[0.06]',
        'eggdairy' => 'bg-chapter-eggdairy/[0.06]',
        'bread' => 'bg-chapter-bread/[0.06]',
        'producers' => 'bg-chapter-producers/[0.06]',
        'almanach' => 'bg-chapter-almanach/[0.06]',
    ][$chapter] ?? 'bg-chapter-produce/[0.06]';

    // The .drop-cap utility in app.css reads --chapter-ink. Set it inline
    // so each instance picks up its own accent.
    $chapterInkHex = [
        'produce' => '#21341d',
        'meat' => '#ac273b',
        'fish' => '#1d4485',
        'eggdairy' => '#e46f25',
        'bread' => '#944e22',
        'producers' => '#a37a2c',
        'almanach' => '#703b46',
    ][$chapter] ?? '#21341d';
@endphp

<section
    {{ $attributes->class(['border border-brand-ink/10', $softBg]) }}
    data-testid="customer-story-block"
>
    <div class="@if ($image) grid gap-0 md:grid-cols-12 @endif">
        @if ($image)
            <x-reveal variant="fade" class="md:col-span-5">
                <div class="relative h-64 overflow-hidden md:h-full md:min-h-[20rem]">
                    <img
                        src="{{ $image }}"
                        alt=""
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                </div>
            </x-reveal>
        @endif

        <x-reveal variant="fade-up" delay="120" :class="$image ? 'md:col-span-7' : ''">
            <div class="px-6 py-10 sm:px-10 sm:py-12" style="--chapter-ink: {{ $chapterInkHex }};">
                @if ($eyebrow)
                    <p class="eyebrow {{ $eyebrowText }}">{{ $eyebrow }}</p>
                @endif

                <p class="drop-cap mt-4 font-editorial text-lg italic leading-relaxed text-brand-ink/90 sm:text-xl">
                    {{ $quote }}
                </p>

                @if ($signature)
                    <div class="mt-6 flex items-center gap-3">
                        <x-triple-star class="opacity-70" />
                        <p class="font-editorial text-sm italic text-brand-ink/70">{{ $signature }}</p>
                    </div>
                @endif
            </div>
        </x-reveal>
    </div>
</section>
