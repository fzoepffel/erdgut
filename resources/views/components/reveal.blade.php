@props([
    'variant' => 'fade-up',
    'delay' => 0,
    'as' => 'div',
])

@php
    $variantClass = match ($variant) {
        'fade' => 'reveal reveal--fade',
        'scale' => 'reveal reveal--scale',
        default => 'reveal',
    };

    $style = $delay ? 'transition-delay:' . intval($delay) . 'ms;' : null;
@endphp

<{{ $as }}
    {{ $attributes->class($variantClass)->merge(['style' => $style]) }}
    x-intersect.once="$el.classList.add('is-visible')"
>
    {{ $slot }}
</{{ $as }}>
