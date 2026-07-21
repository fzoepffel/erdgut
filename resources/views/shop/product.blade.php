<x-shop-layout :title="$title" nav-active="products" indexable :description="\Illuminate\Support\Str::limit($product->cardDescription() ?: __('shop.meta_description'), 160)">
    @include('products._partials.public-detail', [
        'product' => $product,
        'indexUrl' => route('public.shop.products.index'),
        'indexLabel' => __('shop.nav_products'),
        'productRouteName' => 'public.shop.products.show',
        'producerRouteName' => 'public.shop.producers.show',
        'shopCta' => $shopCta,
    ])
</x-shop-layout>
