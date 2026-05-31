@props([
    'value',
    'type' => null,
])

@php
    $statusValue = is_object($value) && isset($value->value) ? $value->value : (string) $value;

    $baseClasses = 'inline-flex items-center px-2.5 py-1 text-[11px] font-semibold uppercase tracking-eyebrow';
    $statusClasses = match ($statusValue) {
        'scheduled', 'editable', 'active', 'paid' => 'bg-chapter-produce/10 text-chapter-produce',
        'delivered', 'fulfilled' => 'bg-chapter-produce text-white',
        'locked', 'paused', 'pending', 'pending_payment' => 'border border-brand-ink text-brand-ink',
        'skipped', 'cancelled', 'failed' => 'bg-chapter-meat/10 text-chapter-meat',
        default => 'bg-brand-ink/10 text-brand-ink/80',
    };

    $humanLabel = ucfirst(str_replace('_', ' ', $statusValue));
    $translationKey = 'status.' . $statusValue;
    $translated = __($translationKey);
    $label = $translated === $translationKey ? $humanLabel : $translated;
@endphp

<span {{ $attributes->class([$baseClasses, $statusClasses]) }} data-status-type="{{ $type }}">
    {{ $label }}
</span>
