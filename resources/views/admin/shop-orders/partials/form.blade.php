{{--
    Shared manual-order form for create AND edit.

    Expects:
      $formAction            POST target URL
      $formMethod            'POST' | 'PUT'
      $editing               bool — edit mode hides the recurring/B2B step,
                             freezes the coupon discount, and compensates the
                             stock display for the order's own quantities
      $order                 ?ShopOrder (edit mode)
      $preselectedItems      array (edit mode: stored lines, controller-mapped)
      $pickupLocations, $availableProductsUrl, $quoteUrl
      $restaurantAccounts    (create mode only)
      $submitLabel, $cancelUrl
--}}
@php
    $editing = $editing ?? false;
    $order = $order ?? null;
    $restaurantAccounts = $restaurantAccounts ?? collect();

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

    // Failed validation round-trips the admin's input; otherwise edit mode
    // prefills from the stored lines. Both shapes feed the same repeater.
    $oldItemPrefill = collect(old('items', []))->map(fn ($i) => [
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
        '_comboOpen' => false,
        '_comboQuery' => '',
        '_comboHighlight' => 0,
    ])->values()->all();
    $itemPrefill = $oldItemPrefill !== [] ? $oldItemPrefill : ($preselectedItems ?? []);

    $step = 0;
@endphp

<div class="py-8 sm:py-12"
     x-data="adminOrderForm({
         availableProductsUrl: @js($availableProductsUrl),
         quoteUrl: @js($quoteUrl),
         editing: @js($editing),
         excludeOrderId: @js($editing ? $order->id : null),
         frozenDiscount: @js($editing ? (float) $order->discount_eur : 0),
         pickupMode: '{{ old('fulfillment_mode', $order->fulfillment_mode ?? 'pickup') }}',
         customerType: '{{ $editing ? $order->customer_type->value : old('customer_type', 'private') }}',
         recurring: {{ old('recurring') ? 'true' : 'false' }},
         accountId: '{{ old('restaurant_user_id') }}',
         accounts: @js($editing ? [] : $restaurantAccounts->keyBy('user_id')),
         preselectedDate: '{{ old('expected_delivery_date', $editing ? $order->expected_delivery_date->toDateString() : '') }}',
         preselectedItems: @js($itemPrefill),
         preselectedDeliveryFee: @js(old('delivery_fee_eur', $editing && $order->fulfillment_mode === 'delivery' ? (string) $order->delivery_fee_eur : null)),
         preselectedDeliveryVatRate: @js(old('delivery_fee_vat_rate_percent', $editing ? (string) $order->delivery_fee_vat_rate_percent : '7')),
         vatLineTemplate: @js(__('shop.summary_vat_rate_line')),
         outOfStockLabel: @js(__('shop.admin.out_of_stock_tag')),
         lineLinkLabel: @js(__('shop.admin.create_line_link_label')),
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

        @if ($editing && $order->paid_at !== null)
            {{-- Money already moved: edits shift the balance, not the payment. --}}
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" data-testid="shop-order-edit-paid-warning">
                {{ __('shop.admin.edit_paid_warning', ['amount' => number_format((float) $order->effectivePaidAmountEur(), 2, ',', '.').' €']) }}
            </div>
        @endif

        <form method="POST" action="{{ $formAction }}">
            @csrf
            @if (($formMethod ?? 'POST') !== 'POST')
                @method($formMethod)
            @endif
            @if (! $editing)
                <input type="hidden" name="payment_method" value="in_person">
            @endif

            {{-- ── Step: Date ─────────────────────────────────────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ++$step }}. {{ __('shop.form_delivery_date') }}</h2>
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

            @if (! $editing)
                {{-- ── Step: Customer type (controls which tier price is loaded) ── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                     x-show="selectedDate"
                     x-cloak>
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ++$step }}. {{ __('shop.admin.customer_type_label') }}</h2>
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
                        <x-input-error :messages="$errors->get('customer_type')" class="mt-1" />

                        {{-- Recurring weekly B2B delivery: routes through the restaurant
                             order machinery (B2B price list, weekly materialization). --}}
                        <div x-show="customerType === 'business'" x-cloak class="space-y-3 rounded-md border border-gray-200 bg-gray-50 p-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="recurring" value="1" x-model="recurring"
                                       class="rounded text-emerald-600 focus:ring-emerald-500"
                                       {{ old('recurring') ? 'checked' : '' }}>
                                <span class="text-sm font-medium text-gray-800">{{ __('shop.admin.recurring_label') }}</span>
                            </label>
                            <div x-show="recurring" x-cloak class="space-y-3">
                                <p class="text-xs text-gray-500">{{ __('shop.admin.recurring_hint') }}</p>
                                {{-- Account from the customer-details picker — or auto-created
                                     from the customer details (name + email). --}}
                                <p x-show="!accountId" x-cloak class="text-xs text-gray-600">{{ __('shop.admin.recurring_auto_account_note') }}</p>
                                <div class="sm:max-w-xs">
                                    <label for="recurring_until" class="mb-1 block text-xs font-medium text-gray-600">{{ __('shop.admin.recurring_until_label') }}</label>
                                    <input type="date" id="recurring_until" name="recurring_until" value="{{ old('recurring_until') }}" class="block w-full rounded-md border-gray-300 text-sm">
                                    <p class="mt-1 text-xs text-gray-400">{{ __('shop.admin.recurring_until_hint') }}</p>
                                </div>
                                <x-input-error :messages="$errors->get('recurring')" class="mt-1" />
                                <x-input-error :messages="$errors->get('recurring_until')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Step: Products + per-line pricing ──────────────── --}}
            <div class="rounded-lg border border-gray-100 bg-white shadow-sm"
                 x-show="selectedDate"
                 x-cloak>
                <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ++$step }}. {{ __('shop.admin.create_items_heading') }}</h2>
                    <span x-show="loading" class="text-xs text-gray-400">{{ __('Loading') }}…</span>
                </div>
                <div class="p-6 space-y-3">
                    <x-input-error :messages="$errors->get('items')" class="mb-2" />

                    {{-- Always rendered (not gated on catalog hits): custom
                         off-catalog lines must work even when nothing is in
                         the catalog for that date, and edits must always show
                         their prefilled lines. --}}
                    <div class="space-y-3">
                            <div class="hidden sm:grid sm:grid-cols-12 sm:gap-3 sm:px-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                                <span class="sm:col-span-3">{{ __('admin.select_product') }}</span>
                                <span class="sm:col-span-2">{{ __('admin.quantity') }}</span>
                                <span class="sm:col-span-1 sm:text-center">{{ __('shop.admin.create_line_link_short') }}</span>
                                <span class="sm:col-span-2">{{ __('shop.admin.create_line_net_label') }}</span>
                                <span class="sm:col-span-2">{{ __('shop.admin.create_line_gross_label') }}</span>
                                <span class="sm:col-span-2">{{ __('shop.admin.create_line_vat_label') }}</span>
                            </div>

                            <template x-for="(item, i) in items" :key="i">
                                <div class="grid grid-cols-1 gap-3 rounded-md border border-gray-100 p-3 sm:grid-cols-12 sm:items-start sm:border-0 sm:p-0">
                                    <div class="sm:col-span-3">
                                        <label class="mb-1 block text-xs text-gray-500 sm:sr-only">{{ __('admin.select_product') }}</label>
                                        {{-- Catalog line: searchable product combobox. --}}
                                        <template x-if="!item.custom">
                                            <div class="relative" @click.outside="item._comboOpen = false">
                                                {{-- Selected state: badge with product label + clear button --}}
                                                <div x-show="item.grade_key !== ''"
                                                     class="flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-[7px] text-sm shadow-sm">
                                                    <span class="min-w-0 flex-1 truncate text-gray-900"
                                                          x-text="productLabel(item)"></span>
                                                    <button type="button"
                                                            @click.stop="clearProduct(item)"
                                                            class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700"
                                                            aria-label="{{ __('Clear') }}">&times;</button>
                                                </div>
                                                {{-- Search mode: filter input + dropdown --}}
                                                <div x-show="item.grade_key === ''" class="relative">
                                                    <input type="text"
                                                           x-model="item._comboQuery"
                                                           @focus="item._comboOpen = true; item._comboHighlight = 0"
                                                           @input="item._comboOpen = true; item._comboHighlight = 0"
                                                           @keydown="handleProductComboKey($event, item)"
                                                           placeholder="{{ __('admin.select_product') }}…"
                                                           autocomplete="off"
                                                           class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                    <ul x-show="item._comboOpen && filteredProducts(item).length > 0"
                                                        x-cloak
                                                        class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                                        <template x-for="(p, idx) in filteredProducts(item)" :key="p.id">
                                                            <li :class="idx === item._comboHighlight ? 'bg-emerald-50 text-emerald-900' : 'text-gray-700 hover:bg-gray-50'"
                                                                class="cursor-pointer px-3 py-2 text-sm leading-snug"
                                                                @mouseenter="item._comboHighlight = idx"
                                                                @mousedown.prevent="selectProduct(item, p)">
                                                                <span x-text="p.name"></span>
                                                                <span x-show="p.producer" class="text-xs text-gray-500 ml-1.5" x-text="p.producer"></span>
                                                                <span x-show="parseFloat(p.remaining) <= 0" class="text-xs text-amber-600 ml-1.5" x-text="'· ' + outOfStockLabel"></span>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                    <p x-show="item._comboOpen && item._comboQuery.trim() !== '' && filteredProducts(item).length === 0"
                                                       x-cloak
                                                       class="absolute z-30 mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 shadow-lg">
                                                        {{ __('shop.admin.no_products_on_date') }}
                                                    </p>
                                                </div>
                                            </div>
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
                                                @input="onQuantityInput(item)"
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
                                    {{-- Link toggle: when on, quantity and price track each
                                         other through a fixed unit price. When off, both are
                                         entered freely. Sits between quantity and price. --}}
                                    <div class="sm:col-span-1">
                                        <label class="mb-1 block text-xs text-gray-500 sm:sr-only" x-text="linkLabel"></label>
                                        <div class="flex sm:justify-center">
                                            <label class="inline-flex cursor-pointer items-center gap-2" :title="linkLabel">
                                                <input type="checkbox"
                                                    x-model="item.priceLinked"
                                                    @change="onLinkToggle(item)"
                                                    class="peer sr-only">
                                                <span class="flex h-[38px] w-9 items-center justify-center rounded-md border border-gray-300 text-gray-400 transition peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500" aria-hidden="true">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5" />
                                                    </svg>
                                                </span>
                                                <span class="text-xs text-gray-500 sm:hidden" x-text="item.priceLinked ? '{{ __('shop.admin.create_line_link_on') }}' : '{{ __('shop.admin.create_line_link_off') }}'"></span>
                                            </label>
                                        </div>
                                    </div>
                                    {{-- Net line total: display + editable. Not submitted;
                                         the backend derives net from the gross line total. --}}
                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-xs text-gray-500 sm:sr-only">{{ __('shop.admin.create_line_net_label') }}</label>
                                        <div class="relative">
                                            <input type="number"
                                                x-model="item.line_net_eur"
                                                @input="onNetInput(item)"
                                                min="0" step="0.01"
                                                aria-label="{{ __('shop.admin.create_line_net_label') }}"
                                                class="block w-full rounded-md border-gray-300 pr-7 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                :disabled="quoteLoading">
                                            <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-gray-400">€</span>
                                        </div>
                                    </div>
                                    {{-- Gross line total: the value stored on the order line
                                         (prices are gross). Submitted as line_total_eur. --}}
                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-xs text-gray-500 sm:sr-only">{{ __('shop.admin.create_line_gross_label') }}</label>
                                        <div class="relative">
                                            <input type="number"
                                                :name="'items[' + i + '][line_total_eur]'"
                                                x-model="item.line_total_eur"
                                                @input="onGrossInput(item)"
                                                min="0" step="0.01"
                                                aria-label="{{ __('shop.admin.create_line_gross_label') }}"
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
                                            @change="onVatChange(item)"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                            required>
                                            <option value="7">{{ __('admin.product_form.vat_rate_7') }}</option>
                                            <option value="19">{{ __('admin.product_form.vat_rate_19') }}</option>
                                        </select>
                                    </div>
                                    {{-- Footer: per-line price-source remark on the left, the
                                         remove control on the right. --}}
                                    <div class="sm:col-span-12 flex items-start justify-between gap-3">
                                        <p class="min-w-0 flex-1 text-xs text-gray-500" x-show="priceHint(item)" x-cloak x-html="priceHint(item)"></p>
                                        <button type="button"
                                            @click="removeItem(i)"
                                            x-show="items.length > 1"
                                            class="shrink-0 text-xs text-red-500 hover:text-red-700 whitespace-nowrap"
                                            x-cloak>
                                            {{ __('shop.admin.create_remove_item') }}
                                        </button>
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
                </div>
            </div>

            {{-- ── Step: Customer details (optional) ──────────────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                 x-show="selectedDate"
                 x-cloak>
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ++$step }}. {{ __('shop.admin.create_customer_heading') }}</h2>
                </div>
                <div class="space-y-4 p-6">
                    @if (! $editing)
                        {{-- Pick a verified restaurant account to prefill contact +
                             delivery address. Restaurant accounts are B2B-only, so
                             the picker only shows for business customers; switching
                             back to private clears the selection. --}}
                        <div x-show="customerType === 'business'" x-cloak>
                            <label for="restaurant_user_id" class="block text-sm font-medium text-gray-700">{{ __('shop.admin.account_select_label') }}</label>
                            <select id="restaurant_user_id" name="restaurant_user_id" x-model="accountId" @change="applyAccount()"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:max-w-md"
                                    data-testid="shop-admin-account-select">
                                <option value="">{{ __('shop.admin.account_select_none') }}</option>
                                @foreach ($restaurantAccounts as $acc)
                                    <option value="{{ $acc['user_id'] }}" @selected((string) old('restaurant_user_id') === (string) $acc['user_id'])>{{ $acc['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">{{ __('shop.admin.account_select_hint') }}</p>
                            <x-input-error :messages="$errors->get('restaurant_user_id')" class="mt-1" />
                        </div>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Name') }}</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $order->name ?? '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $order->email ?? '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $order->phone ?? '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Step: Fulfillment ──────────────────────────────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                 x-show="selectedDate"
                 x-cloak>
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ++$step }}. {{ __('shop.admin.create_fulfillment_heading') }}</h2>
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
                                        {{ old('pickup_location', $order->pickup_location ?? 'karl_kunger') === $key ? 'checked' : '' }}>
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
                                <input type="text" id="delivery_street" name="delivery_street" value="{{ old('delivery_street', $order->delivery_street ?? '') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('delivery_street')" class="mt-1" />
                            </div>
                            <div>
                                <label for="delivery_postal_code" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_postal_code') }}</label>
                                <input type="text" id="delivery_postal_code" name="delivery_postal_code" value="{{ old('delivery_postal_code', $order->delivery_postal_code ?? '') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-1" />
                            </div>
                            <div>
                                <label for="delivery_city" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_city') }}</label>
                                <input type="text" id="delivery_city" name="delivery_city" value="{{ old('delivery_city', $order->delivery_city ?? '') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('delivery_city')" class="mt-1" />
                            </div>
                        </div>
                        <div>
                            <label for="delivery_notes" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_notes') }}</label>
                            <textarea id="delivery_notes" name="delivery_notes" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('delivery_notes', $order->delivery_notes ?? '') }}</textarea>
                            <x-input-error :messages="$errors->get('delivery_notes')" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Step: Delivery pricing + summary ───────────────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm"
                 x-show="selectedDate && hasValidItems"
                 x-cloak>
                <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ++$step }}. {{ __('shop.admin.create_pricing_heading') }}</h2>
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
                        @if ($editing && (float) $order->discount_eur > 0)
                            {{-- Coupon discounts don't re-resolve on edit; the stored
                                 amount is frozen and shown for transparency. --}}
                            <div class="flex items-baseline justify-between gap-4 text-gray-500" data-testid="shop-order-edit-frozen-discount">
                                <span>{{ __('shop.admin.coupon_frozen_note', ['code' => (string) $order->coupon_code]) }}</span>
                                <span class="tabular-nums">&minus;{{ number_format((float) $order->discount_eur, 2, ',', '.') }} €</span>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between gap-4" x-show="mode === 'delivery'" x-cloak>
                            <span class="text-gray-600">{{ __('shop.summary_line_delivery') }}</span>
                            <span class="tabular-nums text-gray-900" x-text="deliveryFeeLabel"></span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex items-baseline justify-between gap-4">
                            <span class="font-medium text-gray-700">{{ __('shop.admin.create_grand_total_label') }}</span>
                            <span class="font-semibold tabular-nums text-gray-900" x-text="grandTotalLabel"></span>
                        </div>
                        @if ($editing && $order->paid_at !== null)
                            <div class="flex items-baseline justify-between gap-4 text-gray-500">
                                <span>{{ __('shop.admin.label_already_paid') }}</span>
                                <span class="tabular-nums">{{ number_format((float) $order->effectivePaidAmountEur(), 2, ',', '.') }} €</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4" x-show="selectedDate" x-cloak>
                {{-- Only gated on loading: an order may consist entirely of
                     custom lines, so an empty catalog must not block submit. --}}
                <button type="submit"
                    :disabled="loading"
                    class="btn-solid-sm disabled:cursor-not-allowed disabled:opacity-40">
                    {{ $submitLabel }}
                </button>
                <a href="{{ $cancelUrl }}" class="text-sm text-gray-500 hover:text-gray-700">
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
        // Edit mode: compensate stock for the order's own quantities and
        // freeze the stored coupon discount (never re-quoted).
        editing: config.editing || false,
        excludeOrderId: config.excludeOrderId || null,
        vatLineTemplate: config.vatLineTemplate || '',
        outOfStockLabel: config.outOfStockLabel || '',
        linkLabel: config.lineLinkLabel || '',
        priceHintLabels: config.priceHintLabels || { reasons: {}, tiers: {}, compare_template: '', used_template: '' },
        selectedDate: config.preselectedDate || '',
        customerType: config.customerType || 'private',
        recurring: config.recurring || false,
        accountId: config.accountId || '',
        accounts: config.accounts || {},

        // Picking a verified restaurant account prefills contact + delivery
        // address and switches to business pricing. Clearing it leaves the
        // typed values untouched (the admin may have edited them).
        applyAccount() {
            const acc = this.accounts[this.accountId];
            if (!acc) return;
            const set = (id, v) => { const el = document.getElementById(id); if (el && v) el.value = v; };
            set('name', acc.name);
            set('email', acc.email);
            set('phone', acc.phone);
            set('delivery_street', acc.street);
            set('delivery_postal_code', acc.postal);
            set('delivery_city', acc.city);
            if (this.customerType !== 'business') {
                this.customerType = 'business';
                this.onCustomerTypeChange();
            }
            if (this.mode !== 'delivery') {
                this.mode = 'delivery';
                this.onModeChange();
            }
        },
        availableProducts: [],
        loading: false,
        quoteLoading: false,
        mode: config.pickupMode || 'pickup',
        items: (config.preselectedItems && config.preselectedItems.length
            ? config.preselectedItems
            : [emptyLine()]).map(item => ({
            ...item,
            // Net line total is display-only (backend derives it); the link
            // ties quantity and price together via a held gross unit price.
            line_net_eur: item.line_net_eur ?? '',
            priceLinked: item.priceLinked ?? !item.custom,
            unitPriceGross: item.unitPriceGross ?? 0,
            _comboOpen: false,
            _comboQuery: item._comboQuery || '',
            _comboHighlight: 0,
        })),
        deliveryFeeEur: config.preselectedDeliveryFee ?? '',
        deliveryVatRate: String(config.preselectedDeliveryVatRate ?? '7'),
        discountEur: config.frozenDiscount || 0,
        deliveryPriceTouched: config.preselectedDeliveryFee !== null && config.preselectedDeliveryFee !== '',
        // Edit mode: the stored delivery VAT rate is authoritative; a later
        // catalog quote must not reset it to the modal default.
        deliveryVatTouched: config.editing || false,
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
            // Seed the net display and the held unit price from any prefilled
            // (edit-mode / validation-return) gross totals, so the net field
            // and the quantity/price link work immediately without a re-quote.
            this.items.forEach((item) => {
                if (item.line_net_eur === '' && item.line_total_eur !== '') {
                    this.netFromGross(item);
                }
                if (!(item.unitPriceGross > 0)) {
                    const g = parseFloat(item.line_total_eur);
                    const q = parseFloat(item.quantity);
                    if (!Number.isNaN(g) && g > 0 && !Number.isNaN(q) && q > 0) {
                        item.unitPriceGross = g / q;
                    }
                }
            });
            if (this.selectedDate) {
                await this.fetchProducts();
                if (this.hasValidItems && !this.editing) {
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
            // Restaurant accounts are B2B-only: dropping back to private
            // clears a previously picked account (typed contact details
            // stay untouched — the admin may have edited them).
            if (this.customerType !== 'business') {
                this.accountId = '';
            }
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
            // stale by definition. A fresh product re-links quantity/price.
            item.lineTouched = false;
            item.priceLinked = true;
            const product = this.availableProducts.find(
                (p) => String(p.id) === String(item.grade_key),
            );
            if (product) {
                const up = parseFloat(product.unit_price_eur);
                item.unitPriceGross = (!Number.isNaN(up) && up > 0) ? up : 0;
                if (!item.vatTouched) {
                    item.vat_rate_percent = String(product.vat_rate_percent ?? 7);
                }
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
                if (!this.editing) {
                    this.discountEur = data.discount_eur || 0;
                }

                validIndices.forEach((itemIndex, i) => {
                    const item = this.items[itemIndex];
                    const quoted = data.lines[i];
                    if (!quoted) return;
                    // Tier-aware gross unit price anchors the quantity/price link.
                    if (quoted.unit_price_eur != null) {
                        const up = parseFloat(quoted.unit_price_eur);
                        if (!Number.isNaN(up) && up > 0) item.unitPriceGross = up;
                    }
                    if (!item.lineTouched) {
                        item.line_total_eur = this.formatAmount(quoted.line_total_eur);
                    }
                    if (!item.vatTouched) {
                        item.vat_rate_percent = String(quoted.vat_rate_percent ?? 7);
                    }
                    // Net display always follows the gross total and VAT rate.
                    this.netFromGross(item);
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

        filteredProducts(item) {
            const q = (item._comboQuery || '').trim().toLocaleLowerCase();
            if (q === '') return this.availableProducts;
            return this.availableProducts.filter((p) =>
                p.name.toLocaleLowerCase().includes(q) ||
                (p.producer && p.producer.toLocaleLowerCase().includes(q))
            );
        },

        productLabel(item) {
            const p = this.availableProducts.find((p) => String(p.id) === String(item.grade_key));
            if (!p) return '';
            return p.name + (p.producer ? ' · ' + p.producer : '') + ' — ' + p.price + ' / ' + p.display_unit;
        },

        selectProduct(item, p) {
            item.grade_key = String(p.id);
            item._comboQuery = '';
            item._comboOpen = false;
            item._comboHighlight = 0;
            this.onLineProductChange(item);
        },

        clearProduct(item) {
            item.grade_key = '';
            item.product_id = '';
            item.variant_id = '';
            item._comboQuery = '';
            item._comboOpen = false;
            item._comboHighlight = 0;
        },

        handleProductComboKey(event, item) {
            const list = this.filteredProducts(item);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                item._comboOpen = true;
                item._comboHighlight = Math.min(list.length - 1, (item._comboHighlight || 0) + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                item._comboHighlight = Math.max(0, (item._comboHighlight || 0) - 1);
            } else if (event.key === 'Enter') {
                if (item._comboOpen && list[item._comboHighlight]) {
                    event.preventDefault();
                    this.selectProduct(item, list[item._comboHighlight]);
                }
            } else if (event.key === 'Escape') {
                item._comboOpen = false;
            }
        },

        async fetchProducts() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    date: this.selectedDate,
                    customer_type: this.customerType,
                });
                if (this.excludeOrderId) {
                    // Order edit: the availability matrix counts this order's
                    // own quantities as committed — the endpoint adds them back
                    // so the form doesn't declare its own order out of stock.
                    params.set('exclude_order_id', String(this.excludeOrderId));
                }
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

        // ---- Net / gross / quantity coupling -------------------------------
        // Prices are stored gross. The net field is display-only and always
        // derived from the gross total via the line's VAT rate. The "link"
        // holds a gross unit price so quantity and price move together.

        vatMult(item) {
            const r = parseInt(item.vat_rate_percent, 10);
            return 1 + (Number.isNaN(r) ? 0 : r) / 100;
        },

        netFromGross(item) {
            const g = parseFloat(item.line_total_eur);
            item.line_net_eur = Number.isNaN(g) ? '' : this.formatAmount(g / this.vatMult(item));
        },

        grossFromNet(item) {
            const n = parseFloat(item.line_net_eur);
            item.line_total_eur = Number.isNaN(n) ? '' : this.formatAmount(n * this.vatMult(item));
        },

        // Quantity in the smallest sensible form (up to 3 decimals, no
        // trailing zeros) so a linked price change reads cleanly.
        formatQuantity(value) {
            const n = parseFloat(value);
            if (Number.isNaN(n)) return '';
            return String(parseFloat(n.toFixed(3)));
        },

        // Admin edited the gross field: re-split net, and if linked, back out
        // the quantity from the held unit price.
        onGrossInput(item) {
            item.lineTouched = true;
            this.netFromGross(item);
            this.recalcQuantityFromPrice(item);
        },

        // Admin edited the net field: gross follows via VAT, quantity follows
        // when linked.
        onNetInput(item) {
            item.lineTouched = true;
            this.grossFromNet(item);
            this.recalcQuantityFromPrice(item);
        },

        recalcQuantityFromPrice(item) {
            if (!item.priceLinked || !(item.unitPriceGross > 0)) return;
            const g = parseFloat(item.line_total_eur);
            if (Number.isNaN(g) || g <= 0) return;
            item.quantity = this.formatQuantity(g / item.unitPriceGross);
        },

        // Admin edited the quantity. When linked, drive the price from the
        // held unit price and freeze the line so the quote can't clobber it.
        onQuantityInput(item) {
            if (item.priceLinked && item.unitPriceGross > 0) {
                const q = parseFloat(item.quantity);
                if (!Number.isNaN(q) && q > 0) {
                    const gross = item.unitPriceGross * q;
                    item.line_total_eur = this.formatAmount(gross);
                    item.line_net_eur = this.formatAmount(gross / this.vatMult(item));
                    item.lineTouched = true;
                }
            }
            this.scheduleQuote();
        },

        // VAT change keeps the gross (customer) price fixed and re-splits net.
        onVatChange(item) {
            item.vatTouched = true;
            this.netFromGross(item);
        },

        // Turning the link on captures the current price/quantity ratio as the
        // held unit price.
        onLinkToggle(item) {
            if (!item.priceLinked) return;
            const g = parseFloat(item.line_total_eur);
            const q = parseFloat(item.quantity);
            if (!Number.isNaN(g) && g > 0 && !Number.isNaN(q) && q > 0) {
                item.unitPriceGross = g / q;
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
        line_net_eur: '',
        vat_rate_percent: '7',
        priceLinked: true,
        unitPriceGross: 0,
        lineTouched: false,
        vatTouched: false,
        _comboOpen: false,
        _comboQuery: '',
        _comboHighlight: 0,
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
        line_net_eur: '',
        // Off-catalog lines default to free entry (no held unit price); the
        // net/gross fields still stay coupled through the VAT rate.
        vat_rate_percent: '7',
        priceLinked: false,
        unitPriceGross: 0,
        lineTouched: true,
        vatTouched: true,
    };
}
</script>
