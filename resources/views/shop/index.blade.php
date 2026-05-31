<x-shop-layout>
    @php
        $inputClass = 'mt-2 block w-full border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0';

        // Build the Alpine product registry: per-product canonical price,
        // step, remaining stock, and a localized display price. Quantities
        // live in canonical units; the cart total is computed client-side
        // and re-verified server-side on submit.
        $alpineProducts = $products->map(function ($row) use ($quantityStep) {
            $product = $row['product'];
            $isCount = in_array($product->unit?->value, ['piece', 'bunch'], true);
            $step = $isCount ? 1 : (int) $quantityStep;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'unitPrice' => (float) $product->unit_price,
                'step' => $step,
                'remaining' => (int) floor($row['remaining']),
                'isCount' => $isCount,
            ];
        })->values();
    @endphp

    <div
        class="mx-auto max-w-5xl px-4 py-12 sm:px-6 sm:py-16"
        x-data="shopStorefront({
            products: @js($alpineProducts),
            deliveryFee: {{ (float) $deliveryFeeEur }},
            mode: {{ json_encode(old('fulfillment_mode', 'pickup')) }},
            payment: {{ json_encode(old('payment_method', 'online')) }},
            pickupLocation: {{ json_encode(old('pickup_location', '')) }},
        })"
    >
        {{-- Editorial hero --}}
        <header class="mb-10 sm:mb-12">
            <p class="eyebrow text-chapter-produce">{{ __('shop.eyebrow') }}</p>
            <h1 class="mt-3 max-w-2xl font-display text-4xl font-normal leading-[0.95] text-brand-ink sm:text-5xl">
                {{ __('shop.heading_main') }} <span class="italic">{{ __('shop.heading_italic') }}</span>
            </h1>
            <p class="mt-4 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75 sm:text-lg">
                {{ __('shop.intro') }}
            </p>
        </header>

        @if (session('error'))
            <div class="mb-8 border border-chapter-meat/40 bg-chapter-meat/[0.06] p-4 text-sm leading-relaxed text-brand-ink" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if ($availableDates->isEmpty())
            {{-- Empty state: no admin stock anywhere in the horizon. --}}
            <div class="border border-brand-ink/15 bg-brand-paper p-8 text-center" data-testid="shop-empty">
                <p class="eyebrow text-chapter-produce">{{ __('shop.empty_eyebrow') }}</p>
                <p class="mt-3 font-display text-2xl text-brand-ink">{{ __('shop.empty_heading') }}</p>
                <p class="mt-2 text-sm text-brand-ink/70">{{ __('shop.empty_body') }}</p>
            </div>
        @else
            {{-- Delivery-date picker. Server-rendered: changing the date
                 reloads the page with ?date= so the product grid below
                 reflects exactly what's in stock that day. --}}
            <section class="mb-10" aria-labelledby="shop-date-heading" data-testid="shop-date-picker">
                <h2 id="shop-date-heading" class="eyebrow text-chapter-produce">{{ __('shop.date_picker_label') }}</h2>
                <p class="mt-2 text-sm text-brand-ink/70">{{ __('shop.date_picker_intro') }}</p>
                <select
                    class="mt-3 block w-full border border-brand-ink/30 bg-white py-2.5 pl-3 pr-8 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0 sm:w-auto"
                    data-testid="shop-date-select"
                    onchange="window.location = '{{ route('public.shop.index') }}?date=' + encodeURIComponent(this.value)"
                >
                    @foreach ($availableDates as $row)
                        @php $iso = $row['date']->toDateString(); @endphp
                        <option value="{{ $iso }}" @selected($selectedDate && $selectedDate->toDateString() === $iso)>
                            {{ $row['date']->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}
                            · {{ trans_choice('shop.date_product_count', $row['product_count'], ['count' => $row['product_count']]) }}
                        </option>
                    @endforeach
                </select>
            </section>

            <form
                method="POST"
                action="{{ route('public.shop.store') }}"
                class="space-y-12"
                data-testid="shop-form"
            >
                @csrf
                <input type="hidden" name="expected_delivery_date" value="{{ $selectedDate?->toDateString() }}">

                {{-- Product grid for the selected date --}}
                <fieldset class="space-y-4">
                    <legend class="eyebrow text-chapter-produce">{{ __('shop.section_products') }}</legend>

                    @if ($products->isEmpty())
                        <div class="border border-brand-ink/15 bg-brand-paper p-6 text-sm text-brand-ink/70" data-testid="shop-no-products">
                            {{ __('shop.no_products_for_date') }}
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($products as $row)
                                @php
                                    $product = $row['product'];
                                    $remaining = (int) floor($row['remaining']);
                                    $isCount = in_array($product->unit?->value, ['piece', 'bunch'], true);
                                    $step = $isCount ? 1 : (int) $quantityStep;
                                    $imageUrl = $product->imageUrl();
                                @endphp
                                <div
                                    class="group relative flex flex-col overflow-hidden border bg-brand-cream transition"
                                    :class="(quantities[{{ $product->id }}] || 0) > 0
                                        ? 'border-chapter-produce ring-1 ring-chapter-produce'
                                        : 'border-brand-ink/15 hover:border-brand-ink/40'"
                                    data-testid="shop-product-card"
                                >
                                    <a href="{{ route('public.shop.products.show', $product) }}" class="block aspect-[5/3] w-full overflow-hidden bg-brand-ink/5" target="_blank" rel="noopener">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy" />
                                        @else
                                            <span class="flex h-full w-full items-center justify-center text-brand-ink/30" aria-hidden="true">
                                                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
                                            </span>
                                        @endif
                                    </a>
                                    <div class="flex flex-1 flex-col gap-2 p-5">
                                        <a href="{{ route('public.shop.products.show', $product) }}" class="font-display text-xl font-normal leading-tight text-brand-ink underline-offset-4 hover:underline" target="_blank" rel="noopener">
                                            {{ $product->name }}
                                        </a>
                                        @if ($product->producer)
                                            <a href="{{ route('public.shop.producers.show', $product->producer) }}" class="text-xs uppercase tracking-eyebrow text-chapter-produce underline-offset-4 hover:underline">
                                                {{ $product->producer->name }}
                                            </a>
                                        @endif
                                        <p class="text-sm tabular-nums text-brand-ink/70">{{ $product->displayPrice() }}</p>

                                        <div class="mt-auto pt-4">
                                            <label for="shop-qty-{{ $product->id }}" class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink/65">
                                                {{ __('shop.quantity_label') }}
                                            </label>
                                            <div class="mt-2 flex items-center gap-2">
                                                <input
                                                    id="shop-qty-{{ $product->id }}"
                                                    type="number"
                                                    name="quantities[{{ $product->id }}]"
                                                    inputmode="numeric"
                                                    min="0"
                                                    step="{{ $step }}"
                                                    max="{{ $remaining }}"
                                                    value="{{ (int) old('quantities.'.$product->id, 0) }}"
                                                    x-model.number="quantities[{{ $product->id }}]"
                                                    class="w-24 border border-brand-ink/30 bg-white px-3 py-2 text-sm text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                                                    data-testid="shop-qty-input"
                                                />
                                                <span class="text-xs text-brand-ink/55">
                                                    {{ $isCount ? '' : \App\Enums\ProductUnit::displaySymbol($product->unit->canonicalSymbol()) }}
                                                    · {{ __('shop.remaining_short', ['qty' => $product->displayQuantity($remaining)]) }}
                                                </span>
                                            </div>
                                            <p class="mt-2 text-xs tabular-nums text-brand-ink/60" x-show="(quantities[{{ $product->id }}] || 0) > 0" x-cloak>
                                                <span x-text="money(lineTotal({{ $product->id }}))"></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('items')" class="mt-2" />
                    @endif
                </fieldset>

                {{-- Fulfillment toggle --}}
                <fieldset class="space-y-3">
                    <legend class="eyebrow text-chapter-produce">{{ __('shop.section_fulfillment') }}</legend>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                            :class="mode === 'pickup' ? 'border-chapter-produce ring-1 ring-chapter-produce' : 'border-brand-ink/15 hover:border-brand-ink/40'"
                        >
                            <input type="radio" name="fulfillment_mode" value="pickup" x-model="mode" class="sr-only" @checked(old('fulfillment_mode', 'pickup') === 'pickup')>
                            <span class="flex flex-col gap-1">
                                <span class="eyebrow text-brand-ink/55">{{ __('shop.fulfillment_pickup_eyebrow') }}</span>
                                <span class="font-display text-xl font-normal">{{ __('shop.fulfillment_pickup') }}</span>
                                <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('shop.fulfillment_pickup_hint') }}</span>
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                            :class="mode === 'delivery' ? 'border-chapter-produce ring-1 ring-chapter-produce' : 'border-brand-ink/15 hover:border-brand-ink/40'"
                        >
                            <input type="radio" name="fulfillment_mode" value="delivery" x-model="mode" class="sr-only" @checked(old('fulfillment_mode') === 'delivery')>
                            <span class="flex flex-col gap-1">
                                <span class="eyebrow text-brand-ink/55">{{ __('shop.fulfillment_delivery_eyebrow') }}</span>
                                <span class="font-display text-xl font-normal">{{ __('shop.fulfillment_delivery') }}</span>
                                <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('shop.fulfillment_delivery_hint', ['fee' => \App\Support\Money::format($deliveryFeeEur)]) }}</span>
                            </span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-2" />
                </fieldset>

                {{-- Pickup location — pickup only --}}
                <fieldset class="space-y-3" x-show="mode === 'pickup'" x-cloak>
                    <legend class="eyebrow text-chapter-produce">{{ __('shop.pickup_location_section') }}</legend>
                    <p class="text-sm text-brand-ink/70">{{ __('shop.pickup_location_hint') }}</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($pickupLocations as $key => $loc)
                            <label
                                class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                                :class="pickupLocation === @js((string) $key) ? 'border-chapter-produce ring-1 ring-chapter-produce' : 'border-brand-ink/15 hover:border-brand-ink/40'"
                            >
                                <input type="radio" name="pickup_location" value="{{ $key }}" x-model="pickupLocation" class="sr-only" @checked(old('pickup_location') === $key)>
                                <span class="flex flex-col gap-1">
                                    <span class="eyebrow text-brand-ink/55">{{ __('shop.pickup_location_eyebrow', ['index' => $loop->iteration]) }}</span>
                                    <span class="font-display text-xl font-normal leading-snug">{{ $loc['street'] }}</span>
                                    <span class="text-xs leading-relaxed text-brand-ink/65">{{ $loc['postal_code'] }} {{ $loc['city'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('pickup_location')" class="mt-2" />
                </fieldset>

                {{-- Payment method — pickup only (delivery is always online) --}}
                <fieldset class="space-y-3" x-show="mode === 'pickup'" x-cloak>
                    <legend class="eyebrow text-chapter-produce">{{ __('shop.section_payment') }}</legend>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                            :class="payment === 'online' ? 'border-chapter-produce ring-1 ring-chapter-produce' : 'border-brand-ink/15 hover:border-brand-ink/40'"
                        >
                            <input type="radio" name="payment_method" value="online" x-model="payment" class="sr-only" @checked(old('payment_method', 'online') === 'online')>
                            <span class="flex flex-col gap-1">
                                <span class="eyebrow text-brand-ink/55">{{ __('shop.payment_online_eyebrow') }}</span>
                                <span class="font-display text-xl font-normal">{{ __('shop.payment_online') }}</span>
                                <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('shop.payment_online_hint') }}</span>
                            </span>
                        </label>
                        <label
                            class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                            :class="payment === 'in_person' ? 'border-chapter-produce ring-1 ring-chapter-produce' : 'border-brand-ink/15 hover:border-brand-ink/40'"
                        >
                            <input type="radio" name="payment_method" value="in_person" x-model="payment" class="sr-only" @checked(old('payment_method') === 'in_person')>
                            <span class="flex flex-col gap-1">
                                <span class="eyebrow text-brand-ink/55">{{ __('shop.payment_in_person_eyebrow') }}</span>
                                <span class="font-display text-xl font-normal">{{ __('shop.payment_in_person') }}</span>
                                <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('shop.payment_in_person_hint') }}</span>
                            </span>
                        </label>
                    </div>
                    {{-- Delivery forces online — keep a hidden value so the
                         radios above (which unmount client-side) don't drop
                         the field when delivery is selected. --}}
                    <template x-if="mode === 'delivery'">
                        <input type="hidden" name="payment_method" value="online">
                    </template>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                </fieldset>

                {{-- Contact --}}
                <fieldset class="space-y-4">
                    <legend class="eyebrow text-chapter-produce">{{ __('shop.section_contact') }}</legend>
                    <div>
                        <x-input-label for="shop-name" :value="__('Name')" />
                        <input id="shop-name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" class="{{ $inputClass }}" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="shop-email" :value="__('Email')" />
                            <input id="shop-email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="shop-phone">
                                {{ __('Phone') }}
                                <span class="ms-1 text-xs font-normal text-brand-ink/55" x-cloak>
                                    <span x-show="mode === 'delivery'">{{ __('required') }}</span>
                                    <span x-show="mode !== 'delivery'">({{ __('optional') }})</span>
                                </span>
                            </x-input-label>
                            <input id="shop-phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                    </div>
                </fieldset>

                {{-- Delivery address — delivery only --}}
                <fieldset class="space-y-4" x-show="mode === 'delivery'" x-cloak>
                    <legend class="eyebrow text-chapter-produce">{{ __('shop.section_address') }}</legend>
                    <div>
                        <x-input-label for="shop-street" :value="__('shop.delivery_street')" />
                        <input id="shop-street" type="text" name="delivery_street" value="{{ old('delivery_street') }}" autocomplete="street-address" class="{{ $inputClass }}" />
                        <x-input-error :messages="$errors->get('delivery_street')" class="mt-2" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                        <div>
                            <x-input-label for="shop-postal" :value="__('shop.delivery_postal_code')" />
                            <input id="shop-postal" type="text" name="delivery_postal_code" value="{{ old('delivery_postal_code') }}" autocomplete="postal-code" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="shop-city" :value="__('shop.delivery_city')" />
                            <input id="shop-city" type="text" name="delivery_city" value="{{ old('delivery_city') }}" autocomplete="address-level2" class="{{ $inputClass }}" />
                            <x-input-error :messages="$errors->get('delivery_city')" class="mt-2" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="shop-notes" :value="__('shop.delivery_notes')" optional />
                        <textarea id="shop-notes" name="delivery_notes" rows="2" class="{{ $inputClass }}">{{ old('delivery_notes') }}</textarea>
                        <x-input-error :messages="$errors->get('delivery_notes')" class="mt-2" />
                    </div>
                </fieldset>

                {{-- Live order summary --}}
                <div class="border border-brand-ink/15 bg-brand-paper p-6 text-sm text-brand-ink/85">
                    <p class="eyebrow text-chapter-produce">{{ __('shop.summary_heading') }}</p>
                    <dl class="mt-4 space-y-1.5">
                        <template x-for="p in products" :key="p.id">
                            <div class="flex items-center justify-between gap-3" x-show="(quantities[p.id] || 0) > 0" x-cloak>
                                <dt x-text="p.name"></dt>
                                <dd class="tabular-nums" x-text="money(lineTotal(p.id))"></dd>
                            </div>
                        </template>
                        <div class="flex items-center justify-between gap-3" x-show="mode === 'delivery'" x-cloak>
                            <dt>{{ __('shop.summary_line_delivery') }}</dt>
                            <dd class="tabular-nums" x-text="money(effectiveDeliveryFee)"></dd>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-brand-ink/10 pt-3 font-semibold text-brand-ink">
                            <dt class="text-base">{{ __('shop.summary_line_total') }}</dt>
                            <dd class="text-2xl tabular-nums text-brand-ink" x-text="money(total)"></dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-brand-ink/60">{{ __('shop.summary_vat_inclusive', ['rate' => $vatRatePercent]) }}</p>
                </div>

                {{-- Consent --}}
                <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-brand-ink/85">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce">
                    <span>
                        {!! __('shop.consent_text', ['privacy' => '<a href="'.route('public.privacy').'" class="underline-offset-4 hover:text-brand-ink hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_privacy_link')).'</a>', 'terms' => '<a href="'.route('public.terms').'" class="underline-offset-4 hover:text-brand-ink hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_terms_link')).'</a>']) !!}
                    </span>
                </label>
                <x-input-error :messages="$errors->get('consent')" class="-mt-8" />

                <div class="pt-2">
                    <button
                        type="submit"
                        class="btn-solid w-full justify-center disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                        :disabled="itemCount === 0"
                        data-testid="shop-submit"
                    >
                        <span x-show="mode === 'delivery' || payment === 'online'">{{ __('shop.cta_continue_to_payment') }}</span>
                        <span x-show="mode === 'pickup' && payment === 'in_person'" x-cloak>{{ __('shop.cta_place_order') }}</span>
                    </button>
                </div>
            </form>
        @endif
    </div>

    {{-- Inline (not @stack): the isolated shop layout has no scripts stack.
         A plain inline script runs during parse, before the deferred Alpine
         module, so the function is global by the time Alpine evaluates
         x-data="shopStorefront(...)". --}}
    <script>
        function shopStorefront(config) {
                return {
                    products: config.products,
                    deliveryFee: config.deliveryFee,
                    mode: config.mode || 'pickup',
                    payment: config.payment || 'online',
                    pickupLocation: config.pickupLocation || '',
                    quantities: {},
                    init() {
                        // Seed quantities from the rendered inputs so a
                        // validation bounce (old() values) keeps the cart.
                        this.products.forEach((p) => {
                            const el = document.getElementById('shop-qty-' + p.id);
                            this.quantities[p.id] = el ? (parseFloat(el.value) || 0) : 0;
                        });
                        // Delivery is always paid online.
                        this.$watch('mode', (v) => { if (v === 'delivery') this.payment = 'online'; });
                    },
                    product(id) { return this.products.find((p) => p.id === id); },
                    lineTotal(id) {
                        const p = this.product(id);
                        if (!p) return 0;
                        const qty = this.quantities[id] || 0;
                        return Math.round(p.unitPrice * qty * 100) / 100;
                    },
                    get itemsTotal() {
                        return Math.round(this.products.reduce((sum, p) => sum + this.lineTotal(p.id), 0) * 100) / 100;
                    },
                    get itemCount() {
                        return this.products.reduce((n, p) => n + ((this.quantities[p.id] || 0) > 0 ? 1 : 0), 0);
                    },
                    get effectiveDeliveryFee() { return this.mode === 'delivery' ? this.deliveryFee : 0; },
                    get total() { return Math.round((this.itemsTotal + this.effectiveDeliveryFee) * 100) / 100; },
                    money(v) { return v.toFixed(2).replace('.', ',') + ' €'; },
                };
            }
    </script>
</x-shop-layout>
