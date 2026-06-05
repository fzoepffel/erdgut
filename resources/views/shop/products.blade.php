<x-shop-layout :title="$title" nav-active="products">
    {{-- Isolated product catalogue: same body as the marketing /products page,
         but every link points back into the shop chrome. --}}
    @include('products._partials.public-index', [
        'productsIndexRoute' => 'public.shop.products.index',
        'productShowRoute' => 'public.shop.products.show',
    ])
</x-shop-layout>
