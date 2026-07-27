@php
    $hasFilters = filled(trim((string) request('q')))
        || filled(request('category'))
        || request()->filled('published')
        || request()->filled('available')
        || request()->filled('group_id');
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Products') }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('app.admin.subcategories.index') }}"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                >
                    {{ __('admin.subcategories.title') }}
                </a>
                <a
                    href="{{ route('app.admin.inventory.index') }}"
                    class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                >
                    {{ __('admin.weekly_catalog.nav_label') }}
                </a>
                <a
                    href="{{ route('app.admin.products.export') }}"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                >
                    {{ __('admin.products.csv.download') }}
                </a>
                <form
                    method="POST"
                    action="{{ route('app.admin.products.import') }}"
                    enctype="multipart/form-data"
                    x-data="{ loading: false }"
                    class="inline-flex"
                >
                    @csrf
                    <label
                        class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus-within:ring-2 focus-within:ring-emerald-600 focus-within:ring-offset-2"
                        :class="loading && 'pointer-events-none opacity-60'"
                    >
                        <span x-show="!loading">{{ __('admin.products.csv.import') }}</span>
                        <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            {{ __('admin.products.csv.import_loading') }}
                        </span>
                        <input
                            type="file"
                            name="file"
                            accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                            class="sr-only"
                            :disabled="loading"
                            @change="loading = true; $el.form.submit()"
                        >
                    </label>
                    <div
                        x-show="loading"
                        x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40"
                        role="status"
                        aria-live="polite"
                    >
                        <div class="flex items-center gap-3 rounded-lg bg-white px-5 py-4 text-sm font-semibold text-gray-800 shadow-lg">
                            <svg class="h-5 w-5 animate-spin text-emerald-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            {{ __('admin.products.csv.import_loading') }}
                        </div>
                    </div>
                </form>
                <a
                    href="{{ route('app.admin.products.create') }}"
                    class="btn-solid-sm"
                >
                    {{ __('Add product') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12" x-data="{ deleteAction: '', deleteLabel: '', groupSelection: [], groupModalOpen: false, groupChoice: '' }">
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
                    {{-- Bulk grouping: pick duplicate offerings via the row
                         checkboxes, bundle them into one storefront card. --}}
                    <div x-show="groupSelection.length > 0" x-cloak class="flex items-center gap-3 rounded-md border border-violet-200 bg-violet-50 px-3 py-2">
                        <span class="text-sm text-violet-900" x-text="groupSelection.length + ' ' + @js(__('admin.products.group_selected_suffix'))"></span>
                        <button type="button" @click="groupModalOpen = true"
                                class="rounded-md bg-violet-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-violet-800"
                                data-testid="admin-products-group-assign-open">
                            {{ __('admin.products.group_assign_button') }}
                        </button>
                        <button type="button" @click="groupSelection = []" class="text-xs text-violet-700 underline">{{ __('admin.products.group_clear_selection') }}</button>
                    </div>

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
                        <div class="w-full sm:w-auto">
                            <label for="admin-products-group" class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.products.group_column') }}</label>
                            <select
                                id="admin-products-group"
                                name="group_id"
                                class="block w-full sm:w-44 rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm"
                            >
                                <option value="">{{ __('admin.catalog.filter_any') }}</option>
                                <option value="none" @selected(old('group_id', request('group_id')) === 'none')>{{ __('admin.products.group_none') }}</option>
                                @foreach ($groups as $group)
                                    <option value="{{ $group->id }}" @selected(old('group_id', request('group_id')) === (string) $group->id)>
                                        {{ $group->localizedName() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <x-primary-button size="sm">
                                {{ __('admin.catalog.apply_filters') }}
                            </x-primary-button>
                            @if ($hasFilters)
                                <a
                                    href="{{ route('app.admin.products.index') }}"
                                    class="btn-ghost-sm"
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
                                    class="mt-4 btn-solid-sm"
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
                                        <th scope="col" class="py-2 pe-2 w-8"><span class="sr-only">{{ __('admin.products.group_assign_button') }}</span></th>
                                        <th scope="col" class="py-2 pe-2 w-12">{{ __('Image') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Name') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Producer') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.products.group_column') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Category') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Price') }}</th>
                                        {{-- F6 (UX notes): expose margin (or "—" when buying
                                             price is missing) so operators can spot pricing
                                             issues from the list. Title-attribute reveals the
                                             buying price for the curious. --}}
                                        <th scope="col" class="py-2 pe-4" data-testid="admin-products-col-margin">{{ __('Margin') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Shop') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Catalog') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.catalog.col_last_updated') }}</th>
                                        <th scope="col" class="py-2 pe-0 text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($products as $product)
                                        <tr>
                                            <td class="py-2 pe-2 align-middle">
                                                <input type="checkbox" value="{{ $product->id }}" x-model="groupSelection"
                                                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600"
                                                       aria-label="{{ __('admin.products.group_assign_button') }}"
                                                       data-testid="admin-product-group-check-{{ $product->id }}">
                                            </td>
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
                                                @if ($product->group !== null)
                                                    <span class="rounded bg-violet-50 px-1.5 py-0.5 text-xs font-medium text-violet-800" data-testid="admin-product-group-badge-{{ $product->id }}">{{ $product->group->localizedName() }}</span>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                @php $catEnum = \App\Enums\ProductCategory::tryFrom($product->category); @endphp
                                                {{ $catEnum ? __('admin.category.'.$catEnum->value) : $product->category }}
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $product->displayPrice() }}</td>
                                            @php
                                                $marginPct = $product->grossMarginPercent();
                                                $marginEur = $product->grossMargin();
                                                $buyingPriceEur = $product->buying_price_cents !== null
                                                    ? round((int) $product->buying_price_cents / 100, 2)
                                                    : null;
                                            @endphp
                                            <td class="py-3 pe-4 text-gray-600 tabular-nums" title="{{ $buyingPriceEur !== null ? __('Cost: EUR :amount', ['amount' => number_format($buyingPriceEur, 2, ',', '.')]) : '' }}">
                                                @if ($marginPct === null)
                                                    <span class="text-gray-400">—</span>
                                                @else
                                                    <span @class([
                                                        'text-rose-700 font-semibold' => $marginPct < 20,
                                                        'text-amber-700' => $marginPct >= 20 && $marginPct < 30,
                                                        'text-gray-700' => $marginPct >= 30,
                                                    ])>
                                                        {{ number_format($marginPct, 1, ',', '.') }}&nbsp;%
                                                    </span>
                                                    <span class="block text-xs text-gray-500">@eur($marginEur)</span>
                                                @endif
                                            </td>
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
                                                @if ($product->is_seasonal)
                                                    <span class="mt-1 block w-fit rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-800">
                                                        {{ $product->seasonLabel() ? __('product.season_badge_label', ['range' => $product->seasonLabel()]) : __('product.season_badge') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-xs text-gray-500 tabular-nums" title="{{ $product->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}">
                                                {{ $product->updated_at?->diffForHumans() ?? '—' }}
                                            </td>
                                            <td class="py-3 pe-0 text-end space-x-4 whitespace-nowrap">
                                                <a href="{{ route('app.admin.products.edit', $product) }}" class="text-emerald-700 hover:text-emerald-900 font-medium">{{ __('Edit') }}</a>
                                                <a href="{{ route('app.admin.products.price-history', $product) }}" class="font-medium text-gray-500 hover:text-gray-700" data-testid="admin-product-price-history-{{ $product->id }}">{{ __('admin.price_history.link') }}</a>
                                                {{-- E5 (UX notes): destructive action is now the
                                                     same visual register as Edit (text link, red).
                                                     The confirm-modal still gates the actual
                                                     delete. --}}
                                                <button
                                                    type="button"
                                                    class="text-sm font-medium text-rose-700 underline-offset-4 hover:underline"
                                                    data-testid="admin-product-delete-trigger"
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
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-gray-500" data-testid="admin-products-row-count">
                                {{ __('admin.catalog.pagination_count', [
                                    'from' => $products->firstItem() ?? 0,
                                    'to' => $products->lastItem() ?? 0,
                                    'total' => $products->total(),
                                ]) }}
                            </p>
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bulk group-assign modal --}}
        <div x-show="groupModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-gray-900/40" @click="groupModalOpen = false"></div>
            <form method="POST" action="{{ route('app.admin.products.group-assign') }}"
                  class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl space-y-4"
                  data-testid="admin-products-group-assign-form">
                @csrf
                <h2 class="text-lg font-medium text-gray-900">{{ __('admin.products.group_assign_modal_title') }}</h2>
                <template x-for="pid in groupSelection" :key="pid">
                    <input type="hidden" name="product_ids[]" :value="pid">
                </template>
                <div>
                    <label for="bulk-group-id" class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.products.group_select_label') }}</label>
                    <select id="bulk-group-id" name="group_id" x-model="groupChoice" required
                            class="block w-full rounded-md border-gray-300 focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="" disabled>{{ __('admin.products.group_select_label') }}</option>
                        @foreach ($groups as $groupOption)
                            <option value="{{ $groupOption->id }}">{{ $groupOption->localizedName() }}</option>
                        @endforeach
                        <option value="new">{{ __('admin.products.group_create_option') }}</option>
                    </select>
                </div>
                <div x-show="groupChoice === 'new'" x-cloak class="space-y-2">
                    @foreach (['de', 'en', 'fr'] as $groupLocale)
                        <div>
                            <label for="bulk-group-name-{{ $groupLocale }}" class="block text-xs font-medium text-gray-600 mb-1">{{ __('admin.products.group_new_name_label') }} ({{ strtoupper($groupLocale) }})</label>
                            <input id="bulk-group-name-{{ $groupLocale }}" type="text" name="new_group_name[{{ $groupLocale }}]" maxlength="120"
                                   class="block w-full rounded-md border-gray-300 focus:border-emerald-600 focus:ring-emerald-600"
                                   data-testid="admin-products-group-name-{{ $groupLocale }}">
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="groupModalOpen = false" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    <button type="submit" class="rounded-md bg-violet-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-violet-800" data-testid="admin-products-group-assign-submit">
                        {{ __('admin.products.group_assign_button') }}
                    </button>
                </div>
            </form>
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
