@props([
    // Which chapter this divider announces. Drives all the colors + mark.
    'chapter' => 'produce',

    // Short label shown above the title (e.g. 'Chapter 2', 'Kapitel 2').
    'eyebrow' => null,

    // Main title; required if slot is empty.
    'title' => null,

    // Optional subtitle beneath the title.
    'subtitle' => null,

    // Whether to render the onion/cheese/beef/etc. mark. Off by default
    // on the producers/almanach chapters which have no mark asset.
    'showMark' => true,
])

@php
    // Map chapter -> background + text color classes.
    $bgByChapter = [
        'produce' => 'bg-chapter-produce-soft text-chapter-produce',
        'meat' => 'bg-chapter-meat-soft text-chapter-meat',
        'fish' => 'bg-chapter-fish-soft text-chapter-fish',
        'eggdairy' => 'bg-chapter-eggdairy-soft text-chapter-eggdairy',
        'bread' => 'bg-chapter-bread-soft text-chapter-bread',
        'producers' => 'bg-chapter-producers-soft text-chapter-producers',
        'almanach' => 'bg-chapter-almanach-soft text-chapter-almanach',
    ];

    $wrapperClasses = $bgByChapter[$chapter] ?? 'bg-brand-paper text-brand-ink';

    // Chapters without a mark asset fall back to no illustration.
    $chaptersWithMark = ['produce', 'meat', 'fish', 'eggdairy', 'bread'];
    $renderMark = $showMark && in_array($chapter, $chaptersWithMark, true);
@endphp

<section {{ $attributes->class(['relative isolate overflow-hidden py-12 sm:py-16', $wrapperClasses]) }}>
    <div class="mx-auto flex max-w-5xl flex-col items-center gap-6 px-4 text-center sm:px-6 lg:px-8">
        @if ($renderMark)
            <x-chapter-mark :chapter="$chapter" size="lg" class="opacity-90" />
        @endif

        @if ($eyebrow)
            <p class="eyebrow opacity-80">{{ $eyebrow }}</p>
        @endif

        @if ($title || $slot->isNotEmpty())
            {{-- Near-black title reads against the soft pastel background regardless of browser colour mode. --}}
            <h2 class="font-display text-4xl font-semibold leading-tight tracking-tight text-brand-ink sm:text-5xl lg:text-6xl">
                {{ $title ?? $slot }}
            </h2>
        @endif

        @if ($subtitle)
            <p class="max-w-2xl font-editorial text-lg italic opacity-85">
                {{ $subtitle }}
            </p>
        @endif

        <x-triple-star color="accent" class="mt-2 opacity-90" />
    </div>
</section>
