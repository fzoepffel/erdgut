<x-shop-layout :title="$title" nav-active="producers" indexable :description="\Illuminate\Support\Str::limit($producer->description ?: __('shop.producers_meta_description'), 160)">
    @include('marketing._partials.producer-detail', [
        'producer' => $producer,
        'products' => $products,
        'indexUrl' => route('public.shop.producers.index'),
        'indexLabel' => __('shop.nav_producers'),
        'productRouteName' => 'public.shop.products.show',
        'shopCtas' => $shopCtas,
    ])
</x-shop-layout>
