@props([
    // Visible group label.
    'label',
    // Route name patterns (string or array) covered by the group's children.
    // When the current request matches, the group starts expanded and the
    // header renders in the active tint so the location stays visible even
    // while collapsed.
    'matches' => [],
])

@php
    $patterns = (array) $matches;
    $isActive = ! empty($patterns) && request()->routeIs(...$patterns);
@endphp

<div x-data="{ open: @js($isActive) }" class="mt-1">
    <button
        type="button"
        @click="open = ! open"
        :aria-expanded="open ? 'true' : 'false'"
        {{ $attributes->class([
            'flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-semibold transition',
            'text-amber-900' => $isActive,
            'text-gray-600 hover:bg-amber-50 hover:text-amber-900' => ! $isActive,
        ]) }}
    >
        <span>{{ $label }}</span>
        <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-90'"
             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
    </button>
    <div x-show="open" x-cloak class="mt-1 space-y-1 border-l border-amber-100 pl-3">
        {{ $slot }}
    </div>
</div>
