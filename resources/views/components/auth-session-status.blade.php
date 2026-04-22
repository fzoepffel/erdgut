@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm font-medium text-brand-ink']) }} role="status">
        {{ $status }}
    </div>
@endif
