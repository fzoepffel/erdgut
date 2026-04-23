@props([
    'variant' => 'filled',
    'withWordmark' => true,
    'layout' => 'stacked', // 'stacked' (mark above wordmark, square) | 'inline' (horizontal wordmark-only)
    'alt' => 'Erdgut',
])

@php
    // Map legacy (filled|outline) prop values to the new asset set.
    // outline → white, used on dark hero overlays in place of the old invert hack.
    $isWhite = $variant === 'outline' || $variant === 'white';

    // `inline` layout uses the horizontal wordmark; the white version is rendered
    // via CSS filter (no dedicated asset yet) since the wordmark is monochrome.
    $isInlineWordmark = $layout === 'inline' && $withWordmark;

    $file = match (true) {
        $isInlineWordmark => 'erdgut-wordmark-tight.png',
        $withWordmark && $isWhite => 'erdgut-white.png',
        $withWordmark => 'erdgut.png',
        $isWhite => 'erdgut-mark-white.png',
        default => 'erdgut-mark.png',
    };

    $filterStyle = $isInlineWordmark && $isWhite ? 'filter: brightness(0) invert(1);' : '';
@endphp

<img
    src="{{ asset('images/logo/' . $file) }}"
    alt="{{ $alt }}"
    @if ($filterStyle) style="{{ $filterStyle }}" @endif
    {{ $attributes->merge(['class' => 'select-none']) }}
/>
