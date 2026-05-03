@props([
    // Route name patterns to match (string or array). When current request
    // matches any pattern, the link renders as the active state and gets
    // aria-current="page" so screen readers announce the user's location.
    'matches' => null,
    // Anchor href.
    'href' => '#',
])

@php
    $patterns = (array) ($matches ?? []);
    $isActive = ! empty($patterns) && request()->routeIs(...$patterns);
@endphp

<a
    href="{{ $href }}"
    @click="adminSidebarOpen = false"
    @if ($isActive) aria-current="page" @endif
    {{ $attributes->class([
        'flex items-center rounded-md px-3 py-2 text-sm font-medium transition',
        'bg-amber-100 text-amber-900' => $isActive,
        'text-gray-700 hover:bg-amber-50 hover:text-amber-900' => ! $isActive,
    ]) }}
>
    {{ $slot }}
</a>
