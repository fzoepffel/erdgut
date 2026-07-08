<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('app.admin.restaurant-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_list') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.admin_create_request_title') }}</h1>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12"
         x-data="adminRequestForm({
             accountId: '{{ old('restaurant_user_id') }}',
             accounts: @js($restaurantAccounts->keyBy('user_id')),
             options: @js($productOptions),
             oldItems: @js(collect(old('items', []))->values()->all()),
         })">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            <p class="text-sm text-gray-600">{{ __('restaurant.admin_create_request_intro') }}</p>

            @if ($errors->has('items'))
                <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first('items') }}</p>
            @endif

            <form method="POST" action="{{ route('app.admin.restaurant-orders.store') }}">
                @csrf

                {{-- ── 1. Restaurant ─────────────────────────────────────── --}}
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">1. {{ __('shop.admin.account_select_label') }}</h2>
                    </div>
                    <div class="space-y-4 p-6">
                        <div class="sm:max-w-md">
                            <select id="restaurant_user_id" name="restaurant_user_id" x-model="accountId" @change="applyAccount()"
                                    data-testid="admin-request-account-select"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">{{ __('shop.admin.account_select_none') }}</option>
                                @foreach ($restaurantAccounts as $account)
                                    <option value="{{ $account['user_id'] }}">{{ $account['label'] }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('restaurant_user_id')" class="mt-1" />
                        </div>
                        <div x-show="!accountId" x-cloak class="space-y-3">
                            <p class="text-xs text-gray-500">{{ __('restaurant.admin_create_request_account_hint') }}</p>
                            <div class="grid gap-4 sm:grid-cols-2">
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
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── 2. Delivery ───────────────────────────────────────── --}}
                <div class="mt-6 overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">2. {{ __('restaurant.cart_delivery_address') }}</h2>
                    </div>
                    <div class="space-y-4 p-6">
                        <div>
                            <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700">{{ __('shop.form_delivery_date') }}</label>
                            <input type="date" id="expected_delivery_date" name="expected_delivery_date"
                                   value="{{ old('expected_delivery_date') }}" min="{{ now()->addDay()->toDateString() }}" required
                                   class="mt-1 block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <x-input-error :messages="$errors->get('expected_delivery_date')" class="mt-1" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="delivery_street" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_street') }}</label>
                                <input type="text" id="delivery_street" name="delivery_street" x-model="street"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('delivery_street')" class="mt-1" />
                            </div>
                            <div>
                                <label for="delivery_postal_code" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_postal_code') }}</label>
                                <input type="text" id="delivery_postal_code" name="delivery_postal_code" x-model="postal"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-1" />
                            </div>
                            <div>
                                <label for="delivery_city" class="block text-sm font-medium text-gray-700">{{ __('shop.delivery_city') }}</label>
                                <input type="text" id="delivery_city" name="delivery_city" x-model="city"
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

                {{-- ── 3. Catalog lines ──────────────────────────────────── --}}
                <div class="mt-6 overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">3. {{ __('restaurant.admin_create_request_catalog_heading') }}</h2>
                            <p class="mt-1 text-xs text-gray-400">{{ __('restaurant.admin_create_request_catalog_hint') }}</p>
                        </div>
                        <button type="button" @click="addLine()" data-testid="admin-request-add-line"
                                class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-emerald-500 hover:text-emerald-700">
                            {{ __('restaurant.admin_create_request_add_line') }}
                        </button>
                    </div>
                    <div class="space-y-3 p-6">
                        <template x-for="(line, i) in lines" :key="'l'+i">
                            <div class="flex flex-wrap items-center gap-2">
                                <select x-model="line.key"
                                        class="min-w-[14rem] flex-1 rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">{{ __('restaurant.admin_create_request_product_placeholder') }}</option>
                                    <template x-for="o in options" :key="o.key">
                                        <option :value="o.key" x-text="o.label" :selected="line.key === o.key"></option>
                                    </template>
                                </select>
                                <input type="text" inputmode="decimal" x-model="line.qty" placeholder="{{ __('restaurant.special_qty') }}"
                                       class="w-20 rounded-md border-gray-300 text-center text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <span class="w-10 text-xs text-gray-500" x-text="unitSymbol(line)"></span>
                                <select x-model="line.recurrence" class="rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="one_time">{{ __('restaurant.recurrence_one_time') }}</option>
                                    <option value="weekly">{{ __('restaurant.recurrence_weekly') }}</option>
                                </select>
                                <label x-show="line.recurrence === 'weekly'" class="flex items-center gap-1 text-xs text-gray-500">
                                    {{ __('restaurant.recurrence_until') }}
                                    <input type="date" x-model="line.until" class="rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </label>
                                <button type="button" @click="lines.splice(i, 1)" class="px-2 text-gray-400 hover:text-red-600" aria-label="{{ __('restaurant.special_remove') }}">✕</button>
                            </div>
                        </template>
                        <p x-show="lines.length === 0" class="text-sm text-gray-400">{{ __('restaurant.admin_create_request_catalog_empty') }}</p>
                    </div>
                </div>

                {{-- ── 4. Free-form lines ────────────────────────────────── --}}
                <div class="mt-6 overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <div>
                            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">4. {{ __('restaurant.admin_create_request_special_heading') }}</h2>
                            <p class="mt-1 text-xs text-gray-400">{{ __('restaurant.admin_create_request_special_hint') }}</p>
                        </div>
                        <button type="button" @click="addSpecial()" data-testid="admin-request-add-special"
                                class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-emerald-500 hover:text-emerald-700">
                            {{ __('restaurant.admin_create_request_add_line') }}
                        </button>
                    </div>
                    <div class="space-y-3 p-6">
                        <template x-for="(s, j) in specials" :key="'s'+j">
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="text" x-model="s.name" maxlength="160" placeholder="{{ __('restaurant.special_name') }}"
                                       class="min-w-[12rem] flex-1 rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <input type="text" inputmode="decimal" x-model="s.quantity" placeholder="{{ __('restaurant.special_qty') }}"
                                       class="w-20 rounded-md border-gray-300 text-center text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <select x-model="s.unit" class="rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    @foreach (['piece', 'bunch', 'kg', 'g', 'l', 'ml'] as $u)
                                        <option value="{{ $u }}">{{ \App\Enums\ProductUnit::displaySymbol($u) }}</option>
                                    @endforeach
                                </select>
                                <select x-model="s.recurrence" class="rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="one_time">{{ __('restaurant.recurrence_one_time') }}</option>
                                    <option value="weekly">{{ __('restaurant.recurrence_weekly') }}</option>
                                </select>
                                <label x-show="s.recurrence === 'weekly'" class="flex items-center gap-1 text-xs text-gray-500">
                                    {{ __('restaurant.recurrence_until') }}
                                    <input type="date" x-model="s.until" class="rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </label>
                                <button type="button" @click="specials.splice(j, 1)" class="px-2 text-gray-400 hover:text-red-600" aria-label="{{ __('restaurant.special_remove') }}">✕</button>
                            </div>
                        </template>
                        <p x-show="specials.length === 0" class="text-sm text-gray-400">{{ __('restaurant.admin_create_request_special_empty') }}</p>
                    </div>
                </div>

                {{-- Hidden payload: only filled lines are posted. --}}
                <template x-for="(line, i) in filledLines" :key="'hl'+i">
                    <div>
                        <input type="hidden" :name="`items[${i}][product_id]`" :value="optionFor(line.key)?.product_id">
                        <input type="hidden" :name="`items[${i}][variant_id]`" :value="optionFor(line.key)?.variant_id ?? ''">
                        <input type="hidden" :name="`items[${i}][quantity]`" :value="canonicalQty(line)">
                        <input type="hidden" :name="`items[${i}][recurrence]`" :value="line.recurrence">
                        <input type="hidden" :name="`items[${i}][until]`" :value="line.recurrence === 'weekly' ? line.until : ''">
                    </div>
                </template>
                <template x-for="(s, j) in filledSpecials" :key="'hs'+j">
                    <div>
                        <input type="hidden" :name="`items[${filledLines.length + j}][name]`" :value="s.name">
                        <input type="hidden" :name="`items[${filledLines.length + j}][unit]`" :value="s.unit">
                        <input type="hidden" :name="`items[${filledLines.length + j}][quantity]`" :value="normQty(s.quantity)">
                        <input type="hidden" :name="`items[${filledLines.length + j}][recurrence]`" :value="s.recurrence">
                        <input type="hidden" :name="`items[${filledLines.length + j}][until]`" :value="s.recurrence === 'weekly' ? s.until : ''">
                    </div>
                </template>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="submit" data-testid="admin-request-submit"
                            :disabled="filledLines.length + filledSpecials.length === 0"
                            :class="{ 'cursor-not-allowed opacity-50': filledLines.length + filledSpecials.length === 0 }"
                            class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        {{ __('restaurant.admin_create_request_submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function adminRequestForm(config) {
            return {
                accountId: config.accountId || '',
                accounts: config.accounts || {},
                options: config.options || [],
                street: @js(old('delivery_street', '')),
                postal: @js(old('delivery_postal_code', '')),
                city: @js(old('delivery_city', '')),
                lines: [],
                specials: [],

                init() {
                    // Restore old input after a validation error; split posted
                    // items back into catalog and free-form rows.
                    for (const item of config.oldItems || []) {
                        if (item.product_id) {
                            const key = item.product_id + ':' + (item.variant_id || 0);
                            const factor = this.optionFor(key)?.factor || 1;
                            this.lines.push({
                                key,
                                qty: String((parseFloat(item.quantity) || 0) / factor),
                                recurrence: item.recurrence || 'one_time',
                                until: item.until || '',
                            });
                        } else if ((item.name || '').trim() !== '') {
                            this.specials.push({
                                name: item.name,
                                quantity: String(item.quantity || ''),
                                unit: item.unit || 'kg',
                                recurrence: item.recurrence || 'one_time',
                                until: item.until || '',
                            });
                        }
                    }
                    if (this.lines.length === 0 && this.specials.length === 0) {
                        this.addLine();
                    }
                },

                optionFor(key) {
                    return this.options.find((o) => o.key === key) || null;
                },
                unitSymbol(line) {
                    return this.optionFor(line.key)?.unit_symbol || '';
                },
                normQty(value) {
                    return parseFloat(String(value).replace(',', '.')) || 0;
                },
                canonicalQty(line) {
                    return this.normQty(line.qty) * (this.optionFor(line.key)?.factor || 1);
                },

                get filledLines() {
                    return this.lines.filter((l) => l.key && this.canonicalQty(l) > 0);
                },
                get filledSpecials() {
                    return this.specials.filter((s) => (s.name || '').trim() !== '' && this.normQty(s.quantity) > 0);
                },

                addLine() {
                    this.lines.push({ key: '', qty: '', recurrence: 'one_time', until: '' });
                },
                addSpecial() {
                    this.specials.push({ name: '', quantity: '', unit: 'kg', recurrence: 'one_time', until: '' });
                },

                applyAccount() {
                    const account = this.accounts[this.accountId];
                    if (!account) {
                        return;
                    }
                    this.street = account.street || '';
                    this.postal = account.postal || '';
                    this.city = account.city || '';
                },
            };
        }
    </script>
</x-admin-layout>
