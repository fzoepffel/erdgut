<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Shop extras') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 space-y-4">
                <div class="flex flex-wrap gap-2">
                    @php
                        $baseParams = array_filter(['producer' => request('producer')]);
                    @endphp
                    <a
                        href="{{ route('app.shop.index', $baseParams) }}"
                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ ! request('category') ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    >
                        {{ __('All categories') }}
                    </a>
                    @foreach ($categories as $category)
                        <a
                            href="{{ route('app.shop.index', array_merge($baseParams, ['category' => $category->value])) }}"
                            class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ request('category') === $category->value ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                        >
                            {{ __(ucfirst($category->value)) }}
                        </a>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    @php
                        $producerBaseParams = array_filter(['category' => request('category')]);
                    @endphp
                    <a
                        href="{{ route('app.shop.index', $producerBaseParams) }}"
                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ ! request('producer') ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    >
                        {{ __('All producers') }}
                    </a>
                    @foreach ($producers as $producer)
                        <a
                            href="{{ route('app.shop.index', array_merge($producerBaseParams, ['producer' => $producer->id])) }}"
                            class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ request('producer') == $producer->id ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                        >
                            {{ $producer->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if ($products->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
                    <h2 class="text-lg font-semibold text-gray-900">No products available</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        No products match your filters. Try adjusting or clearing the filters.
                    </p>
                </div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $product)
                        <x-shop.product-card :product="$product" />
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
