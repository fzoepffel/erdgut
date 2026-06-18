<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.product_variants.title', ['name' => $product->name]) }}
            </h1>
            <a href="{{ route('app.admin.products.edit', $product->id) }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('admin.product_variants.back_to_product') }}
            </a>
        </div>
    </x-slot>

    @php
        $unitSuffix = ! empty($displayUnit) ? ' (€ / '.$displayUnit.')' : '';
        $displayPrice = fn ($variant) => $variant->unit_price !== null
            ? number_format((float) $variant->unit_price * $displayFactor, 2, '.', '')
            : '';
    @endphp

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if (session('status') === 'variant-saved')
                <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200">{{ __('admin.product_variants.saved') }}</div>
            @elseif (session('status') === 'variant-deleted')
                <div class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700 ring-1 ring-gray-200">{{ __('admin.product_variants.deleted') }}</div>
            @endif

            <p class="text-sm text-gray-600">
                {{ __('admin.product_variants.intro', ['price' => \App\Support\Money::formatEur($defaultPrice)]) }}
            </p>

            {{-- Existing grades --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-3 sm:px-6">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_variants.existing_heading') }}</h2>
                </div>

                <div class="divide-y divide-gray-100">
                    <div class="flex items-center justify-between px-4 py-3 text-sm text-gray-500 sm:px-6">
                        <span>{{ __('admin.product_variants.standard_row') }}</span>
                        <span class="font-mono tabular-nums">{{ \App\Support\Money::formatEur($defaultPrice) }}</span>
                    </div>

                    @forelse ($variants as $variant)
                        <form method="POST" action="{{ route('app.admin.products.variants.update', [$product->id, $variant->id]) }}"
                              class="space-y-3 px-4 py-4 sm:px-6">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.name_label') }} (DE)</span>
                                    <input type="text" name="name[de]" maxlength="120" value="{{ $variant->name['de'] ?? '' }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.name_label') }} (EN)</span>
                                    <input type="text" name="name[en]" maxlength="120" value="{{ $variant->name['en'] ?? '' }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.name_label') }} (FR)</span>
                                    <input type="text" name="name[fr]" maxlength="120" value="{{ $variant->name['fr'] ?? '' }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                </label>
                            </div>
                            <div class="flex flex-wrap items-end gap-4">
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.price_label') }}{{ $unitSuffix }}</span>
                                    <input type="number" name="unit_price" step="0.01" min="0.01" value="{{ $displayPrice($variant) }}"
                                           placeholder="{{ number_format($defaultPrice, 2, '.', '') }}"
                                           class="mt-1 block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.position_label') }}</span>
                                    <input type="number" name="position" min="0" max="9999" value="{{ $variant->position }}"
                                           class="mt-1 block w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                </label>
                                <label class="flex items-center gap-2 pb-2">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($variant->is_active)
                                           class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                                    <span class="text-sm text-gray-700">{{ __('admin.product_variants.active_label') }}</span>
                                </label>
                                <div class="ml-auto flex items-center gap-3 pb-1">
                                    <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('admin.product_variants.save') }}</button>
                                </div>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('app.admin.products.variants.destroy', [$product->id, $variant->id]) }}"
                              class="px-4 pb-4 sm:px-6" onsubmit="return confirm('{{ __('admin.product_variants.delete_confirm') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">{{ __('admin.product_variants.delete') }}</button>
                        </form>
                    @empty
                        <p class="px-4 py-4 text-sm text-gray-500 sm:px-6">{{ __('admin.product_variants.none_yet') }}</p>
                    @endforelse
                </div>
            </div>

            {{-- Add a grade --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-3 sm:px-6">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_variants.add_heading') }}</h2>
                </div>
                <form method="POST" action="{{ route('app.admin.products.variants.store', $product->id) }}" class="space-y-3 px-4 py-4 sm:px-6">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.name_label') }} (DE)</span>
                            <input type="text" name="name[de]" maxlength="120"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                        <label class="block">
                            <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.name_label') }} (EN)</span>
                            <input type="text" name="name[en]" maxlength="120"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                        <label class="block">
                            <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.name_label') }} (FR)</span>
                            <input type="text" name="name[fr]" maxlength="120"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <label class="block">
                            <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.price_label') }}{{ $unitSuffix }}</span>
                            <input type="number" name="unit_price" step="0.01" min="0.01"
                                   placeholder="{{ number_format($defaultPrice, 2, '.', '') }}"
                                   class="mt-1 block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <span class="mt-1 block text-[0.7rem] text-gray-400">{{ __('admin.product_variants.price_hint') }}</span>
                        </label>
                        <label class="block">
                            <span class="text-xs font-medium text-gray-500">{{ __('admin.product_variants.position_label') }}</span>
                            <input type="number" name="position" min="0" max="9999" value="0"
                                   class="mt-1 block w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                        <button type="submit" class="ml-auto rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('admin.product_variants.add') }}</button>
                    </div>
                </form>
            </div>

            <p class="text-xs text-gray-500">
                {{ __('admin.product_variants.inventory_hint') }}
                <a href="{{ route('app.admin.inventory.index') }}" class="font-medium text-emerald-700 hover:underline">{{ __('admin.weekly_catalog.nav_label') }}</a>.
            </p>
        </div>
    </div>
</x-admin-layout>
