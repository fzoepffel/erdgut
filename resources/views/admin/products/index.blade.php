@php
    $hasFilters = filled(trim((string) request('q')))
        || filled(request('category'))
        || request()->filled('published')
        || request()->filled('available');
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Products') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('app.admin.products.weekly.show') }}"
                    class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                >
                    {{ __('admin.weekly_catalog.nav_label') }}
                </a>
                <a
                    href="{{ route('app.admin.products.create') }}"
                    class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                >
                    {{ __('Add product') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12" x-data="{ deleteAction: '', deleteLabel: '' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                    <span>{{ session('status') }}</span>
                    @if (session('flash_edit_url'))
                        <a href="{{ session('flash_edit_url') }}" class="font-semibold underline underline-offset-2 hover:no-underline">
                            {{ __('Edit') }} →
                        </a>
                    @endif
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 space-y-4 text-gray-900">
                    <form method="get" action="{{ route('app.admin.products.index') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="min-w-0 flex-1 sm:min-w-[12rem]">
                            <label for="admin-products-q" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Search') }}</label>
                            <input
                                id="admin-products-q"
                                type="search"
                                name="q"
                                value="{{ old('q', request('q')) }}"
                                maxlength="120"
                                placeholder="{{ __('admin.catalog.search_products_placeholder') }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm"
                            />
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-products-category" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Category') }}</label>
                            <select
                                id="admin-products-category"
                                name="category"
                                class="block w-full sm:w-44 rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm"
                            >
                                <option value="">{{ __('admin.catalog.filter_all_categories') }}</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->value }}" @selected(old('category', request('category')) === $cat->value)>
                                        {{ __('admin.category.'.$cat->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-products-published" class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.catalog.filter_catalog') }}</label>
                            <select
                                id="admin-products-published"
                                name="published"
                                class="block w-full sm:w-40 rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm"
                            >
                                <option value="">{{ __('admin.catalog.filter_any') }}</option>
                                <option value="1" @selected(old('published', request('published')) === '1')>{{ __('admin.catalog.filter_published_live') }}</option>
                                <option value="0" @selected(old('published', request('published')) === '0')>{{ __('admin.catalog.filter_published_draft') }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-products-available" class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.catalog.filter_shop') }}</label>
                            <select
                                id="admin-products-available"
                                name="available"
                                class="block w-full sm:w-40 rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm"
                            >
                                <option value="">{{ __('admin.catalog.filter_any') }}</option>
                                <option value="1" @selected(old('available', request('available')) === '1')>{{ __('Available') }}</option>
                                <option value="0" @selected(old('available', request('available')) === '0')>{{ __('Hidden') }}</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                            >
                                {{ __('admin.catalog.apply_filters') }}
                            </button>
                            @if ($hasFilters)
                                <a
                                    href="{{ route('app.admin.products.index') }}"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                >
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($products->isEmpty())
                        @if ($hasFilters)
                            <p class="text-sm text-gray-600">{{ __('admin.catalog.no_products_match') }}</p>
                        @else
                            <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
                                <p class="text-sm font-medium text-gray-900">{{ __('No products yet.') }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ __('admin.catalog.products_empty_hint') }}</p>
                                <a
                                    href="{{ route('app.admin.products.create') }}"
                                    class="mt-4 inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                                >
                                    {{ __('Add product') }}
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">
                                    {{ __('admin.catalog.products_table_caption') }}
                                </caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th scope="col" class="py-2 pe-2 w-12">{{ __('Image') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Name') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Producer') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Category') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Price') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Shop') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Catalog') }}</th>
                                        <th scope="col" class="py-2 pe-0 text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($products as $product)
                                        <tr>
                                            <td class="py-2 pe-2 align-middle">
                                                @if ($product->imageUrl())
                                                    <img
                                                        src="{{ $product->imageUrl() }}"
                                                        alt=""
                                                        class="h-9 w-9 max-h-9 rounded object-cover border border-gray-100"
                                                        width="36"
                                                        height="36"
                                                    />
                                                @else
                                                    <span class="inline-block h-9 w-9 rounded bg-gray-100" aria-hidden="true"></span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 font-medium text-gray-900">{{ $product->name }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $product->producer?->name ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                @php $catEnum = \App\Enums\ProductCategory::tryFrom($product->category); @endphp
                                                {{ $catEnum ? __('admin.category.'.$catEnum->value) : $product->category }}
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">@eur($product->unit_price)</td>
                                            <td class="py-3 pe-4">
                                                @if ($product->is_available)
                                                    <span class="text-emerald-700">{{ __('Available') }}</span>
                                                @else
                                                    <span class="text-gray-500">{{ __('Hidden') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4">
                                                @if ($product->is_published)
                                                    <span class="text-emerald-700">{{ __('Live') }}</span>
                                                @else
                                                    <span class="text-amber-800">{{ __('Draft') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-0 text-end space-x-2 whitespace-nowrap">
                                                <a href="{{ route('app.admin.products.edit', $product) }}" class="text-emerald-700 hover:text-emerald-900 font-medium">{{ __('Edit') }}</a>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded bg-rose-600 px-2 py-1 text-xs font-semibold text-white hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-600 focus:ring-offset-2"
                                                    x-on:click.prevent="deleteAction = @js(route('app.admin.products.destroy', $product)); deleteLabel = @js($product->name); $dispatch('open-modal', 'confirm-admin-product-delete')"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <x-modal name="confirm-admin-product-delete" focusable>
            <form method="post" x-bind:action="deleteAction" class="p-6" aria-labelledby="admin-delete-product-title">
                @csrf
                @method('delete')

                <h2 id="admin-delete-product-title" class="text-lg font-medium text-gray-900">
                    {{ __('admin.catalog.delete_product_title') }}
                </h2>

                <p class="mt-2 text-sm text-gray-600">
                    {{ __('admin.catalog.delete_product_body') }}
                </p>
                <p class="mt-1 text-sm font-medium text-gray-900">
                    <span class="sr-only">{{ __('Name') }}: </span>
                    <span x-text="deleteLabel"></span>
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <x-danger-button type="submit">
                        {{ __('admin.catalog.confirm_delete') }}
                    </x-danger-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-admin-layout>
