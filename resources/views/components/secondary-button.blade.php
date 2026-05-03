@props(['size' => 'default'])

@php
    $base = $size === 'sm' ? 'btn-ghost-sm' : 'btn-ghost';
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => $base.' disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
