<x-shop-layout :title="$title">
    @include('marketing._partials.producer-detail', [
        'producer' => $producer,
        'products' => $products,
        'indexUrl' => route('public.shop.index'),
        'indexLabel' => __('shop.eyebrow'),
        'productRouteName' => 'public.shop.products.show',
    ])
</x-shop-layout>
