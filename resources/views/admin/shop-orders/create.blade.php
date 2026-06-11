<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_list') }}</a>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.create_heading') }}</h1>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12"
         x-data="{
             mode: '{{ old('fulfillment_mode', 'pickup') }}',
             items: @js(
                 collect(old('items', [['product_id'=>'','quantity'=>'']]))->map(fn($i)=>['product_id'=>(string)($i['product_id']??''),'quantity'=>(string)($i['quantity']??'')])->values()->all()
             ),
             addItem() { this.items.push({ product_id: '', quantity: '' }); },
             removeItem(i) { if (this.items.length > 1) this.items.splice(i, 1); },
         }">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('error'))
                <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ session('error') }}</p>
            @endif

            <form method="POST" action="{{ route('app.admin.shop-orders.store') }}">
                @csrf

                {{-- ── Customer (optional) ──────────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.admin.create_customer_heading') }}</h2>
                    </div>
                    <div class="space-y-4 p-6">

                        {{-- Customer type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('shop.admin.customer_type_label') }}</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="customer_type" value="private"
                                        class="text-emerald-600 focus:ring-emerald-500"
                                        {{ old('customer_type', 'private') === 'private' ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700">{{ __('shop.admin.customer_type_private') }}</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="customer_type" value="business"
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

                {{-- ── Products ─────────────────────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.admin.create_items_heading') }}</h2>
                    </div>
                    <div class="p-6 space-y-3">
                        <x-input-error :messages="$errors->get('items')" class="mb-2" />

                        <template x-for="(item, i) in items" :key="i">
                            <div class="flex items-start gap-3">
                                {{-- Product selector --}}
                                <div class="flex-1">
                                    <select :name="'items[' + i + '][product_id]'"
                                        x-model="item.product_id"
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        required>
                                        <option value="">{{ __('admin.select_product') }}</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">
                                                {{ $product->name }}
                                                @if ($product->producer)
                                                    ({{ $product->producer->name }})
                                                @endif
                                                — {{ $product->displayPrice() }}
                                                / {{ $product->localizedUnit() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- Quantity --}}
                                <div class="w-28">
                                    <input type="number"
                                        :name="'items[' + i + '][quantity]'"
                                        x-model="item.quantity"
                                        min="0.001" step="any"
                                        placeholder="{{ __('admin.quantity') }}"
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                        required>
                                </div>
                                {{-- Remove button --}}
                                <button type="button"
                                    @click="removeItem(i)"
                                    x-show="items.length > 1"
                                    class="mt-0.5 text-xs text-red-500 hover:text-red-700 whitespace-nowrap"
                                    x-cloak>
                                    {{ __('shop.admin.create_remove_item') }}
                                </button>
                            </div>
                        </template>

                        {{-- Error messages per item --}}
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
                </div>

                {{-- ── Fulfillment ──────────────────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.admin.create_fulfillment_heading') }}</h2>
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

                        {{-- Date --}}
                        <div>
                            <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700">{{ __('shop.form_delivery_date') }}</label>
                            <input type="date" id="expected_delivery_date" name="expected_delivery_date"
                                value="{{ old('expected_delivery_date') }}"
                                class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                required>
                            <x-input-error :messages="$errors->get('expected_delivery_date')" class="mt-1" />
                        </div>

                        {{-- Pickup location --}}
                        <div x-show="mode === 'pickup'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('shop.pickup_location_section') }}</label>
                            <div class="space-y-2">
                                @foreach ($pickupLocations as $key => $loc)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="pickup_location" value="{{ $key }}"
                                            class="text-emerald-600 focus:ring-emerald-500"
                                            {{ old('pickup_location') === $key ? 'checked' : '' }}>
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

                {{-- ── Payment ──────────────────────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.admin.create_payment_heading') }}</h2>
                    </div>
                    <div class="p-6">
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="payment_method" value="in_person"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                    {{ old('payment_method', 'in_person') === 'in_person' ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ __('shop.payment_in_person') }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="payment_method" value="online"
                                    class="text-emerald-600 focus:ring-emerald-500"
                                    {{ old('payment_method') === 'online' ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ __('shop.payment_online') }}</span>
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ __('shop.admin.create_payment_hint') }}</p>
                        <x-input-error :messages="$errors->get('payment_method')" class="mt-1" />
                    </div>
                </div>

                {{-- ── Submit ───────────────────────────────────────────────── --}}
                <div class="flex items-center gap-4">
                    <button type="submit" class="btn-solid-sm">
                        {{ __('shop.admin.create_submit') }}
                    </button>
                    <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        {{ __('Cancel') }}
                    </a>
                </div>

            </form>
        </div>
    </div>
</x-admin-layout>
