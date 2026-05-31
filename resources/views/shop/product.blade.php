<x-shop-layout :title="$title">
    @include('products._partials.public-detail', [
        'product' => $product,
        'indexUrl' => route('public.shop.index'),
        'indexLabel' => __('shop.eyebrow'),
        'productRouteName' => 'public.shop.products.show',
        'producerRouteName' => 'public.shop.producers.show',
    ])
</x-shop-layout>
