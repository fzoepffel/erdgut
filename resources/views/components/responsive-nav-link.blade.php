@props(['active'])

@php
    $isActive = (bool) ($active ?? false);
    $classes = $isActive
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-chapter-produce text-start text-base font-medium text-brand-ink bg-brand-ink/5 transition duration-200'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-brand-ink/70 hover:text-brand-ink hover:bg-brand-ink/5 transition duration-200';
@endphp

<a
    @if ($isActive) aria-current="page" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>
    {{ $slot }}
</a>
