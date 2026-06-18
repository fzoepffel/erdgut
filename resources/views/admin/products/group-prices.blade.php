<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.product_group_prices.title', ['name' => $product->name]) }}
            </h1>
            <a href="{{ route('app.admin.products.edit', $product->id) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('admin.product_group_prices.back_to_product') }}
            </a>
        </div>
    </x-slot>

    @php($unitSuffix = ! empty($displayUnit) ? ' (€ / '.$displayUnit.')' : '')

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if (session('status') === 'group-price-saved')
                <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ __('admin.product_group_prices.saved') }}</div>
            @elseif (session('status') === 'group-price-cleared')
                <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700 ring-1 ring-gray-200">{{ __('admin.product_group_prices.cleared') }}</div>
            @endif

            <p class="text-sm text-gray-600">{{ __('admin.product_group_prices.intro') }}</p>

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="divide-y divide-gray-100">
                    {{-- Private (default) price — the product's own unit price. --}}
                    <div class="flex items-center justify-between px-4 py-3 text-sm sm:px-6">
                        <span class="text-gray-700">{{ __('admin.product_group_prices.private_label') }}</span>
                        <span class="font-mono tabular-nums text-gray-900">{{ \App\Support\Money::formatEur($privatePrice) }}</span>
                    </div>

                    {{-- Business price — set/clear a customer-group rule. --}}
                    <form method="POST" action="{{ route('app.admin.products.group-prices.store', $product->id) }}" class="space-y-3 px-4 py-4 sm:px-6">
                        @csrf
                        <div class="flex flex-wrap items-end gap-4">
                            <label class="block">
                                <span class="text-xs font-medium text-gray-500">{{ __('admin.product_group_prices.business_label') }}{{ $unitSuffix }}</span>
                                <input type="number" name="unit_price" step="0.01" min="0.01"
                                       value="{{ $businessDisplayPrice !== null ? number_format($businessDisplayPrice, 2, '.', '') : '' }}"
                                       placeholder="{{ number_format($privatePrice, 2, '.', '') }}"
                                       class="mt-1 block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <label class="block flex-1 min-w-[8rem]">
                                <span class="text-xs font-medium text-gray-500">{{ __('admin.product_group_prices.note_label') }}</span>
                                <input type="text" name="label" maxlength="120" value="{{ $businessRule?->label }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('admin.product_group_prices.save') }}</button>
                        </div>
                        <p class="text-[0.7rem] text-gray-400">{{ __('admin.product_group_prices.business_hint') }}</p>
                    </form>

                    @if ($businessRule !== null)
                        <form method="POST" action="{{ route('app.admin.products.group-prices.destroy', $product->id) }}" class="px-4 py-3 sm:px-6">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">{{ __('admin.product_group_prices.remove') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
