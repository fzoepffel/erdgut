@php
    $mergeShopQuery = static function (array $patch = []): array {
        $base = array_filter([
            'category' => request('category'),
            'producer' => request('producer'),
            'q' => request('q'),
            'sort' => request('sort'),
        ], static fn ($v) => $v !== null && $v !== '');

        return array_filter(array_merge($base, $patch), static fn ($v) => $v !== null && $v !== '');
    };
    $hasActiveFilters = filled(request('category'))
        || filled(request('producer'))
        || filled(request('q'))
        || (filled(request('sort')) && request('sort') !== 'name');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Shop extras') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-emerald-100 bg-emerald-50/80 px-4 py-3">
                <p class="text-sm font-semibold text-emerald-900">{{ __('Add extras to your weekly box') }}</p>
                <p class="mt-1 text-sm text-emerald-800">
                    {{ __('Browse add-on products. Items are charged against your box value when your box is open for editing.') }}
                </p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm font-semibold text-emerald-800">
                    <a href="{{ route('app.boxes.current') }}" class="underline decoration-emerald-600 hover:text-emerald-950">
                        {{ __('Open current week box') }}
                    </a>
                    <a href="{{ route('app.subscriptions.show') }}" class="underline decoration-emerald-600 hover:text-emerald-950">
                        {{ __('Subscription overview') }}
                    </a>
                </div>
            </div>

            <div class="mt-8 lg:grid lg:grid-cols-[1fr_280px] lg:gap-8">
                <div class="lg:col-span-1">
                    <form method="get" action="{{ route('app.shop.index') }}" class="mb-6 space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                        @if (filled(request('category')))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        @if (filled(request('producer')))
                            <input type="hidden" name="producer" value="{{ request('producer') }}">
                        @endif
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="min-w-0 flex-1">
                                <label for="shop-search-q" class="block text-sm font-medium text-gray-700">{{ __('Search products') }}</label>
                                <input
                                    id="shop-search-q"
                                    type="search"
                                    name="q"
                                    value="{{ request('q') }}"
                                    autocomplete="off"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                    placeholder="{{ __('Search by name…') }}"
                                />
                            </div>
                            <div class="sm:w-52">
                                <label for="shop-sort" class="block text-sm font-medium text-gray-700">{{ __('Sort by') }}</label>
                                <select
                                    id="shop-sort"
                                    name="sort"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                >
                                    <option value="name" @selected(request('sort', 'name') === 'name')>{{ __('Name A–Z') }}</option>
                                    <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ __('Price: low to high') }}</option>
                                    <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ __('Price: high to low') }}</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <x-primary-button type="submit" class="justify-center">{{ __('Apply') }}</x-primary-button>
                                @if ($hasActiveFilters)
                                    <a
                                        href="{{ route('app.shop.index') }}"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                    >
                                        {{ __('Clear all') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>

                    <div class="mb-6" x-data="{ filtersOpen: false }">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 text-left text-sm font-semibold text-gray-900 shadow-sm lg:hidden"
                            @click="filtersOpen = !filtersOpen"
                            :aria-expanded="filtersOpen"
                            aria-controls="shop-filter-chips"
                        >
                            {{ __('Category & producer filters') }}
                            <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform" :class="{ 'rotate-180': filtersOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            id="shop-filter-chips"
                            class="mt-3 hidden space-y-4 lg:mt-0 lg:block"
                            :class="{ '!block': filtersOpen }"
                        >
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $baseParams = $mergeShopQuery(['category' => null]);
                                @endphp
                                <a
                                    href="{{ route('app.shop.index', $baseParams) }}"
                                    class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ ! request('category') ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                >
                                    {{ __('All categories') }}
                                </a>
                                @foreach ($categories as $category)
                                    <a
                                        href="{{ route('app.shop.index', $mergeShopQuery(['category' => $category->value])) }}"
                                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ request('category') === $category->value ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                    >
                                        {{ __(\Illuminate\Support\Str::title($category->value)) }}
                                    </a>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $producerBaseParams = $mergeShopQuery(['producer' => null]);
                                @endphp
                                <a
                                    href="{{ route('app.shop.index', $producerBaseParams) }}"
                                    class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ ! request('producer') ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                >
                                    {{ __('All producers') }}
                                </a>
                                @foreach ($producers as $producer)
                                    <a
                                        href="{{ route('app.shop.index', $mergeShopQuery(['producer' => $producer->id])) }}"
                                        class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-colors {{ request('producer') == $producer->id ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                                    >
                                        {{ $producer->name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if ($products->isEmpty())
                        <x-empty-state
                            :title="__('No products available')"
                            :description="__('No products match your filters. Try adjusting or clearing filters and search.')"
                            variant="card"
                        >
                            @if ($hasActiveFilters)
                                <x-slot name="action">
                                    <a
                                        href="{{ route('app.shop.index') }}"
                                        class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                                    >
                                        {{ __('Clear all filters') }}
                                    </a>
                                </x-slot>
                            @endif
                        </x-empty-state>
                    @else
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach ($products as $product)
                                <x-shop.product-card :product="$product" :currentBox="$currentBox ?? null" />
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
                @if ($currentBox)
                    <aside class="mt-8 lg:col-span-1 lg:mt-0" x-data="{ open: false }">
                        <div class="lg:hidden">
                            <button
                                type="button"
                                @click="open = !open"
                                class="flex w-full items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm"
                            >
                                <span class="text-sm font-semibold text-gray-900">{{ __('View my box') }} ({{ $currentBox->items->sum(fn ($i) => (int) $i->quantity) }} {{ __('items') }})</span>
                                <svg class="h-5 w-5 text-gray-500 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" x-cloak class="mt-2">
                                <x-shop.box-summary :box="$currentBox" :budgetRemainder="$budgetRemainder" />
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <div class="sticky top-4">
                                <x-shop.box-summary :box="$currentBox" :budgetRemainder="$budgetRemainder" />
                            </div>
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
