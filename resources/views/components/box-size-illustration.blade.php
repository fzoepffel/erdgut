{{-- Brand box artwork per size — shipped on the onboarding preferences
     card, the dashboard 'current box' tile, and the admin Abos size
     column. Each illustration is a wooden crate filled proportionally
     to the size: Klein has a few items, Mittel is comfortably full,
     Groß brims over.

     The chapter palette varies by size as a quiet visual accent on the
     surrounding label/eyebrow:

       small  -> chapter-produce  (#21341d  / soft #709452 — fresh greens)
       medium -> chapter-bread    (#944e22  / soft #dec1ae — grounded earth)
       large  -> chapter-almanach (#703b46  / soft #d8c1c8 — harvest plum)

     The image itself stays full-colour — the chapter accent goes on
     borders, eyebrows, and selected-state rings, not on the photograph.

     `$variant` controls the rendered HTML tag size:
       - `card`     ~h-40 (onboarding preferences cards)
       - `tile`     ~h-24 (dashboard current-box tile)
       - `thumb`    ~h-12 (admin Abos table cell)
       - `inline`   defers to caller's classes via $class
--}}
@props([
    'size' => 'medium',
    'variant' => 'card',
    'class' => null,
])

@php
    $size = in_array($size, ['small', 'medium', 'large'], true) ? $size : 'medium';

    $variantClass = match ($variant) {
        'card' => 'h-40 w-auto sm:h-48',
        'tile' => 'h-24 w-auto',
        'thumb' => 'h-10 w-10',
        default => '',
    };

    $alt = match ($size) {
        'small' => __('Small Erdgut box illustration'),
        'medium' => __('Medium Erdgut box illustration'),
        'large' => __('Large Erdgut box illustration'),
    };
@endphp

<picture>
    <source srcset="{{ asset('images/box-sizes/box-'.$size.'.webp') }}" type="image/webp">
    <img
        src="{{ asset('images/box-sizes/box-'.$size.'.png') }}"
        alt="{{ $alt }}"
        loading="lazy"
        decoding="async"
        class="{{ trim(($class ?? '').' '.$variantClass) }}"
    >
</picture>
