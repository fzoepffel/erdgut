<x-admin-layout>
    <x-slot name="breadcrumbs">
        <x-admin.breadcrumbs :items="[
            ['label' => __('admin.shell.overview'), 'href' => route('app.admin.dashboard')],
            ['label' => __('Products'), 'href' => route('app.admin.products.index')],
            ['label' => $product->name],
        ]" />
    </x-slot>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit product') }}: {{ $product->name }}
            </h1>
            <div class="flex items-center gap-4">
                <a href="{{ route('app.admin.products.variants', $product->id) }}"
                   class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                    {{ __('admin.product_variants.nav_link') }} &rarr;
                </a>
                <a href="{{ route('app.admin.products.group-prices', $product->id) }}"
                   class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                    {{ __('admin.product_group_prices.nav_link') }} &rarr;
                </a>
                <a href="{{ route('app.admin.products.pricing', $product->id) }}"
                   class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                    {{ __('admin.product_pricing.nav_link') }} &rarr;
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @include('admin.products._form', [
                        'product' => $product,
                        'producers' => $producers,
                        'categories' => $categories,
                        'action' => route('app.admin.products.update', $product),
                        'method' => 'put',
                    ])
                </div>
            </div>

            <p class="text-xs text-gray-500">
                {{ __('admin.product_schedules.moved_to_weekly_catalog_hint') }}
                <a href="{{ route('app.admin.inventory.index') }}" class="font-medium text-emerald-700 hover:underline">{{ __('admin.weekly_catalog.nav_label') }}</a>.
            </p>
        </div>
    </div>
</x-admin-layout>
