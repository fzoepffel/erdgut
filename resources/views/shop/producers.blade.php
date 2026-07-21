<x-shop-layout :title="$title" nav-active="producers" indexable :description="__('shop.producers_meta_description')">
    {{-- Isolated producer directory: same body as the marketing /producers
         page, but isolated — no auth/dashboard/contact links escape the shop. --}}
    @include('marketing._partials.producers-index', [
        'producersIndexRoute' => 'public.shop.producers.index',
        'producerShowRoute' => 'public.shop.producers.show',
        'productsIndexRoute' => 'public.shop.products.index',
        'isolated' => true,
    ])
</x-shop-layout>
