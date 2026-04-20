@props(['active'])

@php
    $base = 'relative inline-flex items-center px-1 pt-1 text-sm font-medium leading-5 transition duration-200 focus:outline-none';
    $classes = ($active ?? false)
        ? $base . ' text-brand-ink after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:bg-category-produce'
        : $base . ' text-brand-ink/70 hover:text-brand-ink';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
