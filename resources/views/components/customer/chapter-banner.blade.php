@props([
    // Chapter accent for the soft background and the eyebrow.
    'chapter' => 'produce',

    // Eyebrow above the title.
    'eyebrow' => null,

    // Display title.
    'title' => '',

    // Optional italic editorial lead under the title.
    'lead' => null,
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
        'produce' => 'bg-chapter-produce/[0.07]',
        'meat' => 'bg-chapter-meat/[0.07]',
        'fish' => 'bg-chapter-fish/[0.07]',
        'eggdairy' => 'bg-chapter-eggdairy/[0.07]',
        'bread' => 'bg-chapter-bread/[0.07]',
        'producers' => 'bg-chapter-producers/[0.07]',
        'almanach' => 'bg-chapter-almanach/[0.07]',
    ][$chapter] ?? 'bg-chapter-produce/[0.07]';

    $borderColor = [
        'produce' => 'border-chapter-produce/30',
        'meat' => 'border-chapter-meat/30',
        'fish' => 'border-chapter-fish/30',
        'eggdairy' => 'border-chapter-eggdairy/30',
        'bread' => 'border-chapter-bread/30',
        'producers' => 'border-chapter-producers/30',
        'almanach' => 'border-chapter-almanach/30',
    ][$chapter] ?? 'border-chapter-produce/30';
@endphp

<section
    {{ $attributes->class(['border', $borderColor, $softBg, 'px-6 py-8 sm:px-10 sm:py-10']) }}
    data-testid="customer-chapter-banner"
>
    <x-reveal variant="fade-up">
        @if ($eyebrow)
            <p class="eyebrow {{ $eyebrowText }}">{{ $eyebrow }}</p>
        @endif
        <h2 class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">{{ $title }}</h2>
        @if ($lead)
            <p class="mt-3 max-w-2xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ $lead }}
            </p>
        @endif
        @isset($slot)
            @if (trim((string) $slot) !== '')
                <div class="mt-5">{{ $slot }}</div>
            @endif
        @endisset
    </x-reveal>
</section>
