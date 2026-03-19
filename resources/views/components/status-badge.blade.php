@props([
    'value',
    'type' => null,
])

@php
    $statusValue = is_object($value) && isset($value->value) ? $value->value : (string) $value;

    $baseClasses = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium';
    $statusClasses = match ($statusValue) {
        'scheduled', 'editable', 'active' => 'bg-emerald-100 text-emerald-700',
        'delivered' => 'bg-sky-100 text-sky-700',
        'locked', 'paused' => 'bg-amber-100 text-amber-700',
        'skipped', 'cancelled' => 'bg-rose-100 text-rose-700',
        default => 'bg-gray-100 text-gray-700',
    };

    $label = ucfirst(str_replace('_', ' ', $statusValue));
@endphp

<span {{ $attributes->class([$baseClasses, $statusClasses]) }} data-status-type="{{ $type }}">
    {{ $label }}
</span>
