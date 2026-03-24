@props([
    'title',
    'description' => null,
    'variant' => 'card',
    'tone' => 'neutral',
    'heading' => 'h2',
])

@php
    $headingTag = in_array($heading, ['h2', 'h3'], true) ? $heading : 'h2';

    $wrapper = match ([$variant, $tone]) {
        ['inset', 'neutral'] => 'rounded-md border border-dashed border-gray-200 bg-gray-50 p-4',
        ['card', 'neutral'] => 'rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center',
        ['card', 'amber'] => 'rounded-xl border border-amber-200 bg-amber-50 p-8 text-center',
        ['card-muted', 'neutral'] => 'rounded-xl border border-dashed border-gray-300 bg-gray-50/80 p-8 text-center',
        default => 'rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center',
    };

    $titleClass = match (true) {
        $variant === 'inset' && $tone === 'neutral' => 'text-sm font-medium text-gray-900',
        $tone === 'amber' => 'text-lg font-semibold text-amber-950',
        default => 'text-lg font-semibold text-gray-900',
    };

    $titleMargin = isset($icon) ? 'mt-4' : 'mt-0';

    $descClass = match ($tone) {
        'amber' => 'mt-2 text-sm text-amber-900/90',
        default => $variant === 'inset' ? 'mt-1 text-sm text-gray-600' : 'mt-2 text-sm text-gray-600',
    };

    $actionMargin = $variant === 'inset' ? 'mt-3' : 'mt-4';
@endphp

<div
    {{ $attributes->class($wrapper)->merge(['data-testid' => 'empty-state']) }}
>
    @isset($icon)
        <div class="flex justify-center">
            {{ $icon }}
        </div>
    @endisset

    <{{ $headingTag }} class="{{ $titleMargin }} {{ $titleClass }}">
        {{ $title }}
    </{{ $headingTag }}>

    @if (filled($description))
        <p class="{{ $descClass }}">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="{{ $actionMargin }}">
            {{ $action }}
        </div>
    @endisset
</div>
