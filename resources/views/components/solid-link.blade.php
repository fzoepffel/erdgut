@props(['href' => '#'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn-solid']) }}>
    {{ $slot }}
</a>
