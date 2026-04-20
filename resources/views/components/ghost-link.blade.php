@props(['href' => '#'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn-ghost']) }}>
    {{ $slot }}
</a>
