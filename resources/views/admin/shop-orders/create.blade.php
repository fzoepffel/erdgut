<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_list') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.create_heading') }}</h1>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12"
         x-data="adminOrderForm({
             availableProductsUrl: @js($availableProductsUrl),
             quoteUrl: @js($quoteUrl),
             pickupMode: '{{ old('fulfillment_mode', 'pickup') }}',
             customerType: '{{ old('customer_type', 'private') }}',
             preselectedDate: '{{ old('expected_delivery_date', '') }}',
             preselectedItems: @js(collect(old('items', []))->map(fn($i) => ['product_id' => (string)($i['product_id'] ?? ''), 'quantity' => (string)($i['quantity'] ?? '')])->values()->all()),
             preselectedItemsTotal: @js(old('items_total_eur')),
             preselectedDeliveryFee: @js(old('delivery_fee_eur')),
         })">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('error'))
                <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</p>
            @endif

            <form method="POST" action="{{ route('app.admin.shop-orders.store') }}">
                @csrf
                {{-- payment_method is always in_person for admin orders --}}
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

                {{-- ── Step 2: Products (only once a date is chosen) ────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">2. {{ __('shop.admin.create_items_heading') }}</h2>
                        <span x-show="loading" class="text-xs text-gray-400">{{ __('Loading') }}…</span>
                    </div>
                    <div class="p-6 space-y-3">
                        <x-input-error :messages="$errors->get('items')" class="mb-2" />

                        <template x-if="availableProducts.length > 0">
                            <div class="space-y-3">
                                <template x-for="(item, i) in items" :key="i">
                                    <div class="flex items-start gap-3">
                                        {{-- Product selector --}}
                                        <div class="flex-1">
                                            <select :name="'items[' + i + '][product_id]'"
                                                x-model="item.product_id"
                                                @change="scheduleQuote()"
                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                required>
                                                <option value="">{{ __('admin.select_product') }}</option>
                                                <template x-for="p in availableProducts" :key="p.id">
                                                    <option :value="String(p.id)"
                                                            :selected="String(p.id) === String(item.product_id)"
                                                            x-text="p.name + (p.producer ? ' (' + p.producer + ')' : '') + ' — ' + p.price + ' / ' + p.unit"></option>
                                                </template>
                                            </select>
                                        </div>
                                        {{-- Quantity + unit label --}}
                                        <div class="flex items-center gap-1.5">
                                            <input type="number"
                                                :name="'items[' + i + '][quantity]'"
                                                x-model="item.quantity"
                                                @input="scheduleQuote()"
                                                min="0.001" step="any"
                                                placeholder="{{ __('admin.quantity') }}"
                                                class="block w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                required>
                                            <span class="w-8 text-xs text-gray-500 tabular-nums"
                                                  x-text="availableProducts.find(p => String(p.id) === String(item.product_id))?.unit ?? ''"></span>
                                        </div>
                                        {{-- Remove --}}
                                        <button type="button"
                                            @click="removeItem(i)"
                                            x-show="items.length > 1"
                                            class="mt-1 text-xs text-red-500 hover:text-red-700 whitespace-nowrap"
                                            x-cloak>
                                            {{ __('shop.admin.create_remove_item') }}
                                        </button>
                                    </div>
                                </template>

                                @foreach ($errors->keys() as $key)
                                    @if (str_starts_with($key, 'items.'))
                                        <p class="text-xs text-red-600">{{ $errors->first($key) }}</p>
                                    @endif
                                @endforeach

                                <button type="button" @click="addItem()"
                                    class="mt-1 text-sm text-emerald-700 hover:text-emerald-900">
                                    {{ __('shop.admin.create_add_item') }}
                                </button>
                            </div>
                        </template>

                        <p class="text-sm text-gray-400" x-show="!loading && availableProducts.length === 0">
                            {{ __('shop.admin.no_products_on_date') }}
                        </p>
                    </div>
                </div>

                {{-- ── Step 3: Customer (optional) ──────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">3. {{ __('shop.admin.create_customer_heading') }}</h2>
                    </div>
                    <div class="space-y-4 p-6">

                        {{-- Customer type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('shop.admin.customer_type_label') }}</label>
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
                            <x-input-error :messages="$errors->get('customer_type')" class="mt-1" />
                        </div>

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

                {{-- ── Step 4: Fulfillment ──────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">4. {{ __('shop.admin.create_fulfillment_heading') }}</h2>
                    </div>
                    <div class="space-y-4 p-6">

                        {{-- Mode --}}
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

                        {{-- Pickup location --}}
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

                        {{-- Delivery address --}}
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

                {{-- ── Step 5: Pricing ──────────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate && hasValidItems"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">5. {{ __('shop.admin.create_pricing_heading') }}</h2>
                        <span x-show="quoteLoading" class="text-xs text-gray-400">{{ __('Loading') }}…</span>
                    </div>
                    <div class="space-y-4 p-6">
                        <p class="text-sm text-gray-600">{{ __('shop.admin.create_pricing_hint') }}</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="items_total_eur" class="block text-sm font-medium text-gray-700">{{ __('shop.admin.create_items_total_label') }}</label>
                                <div class="relative mt-1">
                                    <input type="number"
                                        id="items_total_eur"
                                        name="items_total_eur"
                                        x-model="itemsTotalEur"
                                        @input="itemsPriceTouched = true"
                                        min="0" step="0.01"
                                        class="block w-full rounded-md border-gray-300 pr-8 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        :disabled="quoteLoading">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-400">€</span>
                                </div>
                                <x-input-error :messages="$errors->get('items_total_eur')" class="mt-1" />
                            </div>

                            <div x-show="mode === 'delivery'" x-cloak>
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
                        </div>

                        <div class="rounded-md border border-gray-100 bg-gray-50 px-4 py-3 text-sm">
                            <div class="flex items-baseline justify-between gap-4">
                                <span class="font-medium text-gray-700">{{ __('shop.admin.create_grand_total_label') }}</span>
                                <span class="font-semibold tabular-nums text-gray-900" x-text="grandTotalLabel"></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Submit ───────────────────────────────────────────── --}}
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
            selectedDate: config.preselectedDate || '',
            customerType: config.customerType || 'private',
            availableProducts: [],
            loading: false,
            quoteLoading: false,
            mode: config.pickupMode || 'pickup',
            items: config.preselectedItems && config.preselectedItems.length
                ? config.preselectedItems
                : [{ product_id: '', quantity: '' }],
            itemsTotalEur: config.preselectedItemsTotal ?? '',
            deliveryFeeEur: config.preselectedDeliveryFee ?? '',
            discountEur: 0,
            itemsPriceTouched: config.preselectedItemsTotal !== null && config.preselectedItemsTotal !== '',
            deliveryPriceTouched: config.preselectedDeliveryFee !== null && config.preselectedDeliveryFee !== '',
            _quoteTimer: null,

            get hasValidItems() {
                return this.items.some((item) => item.product_id && parseFloat(item.quantity) > 0);
            },

            get grandTotalLabel() {
                const items = parseFloat(this.itemsTotalEur);
                const delivery = this.mode === 'delivery' ? parseFloat(this.deliveryFeeEur) : 0;
                if (Number.isNaN(items)) {
                    return '—';
                }
                const total = items - (this.discountEur || 0) + (Number.isNaN(delivery) ? 0 : delivery);
                return total.toFixed(2).replace('.', ',') + ' €';
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
                }
                this.scheduleQuote();
            },

            scheduleQuote() {
                clearTimeout(this._quoteTimer);
                this._quoteTimer = setTimeout(() => this.recalculateQuote(), 300);
            },

            async recalculateQuote() {
                const validItems = this.items.filter(
                    (item) => item.product_id && parseFloat(item.quantity) > 0,
                );
                if (!this.selectedDate || validItems.length === 0) {
                    return;
                }

                this.quoteLoading = true;
                try {
                    const params = new URLSearchParams();
                    params.set('fulfillment_mode', this.mode);
                    validItems.forEach((item, i) => {
                        params.set(`items[${i}][product_id]`, item.product_id);
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
                    if (!this.itemsPriceTouched) {
                        this.itemsTotalEur = this.formatAmount(data.items_total_eur);
                    }
                    if (!this.deliveryPriceTouched) {
                        this.deliveryFeeEur = this.mode === 'delivery'
                            ? this.formatAmount(data.delivery_fee_eur)
                            : '0';
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
                    this.availableProducts = await res.json();
                } catch (e) {
                    this.availableProducts = [];
                } finally {
                    this.loading = false;
                }
            },

            addItem() {
                this.items.push({ product_id: '', quantity: '' });
            },

            removeItem(i) {
                if (this.items.length > 1) {
                    this.items.splice(i, 1);
                    this.scheduleQuote();
                }
            },
        };
    }
    </script>
</x-admin-layout>
