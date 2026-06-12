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
             pickupMode: '{{ old('fulfillment_mode', 'pickup') }}',
             customerType: '{{ old('customer_type', 'private') }}',
             preselectedDate: '{{ old('expected_delivery_date', '') }}',
             preselectedItems: @js(collect(old('items', []))->map(fn($i) => ['product_id' => (string)($i['product_id'] ?? ''), 'quantity' => (string)($i['quantity'] ?? '')])->values()->all()),
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
                                        class="text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-sm text-gray-700">{{ __('shop.fulfillment_pickup') }}</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="fulfillment_mode" value="delivery"
                                        x-model="mode"
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
            selectedDate: config.preselectedDate || '',
            customerType: config.customerType || 'private',
            availableProducts: [],
            loading: false,
            mode: config.pickupMode || 'pickup',
            items: config.preselectedItems && config.preselectedItems.length
                ? config.preselectedItems
                : [{ product_id: '', quantity: '' }],

            async init() {
                if (this.selectedDate) {
                    await this.fetchProducts();
                }
            },

            async onDateChange() {
                this.availableProducts = [];
                if (!this.selectedDate) return;
                await this.fetchProducts();
            },

            async onCustomerTypeChange() {
                if (!this.selectedDate) return;
                await this.fetchProducts();
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
                if (this.items.length > 1) this.items.splice(i, 1);
            },
        };
    }
    </script>
</x-admin-layout>
