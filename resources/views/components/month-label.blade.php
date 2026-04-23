@props([
    // Month text, already localised. Defaults to current month in the app locale.
    'month' => null,

    // Optional year suffix.
    'year' => null,

    // Color variant: 'cream' for dark imagery, 'ink' for light surfaces.
    'color' => 'cream',
])

@php
    if ($month === null) {
        $month = \Illuminate\Support\Carbon::now()->translatedFormat('F');
    }

    $colorClass = $color === 'ink' ? 'text-brand-ink' : 'text-brand-cream';
@endphp

<div {{ $attributes->class(['inline-flex items-baseline gap-2 font-display text-xs font-bold uppercase tracking-eyebrow', $colorClass]) }}>
    <span>{{ mb_strtoupper((string) $month) }}</span>
    @if ($year)
        <span class="opacity-70">{{ $year }}</span>
    @endif
</div>
