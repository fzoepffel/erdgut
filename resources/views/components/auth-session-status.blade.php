@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'border border-category-produce/40 bg-category-produce/5 px-4 py-3 text-sm font-medium text-brand-ink']) }} role="status">
        {{ $status }}
    </div>
@endif
