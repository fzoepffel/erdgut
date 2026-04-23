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
    $file = "images/chapters/{$mark}{$suffix}.png";

    $sizeClasses = match ($size) {
        'xs' => 'h-6 w-6',
        'sm' => 'h-10 w-10',
        'md' => 'h-16 w-16',
        'lg' => 'h-24 w-24',
        'xl' => 'h-36 w-36',
        default => $size, // raw Tailwind utility string
    };

    $altText = $alt ?? ucfirst((string) $chapter);
@endphp

<img
    src="{{ asset($file) }}"
    alt="{{ $altText }}"
    {{ $attributes->class(['select-none object-contain', $sizeClasses]) }}
/>
