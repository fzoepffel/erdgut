<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_list') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.create_heading') }}</h1>
        </div>
    </x-slot>

    @php
        // Allowed units for a custom (off-catalog) line — the catalog's own
        // display units, so custom-line data stays in the same vocabulary.
        $customUnitOptions = [
            ['v' => 'piece', 'l' => __('units.piece')],
            ['v' => 'bunch', 'l' => __('units.bunch')],
            ['v' => 'kg', 'l' => 'kg'],
            ['v' => 'g', 'l' => 'g'],
            ['v' => 'l', 'l' => 'L'],
            ['v' => 'ml', 'l' => 'ml'],
        ];
    @endphp

    <div class="py-8 sm:py-12"
         x-data="adminOrderForm({
             availableProductsUrl: @js($availableProductsUrl),
             quoteUrl: @js($quoteUrl),
             pickupMode: '{{ old('fulfillment_mode', 'pickup') }}',
             customerType: '{{ old('customer_type', 'private') }}',
             preselectedDate: '{{ old('expected_delivery_date', '') }}',
             preselectedItems: @js(collect(old('items', []))->map(fn ($i) => [
                 'custom' => empty($i['product_id']) && trim((string) ($i['name'] ?? '')) !== '',
                 'grade_key' => (string) ($i['product_id'] ?? '').(isset($i['variant_id']) && $i['variant_id'] !== '' ? ':'.$i['variant_id'] : ''),
                 'product_id' => (string) ($i['product_id'] ?? ''),
                 'variant_id' => isset($i['variant_id']) && $i['variant_id'] !== '' ? (string) $i['variant_id'] : '',
                 'name' => (string) ($i['name'] ?? ''),
                 'unit' => (string) ($i['unit'] ?? ''),
                 'quantity' => (string) ($i['quantity'] ?? ''),
                 'line_total_eur' => isset($i['line_total_eur']) ? (string) $i['line_total_eur'] : '',
                 'vat_rate_percent' => (string) ($i['vat_rate_percent'] ?? '7'),
                 'lineTouched' => isset($i['line_total_eur']),
                 'vatTouched' => isset($i['vat_rate_percent']),
             ])->values()->all()),
             preselectedDeliveryFee: @js(old('delivery_fee_eur')),
             preselectedDeliveryVatRate: @js(old('delivery_fee_vat_rate_percent', '7')),
             vatLineTemplate: @js(__('shop.summary_vat_rate_line')),
             priceHintLabels: @js([
                 'reasons' => [
                     'base' => __('shop.admin.create_price_reason_base'),
                     'customer_group' => __('shop.admin.create_price_reason_customer_group'),
                     'period_override' => __('shop.admin.create_price_reason_period_override'),
                     'quantity_tier' => __('shop.admin.create_price_reason_quantity_tier'),
                     'margin_floor' => __('shop.admin.create_price_reason_margin_floor'),
                 ],
                 'tiers' => [
                     'private' => __('shop.admin.customer_type_private'),
                     'business' => __('shop.admin.customer_type_business'),
                 ],
                 'compare_template' => __('shop.admin.create_price_compare'),
                 'used_template' => __('shop.admin.create_price_used'),
             ]),
         })">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('error'))
                <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</p>
            @endif

            <form method="POST" action="{{ route('app.admin.shop-orders.store') }}">
                @csrf
                <input type="hidden" name="payment_method" value="in_person">

                {{-- ── Step 1: Date ─────────────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">1. {{ __('shop.form_delivery_date') }}</h2>
                    </div>
                    <div class="p-6">
                        <input type="date" id="expected_delivery_date" name="expected_delivery_date"
                            x-model="selectedDate"
                            @change="onDateChange()"
                            class="block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            required>
                        <x-input-error :messages="$errors->get('expected_delivery_date')" class="mt-1" />
                        <p class="mt-2 text-xs text-gray-500" x-show="selectedDate && !loading && availableProducts.length === 0" x-cloak>
                            {{ __('shop.admin.no_products_on_date') }}
                        </p>
                    </div>
                </div>

                {{-- ── Step 2: Customer type (controls which tier price is loaded) ── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">2. {{ __('shop.admin.customer_type_label') }}</h2>
                    </div>
                    <div class="space-y-3 p-6">
                        <p class="text-sm text-gray-600">{{ __('shop.admin.create_customer_type_hint') }}</p>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="customer_type" value="private"
                                    x-model="customerType"
                                    @change="onCustomerTypeChange()"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                    {{ old('customer_type', 'private') === 'private' ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ __('shop.admin.customer_type_private') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="customer_type" value="business"
                                    x-model="customerType"
                                    @change="onCustomerTypeChange()"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                    {{ old('customer_type') === 'business' ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ __('shop.admin.customer_type_business') }}</span>
                            </label>
                        </div>
                        {{-- B2B channel hint: verified restaurant accounts have their own
                             invoice + recurring flow — point the admin there. --}}
                        <p class="text-xs text-gray-500" x-show="customerType === 'business'" x-cloak>
                            {{ __('shop.admin.create_restaurant_hint') }}
                            <a href="{{ route('app.admin.restaurant-orders.index') }}" class="text-indigo-600 underline">{{ __('restaurant.admin_orders_title') }}</a>
                        </p>
                        <x-input-error :messages="$errors->get('customer_type')" class="mt-1" />
                    </div>
                </div>

                {{-- ── Step 3: Products + per-line pricing ──────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">3. {{ __('shop.admin.create_items_heading') }}</h2>
                        <span x-show="loading" class="text-xs text-gray-400">{{ __('Loading') }}…</span>
                    </div>
                    <div class="p-6 space-y-3">
                        <x-input-error :messages="$errors->get('items')" class="mb-2" />

                        <template x-if="availableProducts.length > 0">
                            <div class="space-y-3">
                                <div class="hidden sm:grid sm:grid-cols-12 sm:gap-3 sm:px-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <span class="sm:col-span-5">{{ __('admin.select_product') }}</span>
                                    <span class="sm:col-span-2">{{ __('admin.quantity') }}</span>
                                    <span class="sm:col-span-2">{{ __('shop.admin.create_line_total_label') }}</span>
                                    <span class="sm:col-span-2">{{ __('shop.admin.create_line_vat_label') }}</span>
                                    <span class="sm:col-span-1"></span>
                                </div>

                                <template x-for="(item, i) in items" :key="i">
                                    <div class="grid grid-cols-1 gap-3 rounded-md border border-gray-100 p-3 sm:grid-cols-12 sm:items-start sm:border-0 sm:p-0">
                                        <div class="sm:col-span-5">
                                            <label class="mb-1 block text-xs text-gray-500 sm:sr-only">{{ __('admin.select_product') }}</label>
                                            {{-- Catalog line: pick a product/grade. --}}
                                            <template x-if="!item.custom">
                                                <select x-model="item.grade_key"
                                                    @change="onLineProductChange(item)"
                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    required>
                                                    <option value="">{{ __('admin.select_product') }}</option>
                                                    <template x-for="p in availableProducts" :key="p.id">
                                                        <option :value="String(p.id)"
                                                                :selected="String(p.id) === String(item.grade_key)"
                                                                x-text="p.name + (p.producer ? ' (' + p.producer + ')' : '') + ' — ' + p.price + ' / ' + p.display_unit"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            {{-- Custom (off-catalog) line: free-text name. --}}
                                            <template x-if="item.custom">
                                                <input type="text"
                                                    :name="'items[' + i + '][name]'"
                                                    x-model="item.name"
                                                    maxlength="160"
                                                    placeholder="{{ __('shop.admin.create_custom_name_placeholder') }}"
                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    required>
                                            </template>
                                            {{-- Hidden inputs split the grade key back into the
                                                 (product_id, variant_id) the backend expects. Empty
                                                 product_id on a custom line flags it off-catalog. --}}
                                            <input type="hidden" :name="'items[' + i + '][product_id]'" :value="item.product_id">
                                            <input type="hidden" :name="'items[' + i + '][variant_id]'" :value="item.variant_id">
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-gray-500 sm:sr-only">{{ __('admin.quantity') }}</label>
                                            <div class="flex items-center gap-1.5">
                                                <input type="number"
                                                    :name="'items[' + i + '][quantity]'"
                                                    x-model="item.quantity"
                                                    @input="scheduleQuote()"
                                                    min="0.001" step="any"
                                                    placeholder="{{ __('admin.quantity') }}"
                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    required>
                                                <span x-show="!item.custom" class="shrink-0 text-xs text-gray-500 tabular-nums"
                                                      x-text="availableProducts.find(p => String(p.id) === String(item.grade_key))?.unit ?? ''"></span>
                                            </div>
                                            {{-- Custom line unit: constrained to the catalog's display units. --}}
                                            <select x-show="item.custom" x-cloak
                                                :name="item.custom ? 'items[' + i + '][unit]' : null"
                                                x-model="item.unit"
                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                @foreach ($customUnitOptions as $opt)
                                                    <option value="{{ $opt['v'] }}">{{ $opt['l'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-gray-500 sm:sr-only">{{ __('shop.admin.create_line_total_label') }}</label>
                                            <div class="relative">
                                                <input type="number"
                                                    :name="'items[' + i + '][line_total_eur]'"
                                                    x-model="item.line_total_eur"
                                                    @input="item.lineTouched = true"
                                                    min="0" step="0.01"
                                                    class="block w-full rounded-md border-gray-300 pr-7 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    :disabled="quoteLoading"
                                                    required>
                                                <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-gray-400">€</span>
                                            </div>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs text-gray-500 sm:sr-only">{{ __('shop.admin.create_line_vat_label') }}</label>
                                            <select :name="'items[' + i + '][vat_rate_percent]'"
                                                x-model="item.vat_rate_percent"
                                                @change="item.vatTouched = true"
                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                required>
                                                <option value="7">{{ __('admin.product_form.vat_rate_7') }}</option>
                                                <option value="19">{{ __('admin.product_form.vat_rate_19') }}</option>
                                            </select>
                                        </div>
                                        <div class="sm:col-span-1 sm:flex sm:justify-end">
                                            <button type="button"
                                                @click="removeItem(i)"
                                                x-show="items.length > 1"
                                                class="text-xs text-red-500 hover:text-red-700 whitespace-nowrap"
                                                x-cloak>
                                                {{ __('shop.admin.create_remove_item') }}
                                            </button>
                                        </div>
                                        {{-- Per-line price-source remark: which tier price the
                                             autofilled line total uses, plus a comparison with
                                             the other tier when it differs. --}}
                                        <div class="sm:col-span-12" x-show="priceHint(item)" x-cloak>
                                            <p class="text-xs text-gray-500" x-html="priceHint(item)"></p>
                                        </div>
                                    </div>
                                </template>

                                @foreach ($errors->keys() as $key)
                                    @if (str_starts_with($key, 'items.'))
                                        <p class="text-xs text-red-600">{{ $errors->first($key) }}</p>
                                    @endif
                                @endforeach

                                <div class="mt-1 flex flex-wrap gap-4">
                                    <button type="button" @click="addItem()"
                                        class="text-sm text-emerald-700 hover:text-emerald-900">
                                        {{ __('shop.admin.create_add_item') }}
                                    </button>
                                    <button type="button" @click="addCustomLine()"
                                        class="text-sm text-gray-600 hover:text-gray-900">
                                        {{ __('shop.admin.create_add_custom_line') }}
                                    </button>
                                </div>
                            </div>
                        </template>

                        <p class="text-sm text-gray-400" x-show="!loading && availableProducts.length === 0">
                            {{ __('shop.admin.no_products_on_date') }}
                        </p>
                    </div>
                </div>

                {{-- ── Step 4: Customer details (optional) ──────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">4. {{ __('shop.admin.create_customer_heading') }}</h2>
                    </div>
                    <div class="space-y-4 p-6">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                                <input type="text" id="name" name="name" value="{{ old('name') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('email')" class="mt-1" />
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
                                <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Step 5: Fulfillment ──────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">5. {{ __('shop.admin.create_fulfillment_heading') }}</h2>
                    </div>
                    <div class="space-y-4 p-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('shop.fulfillment_mode_label') }}</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="fulfillment_mode" value="pickup"
                                        x-model="mode"
                                        @change="onModeChange()"
                                        class="text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm text-gray-700">{{ __('shop.fulfillment_pickup') }}</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="fulfillment_mode" value="delivery"
                                        x-model="mode"
                                        @change="onModeChange()"
                                        class="text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm text-gray-700">{{ __('shop.fulfillment_delivery') }}</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-1" />
                        </div>

                        <div x-show="mode === 'pickup'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('shop.pickup_location_section') }}</label>
                            <div class="space-y-2">
                                @foreach ($pickupLocations as $key => $loc)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="pickup_location" value="{{ $key }}"
                                            class="text-emerald-600 focus:ring-emerald-500"
                                            {{ old('pickup_location', 'karl_kunger') === $key ? 'checked' : '' }}>
                                        <span class="text-sm text-gray-700">
                                            <strong>{{ $loc['name'] }}</strong> —
                                            {{ $loc['street'] }}, {{ $loc['postal_code'] }} {{ $loc['city'] }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('pickup_location')" class="mt-1" />
                        </div>

                        <div x-show="mode === 'delivery'" class="space-y-3" x-cloak>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="delivery_street" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_street') }}</label>
                                    <input type="text" id="delivery_street" name="delivery_street" value="{{ old('delivery_street') }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <x-input-error :messages="$errors->get('delivery_street')" class="mt-1" />
                                </div>
                                <div>
                                    <label for="delivery_postal_code" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_postal_code') }}</label>
                                    <input type="text" id="delivery_postal_code" name="delivery_postal_code" value="{{ old('delivery_postal_code') }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-1" />
                                </div>
                                <div>
                                    <label for="delivery_city" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_city') }}</label>
                                    <input type="text" id="delivery_city" name="delivery_city" value="{{ old('delivery_city') }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <x-input-error :messages="$errors->get('delivery_city')" class="mt-1" />
                                </div>
                            </div>
                            <div>
                                <label for="delivery_notes" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_notes') }}</label>
                                <textarea id="delivery_notes" name="delivery_notes" rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('delivery_notes') }}</textarea>
                                <x-input-error :messages="$errors->get('delivery_notes')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Step 6: Delivery pricing + summary ───────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate && hasValidItems"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">6. {{ __('shop.admin.create_pricing_heading') }}</h2>
                        <span x-show="quoteLoading" class="text-xs text-gray-400">{{ __('Loading') }}…</span>
                    </div>
                    <div class="space-y-4 p-6">
                        <p class="text-sm text-gray-600">{{ __('shop.admin.create_pricing_hint') }}</p>

                        <div class="grid gap-4 sm:grid-cols-2" x-show="mode === 'delivery'" x-cloak>
                            <div>
                                <label for="delivery_fee_eur" class="block text-sm font-medium text-gray-700">{{ __('shop.admin.create_delivery_fee_label') }}</label>
                                <div class="relative mt-1">
                                    <input type="number"
                                        id="delivery_fee_eur"
                                        name="delivery_fee_eur"
                                        x-model="deliveryFeeEur"
                                        @input="deliveryPriceTouched = true"
                                        min="0" step="0.01"
                                        class="block w-full rounded-md border-gray-300 pr-8 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        :disabled="quoteLoading">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-400">€</span>
                                </div>
                                <x-input-error :messages="$errors->get('delivery_fee_eur')" class="mt-1" />
                            </div>
                            <div>
                                <label for="delivery_fee_vat_rate_percent" class="block text-sm font-medium text-gray-700">{{ __('shop.admin.create_delivery_vat_label') }}</label>
                                <select id="delivery_fee_vat_rate_percent"
                                    name="delivery_fee_vat_rate_percent"
                                    x-model="deliveryVatRate"
                                    @change="deliveryVatTouched = true"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="7">{{ __('admin.product_form.vat_rate_7') }}</option>
                                    <option value="19">{{ __('admin.product_form.vat_rate_19') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('delivery_fee_vat_rate_percent')" class="mt-1" />
                            </div>
                        </div>

                        <div class="rounded-md border border-gray-100 bg-gray-50 px-4 py-3 text-sm space-y-2">
                            <div class="flex items-baseline justify-between gap-4">
                                <span class="text-gray-600">{{ __('shop.summary_line_items') }}</span>
                                <span class="tabular-nums text-gray-900" x-text="itemsSubtotalLabel"></span>
                            </div>
                            <template x-for="row in vatSummaryLines" :key="row.rate">
                                <div class="flex items-baseline justify-between gap-4 text-gray-500">
                                    <span x-text="row.label"></span>
                                    <span class="tabular-nums" x-text="row.amount"></span>
                                </div>
                            </template>
                            <div class="flex items-baseline justify-between gap-4" x-show="mode === 'delivery'" x-cloak>
                                <span class="text-gray-600">{{ __('shop.summary_line_delivery') }}</span>
                                <span class="tabular-nums text-gray-900" x-text="deliveryFeeLabel"></span>
                            </div>
                            <div class="border-t border-gray-200 pt-2 flex items-baseline justify-between gap-4">
                                <span class="font-medium text-gray-700">{{ __('shop.admin.create_grand_total_label') }}</span>
                                <span class="font-semibold tabular-nums text-gray-900" x-text="grandTotalLabel"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4" x-show="selectedDate" x-cloak>
                    <button type="submit"
                        :disabled="availableProducts.length === 0 || loading"
                        class="btn-solid-sm disabled:cursor-not-allowed disabled:opacity-40">
                        {{ __('shop.admin.create_submit') }}
                    </button>
                    <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        {{ __('Cancel') }}
                    </a>
                </div>

            </form>
        </div>
    </div>

    <script>
    function adminOrderForm(config) {
        return {
            availableProductsUrl: config.availableProductsUrl,
            quoteUrl: config.quoteUrl,
            vatLineTemplate: config.vatLineTemplate || '',
            priceHintLabels: config.priceHintLabels || { reasons: {}, tiers: {}, compare_template: '', used_template: '' },
            selectedDate: config.preselectedDate || '',
            customerType: config.customerType || 'private',
            availableProducts: [],
            loading: false,
            quoteLoading: false,
            mode: config.pickupMode || 'pickup',
            items: config.preselectedItems && config.preselectedItems.length
                ? config.preselectedItems
                : [emptyLine()],
            deliveryFeeEur: config.preselectedDeliveryFee ?? '',
            deliveryVatRate: String(config.preselectedDeliveryVatRate ?? '7'),
            discountEur: 0,
            deliveryPriceTouched: config.preselectedDeliveryFee !== null && config.preselectedDeliveryFee !== '',
            deliveryVatTouched: false,
            _quoteTimer: null,

            get hasValidItems() {
                return this.items.some((item) =>
                    parseFloat(item.quantity) > 0
                    && (item.product_id || (item.custom && (item.name || '').trim() !== ''))
                );
            },

            get itemsSubtotal() {
                return this.items.reduce((sum, item) => {
                    const n = parseFloat(item.line_total_eur);
                    return sum + (Number.isNaN(n) ? 0 : n);
                }, 0);
            },

            get itemsSubtotalLabel() {
                return this.formatMoney(this.itemsSubtotal);
            },

            get deliveryFeeLabel() {
                const n = parseFloat(this.deliveryFeeEur);
                return this.formatMoney(Number.isNaN(n) ? 0 : n);
            },

            get grandTotalLabel() {
                const delivery = this.mode === 'delivery' ? parseFloat(this.deliveryFeeEur) : 0;
                const total = this.itemsSubtotal - (this.discountEur || 0) + (Number.isNaN(delivery) ? 0 : delivery);
                return this.formatMoney(total);
            },

            get vatSummaryLines() {
                const byRate = {};
                this.items.forEach((item) => {
                    const gross = parseFloat(item.line_total_eur);
                    const rate = parseInt(item.vat_rate_percent, 10);
                    if (Number.isNaN(gross) || gross <= 0 || Number.isNaN(rate)) return;
                    byRate[rate] = (byRate[rate] || 0) + gross * rate / (100 + rate);
                });
                if (this.mode === 'delivery') {
                    const fee = parseFloat(this.deliveryFeeEur);
                    const rate = parseInt(this.deliveryVatRate, 10);
                    if (!Number.isNaN(fee) && fee > 0 && !Number.isNaN(rate)) {
                        byRate[rate] = (byRate[rate] || 0) + fee * rate / (100 + rate);
                    }
                }
                return Object.keys(byRate)
                    .map((r) => parseInt(r, 10))
                    .sort((a, b) => a - b)
                    .map((rate) => ({
                        rate,
                        label: this.vatLineTemplate
                            .replace(':rate', String(rate))
                            .replace(':amount', this.formatMoney(byRate[rate]).replace(' €', '')),
                        amount: this.formatMoney(byRate[rate]),
                    }));
            },

            async init() {
                if (this.selectedDate) {
                    await this.fetchProducts();
                    if (this.hasValidItems) {
                        await this.recalculateQuote();
                    }
                }
            },

            async onDateChange() {
                this.availableProducts = [];
                if (!this.selectedDate) return;
                await this.fetchProducts();
                this.scheduleQuote();
            },

            async onCustomerTypeChange() {
                if (!this.selectedDate) return;
                await this.fetchProducts();
                this.scheduleQuote();
            },

            onModeChange() {
                if (this.mode === 'pickup') {
                    this.deliveryFeeEur = '0';
                    this.deliveryPriceTouched = false;
                    this.deliveryVatTouched = false;
                }
                this.scheduleQuote();
            },

            onLineProductChange(item) {
                // The dropdown stores a grade key ("123" for default, "123:7"
                // when a variant is picked) — split it back into the two
                // fields the backend expects.
                const key = String(item.grade_key || '');
                if (key === '') {
                    item.product_id = '';
                    item.variant_id = '';
                } else if (key.includes(':')) {
                    const [pid, vid] = key.split(':');
                    item.product_id = pid;
                    item.variant_id = vid;
                } else {
                    item.product_id = key;
                    item.variant_id = '';
                }
                // Customer-tier autofill is allowed to overwrite a previously
                // quoted line — admin changed the product, the old amount is
                // stale by definition.
                item.lineTouched = false;
                const product = this.availableProducts.find(
                    (p) => String(p.id) === String(item.grade_key),
                );
                if (product && !item.vatTouched) {
                    item.vat_rate_percent = String(product.vat_rate_percent ?? 7);
                }
                this.scheduleQuote();
            },

            scheduleQuote() {
                clearTimeout(this._quoteTimer);
                this._quoteTimer = setTimeout(() => this.recalculateQuote(), 300);
            },

            async recalculateQuote() {
                const validIndices = [];
                this.items.forEach((item, index) => {
                    if (item.product_id && parseFloat(item.quantity) > 0) {
                        validIndices.push(index);
                    }
                });
                if (!this.selectedDate || validIndices.length === 0) {
                    return;
                }

                this.quoteLoading = true;
                try {
                    const params = new URLSearchParams();
                    params.set('fulfillment_mode', this.mode);
                    // Customer type + date drive PriceResolver — without them
                    // every quote came back at the Private base price even
                    // when "Business customer" was picked.
                    params.set('customer_type', this.customerType);
                    if (this.selectedDate) {
                        params.set('expected_delivery_date', this.selectedDate);
                    }
                    validIndices.forEach((itemIndex, i) => {
                        const item = this.items[itemIndex];
                        params.set(`items[${i}][product_id]`, item.product_id);
                        if (item.variant_id) {
                            params.set(`items[${i}][variant_id]`, item.variant_id);
                        }
                        params.set(`items[${i}][quantity]`, item.quantity);
                    });
                    const res = await fetch(this.quoteUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        return;
                    }
                    const data = await res.json();
                    this.discountEur = data.discount_eur || 0;

                    validIndices.forEach((itemIndex, i) => {
                        const item = this.items[itemIndex];
                        const quoted = data.lines[i];
                        if (!quoted) return;
                        if (!item.lineTouched) {
                            item.line_total_eur = this.formatAmount(quoted.line_total_eur);
                        }
                        if (!item.vatTouched) {
                            item.vat_rate_percent = String(quoted.vat_rate_percent ?? 7);
                        }
                    });

                    if (!this.deliveryPriceTouched) {
                        this.deliveryFeeEur = this.mode === 'delivery'
                            ? this.formatAmount(data.delivery_fee_eur)
                            : '0';
                    }
                    if (!this.deliveryVatTouched && this.mode === 'delivery') {
                        this.deliveryVatRate = String(data.delivery_fee_vat_rate_percent ?? 7);
                    }
                } catch (e) {
                    // Keep last values on transient network errors.
                } finally {
                    this.quoteLoading = false;
                }
            },

            formatAmount(value) {
                const n = parseFloat(value);
                if (Number.isNaN(n)) {
                    return '';
                }
                return n.toFixed(2);
            },

            formatMoney(value) {
                const n = parseFloat(value);
                if (Number.isNaN(n)) {
                    return '—';
                }
                return n.toFixed(2).replace('.', ',') + ' €';
            },

            /**
             * Full price breakdown rendered as a small HTML block under the
             * item row: which price was auto-filled, the alternative
             * customer-type price, and any private-only volume tiers that
             * exist on this grade. Returns '' (so x-show hides the row)
             * when no product is selected.
             */
            priceHint(item) {
                const product = this.availableProducts.find(
                    (p) => String(p.id) === String(item.grade_key),
                );
                if (!product) return '';

                const labels = this.priceHintLabels;
                const privateLabel = labels.tiers?.private ?? 'Private';
                const businessLabel = labels.tiers?.business ?? 'Business';
                const isBusiness = this.customerType === 'business';

                // Which line is the "used" price (bolded) and which is the alt?
                const usedLabel = isBusiness ? businessLabel : privateLabel;
                const usedPrice = product.price;
                const altLabel = isBusiness ? privateLabel : businessLabel;
                const altPrice = product.alt_price;

                const lines = [];
                lines.push(
                    '<strong>' + this._esc(usedLabel) + ':</strong> ' + this._esc(usedPrice)
                );
                lines.push(
                    this._esc(altLabel) + ': ' + this._esc(altPrice)
                );
                // Private-only volume tiers, when set. Skip on business — the
                // resolver doesn't apply them and we don't want to mislead.
                const tiers = Array.isArray(product.private_tiers) ? product.private_tiers : [];
                if (tiers.length > 0) {
                    const tierLine = tiers.map((t) => this._esc(t.label)).join(' · ');
                    const prefix = isBusiness
                        ? this._esc(privateLabel) + ': '
                        : '';
                    lines.push(prefix + tierLine);
                }
                return lines.join('<br>');
            },

            _esc(s) {
                if (s == null) return '';
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            },

            async fetchProducts() {
                this.loading = true;
                try {
                    const params = new URLSearchParams({
                        date: this.selectedDate,
                        customer_type: this.customerType,
                    });
                    const res = await fetch(this.availableProductsUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        this.availableProducts = [];
                        return;
                    }
                    const data = await res.json();
                    // Defend against the endpoint returning an error object
                    // ({message: …}) instead of an array — without this guard
                    // the products list silently empties out (both x-if/.length
                    // checks fail) and the section appears blank.
                    this.availableProducts = Array.isArray(data) ? data : [];
                } catch (e) {
                    this.availableProducts = [];
                } finally {
                    this.loading = false;
                }
            },

            addItem() {
                this.items.push(emptyLine());
            },

            addCustomLine() {
                this.items.push(customLine());
            },

            removeItem(i) {
                if (this.items.length > 1) {
                    this.items.splice(i, 1);
                    this.scheduleQuote();
                }
            },
        };
    }

    function emptyLine() {
        return {
            custom: false,
            grade_key: '',
            product_id: '',
            variant_id: '',
            name: '',
            unit: '',
            quantity: '',
            line_total_eur: '',
            vat_rate_percent: '7',
            lineTouched: false,
            vatTouched: false,
        };
    }

    // Off-catalog line: no product, manual name/unit/total. lineTouched is set
    // so the quote endpoint (which only sees catalog products) never clobbers
    // the amount the admin typed.
    function customLine() {
        return {
            custom: true,
            grade_key: '',
            product_id: '',
            variant_id: '',
            name: '',
            unit: 'piece',
            quantity: '',
            line_total_eur: '',
            vat_rate_percent: '7',
            lineTouched: true,
            vatTouched: true,
        };
    }
    </script>
</x-admin-layout>
