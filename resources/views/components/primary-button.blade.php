@props(['size' => 'default'])

@php
    $base = $size === 'sm' ? 'btn-solid-sm' : 'btn-solid';
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => $base.' disabled:cursor-not-allowed disabled:opacity-60']) }}>
    {{ $slot }}
</button>
