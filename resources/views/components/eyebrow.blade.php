@props(['color' => 'produce', 'as' => 'p'])

@php
    use App\Support\ProductCategoryStyle;
    $colorClass = ProductCategoryStyle::textClassForStall($color);
@endphp

<{{ $as }} {{ $attributes->class(['eyebrow', $colorClass]) }}>
    {{ $slot }}
</{{ $as }}>
