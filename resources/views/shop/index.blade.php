<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Shop extras') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('app.shop.index') }}" class="mb-6 flex flex-wrap gap-4">
                <div>
                    <label for="category" class="sr-only">Category</label>
                    <select
                        id="category"
                        name="category"
                        class="rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option value="">All categories</option>
                        <option value="vegetables" {{ request('category') === 'vegetables' ? 'selected' : '' }}>Vegetables</option>
                        <option value="fruit" {{ request('category') === 'fruit' ? 'selected' : '' }}>Fruit</option>
                        <option value="dairy" {{ request('category') === 'dairy' ? 'selected' : '' }}>Dairy</option>
                        <option value="bakery" {{ request('category') === 'bakery' ? 'selected' : '' }}>Bakery</option>
                    </select>
                </div>
                <div>
                    <label for="producer" class="sr-only">Producer</label>
                    <select
                        id="producer"
                        name="producer"
                        class="rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    >
                        <option value="">All producers</option>
                        @foreach ($producers as $producer)
                            <option value="{{ $producer->id }}" {{ request('producer') == $producer->id ? 'selected' : '' }}>
                                {{ $producer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                    Filter
                </button>
            </form>

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
