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
        ['inset', 'neutral'] => 'border border-dashed border-brand-ink/25 bg-brand-ink/[0.03] p-4',
        ['card', 'neutral'] => 'border border-dashed border-brand-ink/25 bg-transparent p-10 text-center',
        ['card', 'amber'] => 'border border-brand-ink bg-brand-ink/5 p-10 text-center',
        ['card-muted', 'neutral'] => 'border border-dashed border-brand-ink/20 bg-brand-ink/[0.03] p-10 text-center',
        default => 'border border-dashed border-brand-ink/25 bg-transparent p-10 text-center',
    };

    $titleClass = match (true) {
        $variant === 'inset' && $tone === 'neutral' => 'text-sm font-medium text-brand-ink',
        default => 'font-display text-2xl font-semibold text-brand-ink',
    };

    $titleMargin = isset($icon) ? 'mt-4' : 'mt-0';

    $descClass = match ($tone) {
        'amber' => 'mt-3 text-sm text-brand-ink/75',
        default => $variant === 'inset' ? 'mt-1 text-sm text-brand-ink/70' : 'mt-3 text-sm text-brand-ink/70',
    };

    $actionMargin = $variant === 'inset' ? 'mt-3' : 'mt-6';
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
