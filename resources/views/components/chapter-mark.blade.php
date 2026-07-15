@props([
    // Chapter identifier — maps to a PNG under public/images/chapters/.
    // Supported: produce|meat|fish|eggdairy|bread. producers|almanach have
    // no mark asset yet and render as an empty box.
    'chapter' => null,

    // 'color' (default) or 'white' for use on dark backgrounds.
    'variant' => 'color',

    // Tailwind size utility shortcut. Pass any h-* w-* via class= to override.
    'size' => 'md',

    // Optional alt text override; falls back to a capitalized chapter name.
    'alt' => null,
])

@php
    $markByChapter = [
        'produce' => 'onion',
        'meat' => 'beef',
        'fish' => 'scallop',
        'eggdairy' => 'cheese',
        'bread' => 'wheat',
    ];
    $mark = $markByChapter[$chapter] ?? null;

    if ($mark === null) {
        return; // no illustration for producers/almanach yet
    }

    $suffix = $variant === 'white' ? '-white' : '';
    $file = "images/chapters/{$mark}{$suffix}.webp";

    $sizeClasses = match ($size) {
        'xs' => 'h-8 w-8',
        'sm' => 'h-14 w-14',
        'md' => 'h-20 w-20',
        'lg' => 'h-32 w-32',
        'xl' => 'h-48 w-48',
        default => $size, // raw Tailwind utility string
    };

    $altText = $alt ?? ucfirst((string) $chapter);
@endphp

<img
    src="{{ asset($file) }}"
    alt="{{ $altText }}"
    loading="lazy"
    decoding="async"
    {{ $attributes->class(['select-none object-contain', $sizeClasses]) }}
/>
