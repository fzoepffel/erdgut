@props([
    // Names to sign off. Defaults to the three founders.
    'names' => 'Lucille, Arthur & Milan',

    // Optional closing phrase, e.g. "Mit herzlichen Grüßen".
    'closing' => null,

    // Text size variant.
    'size' => 'md',

    // Color variant for dark/light placement.
    'color' => 'ink',
])

@php
    $sizeClasses = match ($size) {
        'sm' => 'text-lg',
        'lg' => 'text-3xl sm:text-4xl',
        default => 'text-xl sm:text-2xl',
    };

    $colorClass = $color === 'cream' ? 'text-brand-cream' : 'text-brand-ink';
@endphp

<div {{ $attributes->class(['flex flex-col gap-2', $colorClass]) }}>
    @if ($closing)
        <p class="font-sans text-sm italic opacity-80">{{ $closing }}</p>
    @endif
    <p class="font-editorial italic leading-tight {{ $sizeClasses }}">
        — {{ $names }}
    </p>
</div>
