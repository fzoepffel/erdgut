@props([
    'variant' => 'filled',
    'withWordmark' => true,
    'alt' => 'Erdgut',
])

@php
    // Map legacy (filled|outline) prop values to the new asset set.
    // outline → white, used on dark hero overlays in place of the old invert hack.
    $isWhite = $variant === 'outline' || $variant === 'white';

    $file = match (true) {
        $withWordmark && $isWhite => 'erdgut-white.png',
        $withWordmark => 'erdgut.png',
        $isWhite => 'erdgut-mark-white.png',
        default => 'erdgut-mark.png',
    };
@endphp

<img
    src="{{ asset('images/logo/' . $file) }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'select-none']) }}
/>
