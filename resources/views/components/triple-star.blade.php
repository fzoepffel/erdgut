@props([
    // 'accent' (red, the catalogue default) or 'ink' / 'cream' for contexts
    // that need a calmer color.
    'color' => 'accent',
])

@php
    $colorClass = match ($color) {
        'ink' => 'text-brand-ink',
        'cream' => 'text-brand-cream',
        default => 'text-accent-star',
    };
@endphp

<div {{ $attributes->class(['flex items-center justify-center gap-3 font-display text-lg leading-none', $colorClass]) }} aria-hidden="true">
    <span>✱</span>
    <span>✱</span>
    <span>✱</span>
</div>
