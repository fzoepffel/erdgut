<x-app-layout>
    @include('products._partials.detail', [
        'product' => $product,
        'listRouteName' => 'app.products.index',
        'detailRouteName' => 'app.products.show',
    ])
</x-app-layout>
