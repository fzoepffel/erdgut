@props([
    'variant' => 'filled',
    'withWordmark' => true,
    'alt' => 'Erdgut',
])

@php
    $onion = $withWordmark ? '' : '-onion';
    $file = match ($variant) {
        'outline' => "erdgut{$onion}-outline.png",
        default => "erdgut{$onion}-filled.png",
    };
@endphp

<img
    src="{{ asset('images/logo/' . $file) }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'select-none']) }}
/>
