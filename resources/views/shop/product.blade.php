<x-shop-layout :title="$title" nav-active="products">
    @include('products._partials.public-detail', [
        'product' => $product,
        'indexUrl' => route('public.shop.products.index'),
        'indexLabel' => __('shop.nav_products'),
        'productRouteName' => 'public.shop.products.show',
        'producerRouteName' => 'public.shop.producers.show',
        'shopCta' => $shopCta,
    ])
</x-shop-layout>
