@props([
    'eyebrow' => null,
    'title' => '',
    'lead' => null,
    'color' => 'produce',
    'align' => 'left',
    'level' => 2,
])

@php
    $alignClass = $align === 'center' ? 'text-center mx-auto' : '';
    $tag = 'h' . max(1, min(6, (int) $level));
@endphp

<div {{ $attributes->class(['max-w-3xl', $alignClass]) }}>
    @if ($eyebrow)
        <x-reveal variant="fade-up">
            <x-eyebrow :color="$color">{{ $eyebrow }}</x-eyebrow>
        </x-reveal>
    @endif

    <x-reveal variant="fade-up" delay="80">
        <{{ $tag }} class="mt-3 font-display text-4xl leading-tight tracking-tight text-brand-ink sm:text-5xl md:text-6xl">
            {{ $title }}
        </{{ $tag }}>
    </x-reveal>

    @if ($lead)
        <x-reveal variant="fade-up" delay="160">
            <p class="mt-5 max-w-2xl text-base leading-relaxed text-brand-ink/75 sm:text-lg {{ $align === 'center' ? 'mx-auto' : '' }}">
                {{ $lead }}
            </p>
        </x-reveal>
    @endif

    @isset($actions)
        <x-reveal variant="fade-up" delay="220">
            <div class="mt-8 flex flex-wrap items-center gap-3 {{ $align === 'center' ? 'justify-center' : '' }}">
                {{ $actions }}
            </div>
        </x-reveal>
    @endisset
</div>
