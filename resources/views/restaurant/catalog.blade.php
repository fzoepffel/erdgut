<x-shop-layout :title="__('restaurant.catalog_title')">
    @php
        // Flat JS registry of orderable grades for client-side basket maths.
        $jsRows = [];
        foreach ($products as $row) {
            foreach ($row['grades'] as $grade) {
                $jsRows[] = [
                    'key' => $grade['key'],
                    'productId' => $row['product']->id,
                    'variantId' => $grade['variantId'],
                    'name' => $row['product']->name.($grade['label'] ? ' · '.$grade['label'] : ''),
                    'unitSymbol' => $grade['unitSymbol'],
                    'factor' => $grade['displayFactor'],
                    'step' => $grade['step'],
                    'cap' => $grade['cap'], // null = no cap (on request)
                    'net' => $grade['netPerDisplay'],
                ];
            }
        }
    @endphp

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14"
         x-data="restaurantCatalog({
            rows: {{ Illuminate\Support\Js::from($jsRows) }},
            submitUrl: @js(route('app.restaurant.orders.store')),
            date: @js($selectedDate?->toDateString()),
            prefill: {{ Illuminate\Support\Js::from([
                'street' => $profile?->billing_street ?? '',
                'postal' => $profile?->billing_postal_code ?? '',
                'city' => $profile?->billing_city ?? '',
            ]) }},
            csrf: @js(csrf_token()),
         })">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-shop-primary">{{ __('restaurant.catalog_eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl text-shop-foreground sm:text-4xl">{{ __('restaurant.catalog_title') }}</h1>
            </div>
            <div class="flex items-end gap-4">
                @if ($selectableDates->isNotEmpty() && $selectedDate)
                    <form method="GET" action="{{ route('app.restaurant.catalog') }}" class="flex flex-col gap-1">
                        <label for="date" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.catalog_date_label') }}</label>
                        <select id="date" name="date" onchange="this.form.submit()"
                                class="min-w-[16rem] border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none focus:ring-1 focus:ring-shop-primary">
                            @foreach ($selectableDates as $row)
                                @php $iso = $row['date']->toDateString(); @endphp
                                <option value="{{ $iso }}" @selected($selectedDate->toDateString() === $iso)>
                                    {{ $row['date']->locale(app()->getLocale())->isoFormat('dd, D. MMM') }}@if ($row['cutoff_label']) · {{ __('restaurant.orderable_until', ['time' => $row['cutoff_label']]) }}@endif
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <a href="{{ route('app.restaurant.orders.index') }}" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.my_requests') }}</a>
            </div>
        </div>

        @if ($selectableDates->isEmpty())
            <div class="shop-card mt-10 p-8 text-center text-shop-muted">{{ __('restaurant.no_open_days') }}</div>
        @elseif ($products->isEmpty())
            <div class="shop-card mt-10 p-8 text-center text-shop-muted">{{ __('restaurant.catalog_empty') }}</div>
        @else
            <div class="mt-10 grid gap-4 pb-28 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($products as $row)
                    @php $product = $row['product']; @endphp
                    <div class="shop-card flex flex-col gap-3 p-5" data-testid="restaurant-catalog-item">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-display text-lg text-shop-foreground">{{ $product->name }}</h2>
                                <p class="text-xs text-shop-muted">{{ $product->producer?->name }}</p>
                            </div>
                            @if ($row['inStock'])
                                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">{{ __('restaurant.in_stock') }}</span>
                            @else
                                <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-800">{{ __('restaurant.on_request') }}</span>
                            @endif
                        </div>

                        <div class="mt-1 space-y-2">
                            @foreach ($row['grades'] as $grade)
                                <div class="flex items-center justify-between gap-3 border-t border-shop-border/60 pt-2 first:border-t-0 first:pt-0">
                                    <div class="min-w-0">
                                        @if ($grade['label'])<p class="truncate text-sm text-shop-foreground">{{ $grade['label'] }}</p>@endif
                                        <p class="font-mono text-sm text-shop-foreground">@eur($grade['netPerDisplay']) <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}/{{ $grade['unitSymbol'] }}</span></p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button" @click="dec(@js($grade['key']))" class="h-8 w-8 border border-shop-border text-shop-foreground hover:border-shop-primary">−</button>
                                        <input type="text" inputmode="decimal" :value="displayValue(@js($grade['key']))"
                                               @change="setFromDisplay(@js($grade['key']), $event.target.value)"
                                               class="w-20 border border-shop-border bg-shop-background px-2 py-1 text-center text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                        <button type="button" @click="inc(@js($grade['key']))" class="h-8 w-8 border border-shop-border text-shop-foreground hover:border-shop-primary">+</button>
                                        <span class="w-8 text-xs text-shop-muted">{{ $grade['unitSymbol'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Sticky basket bar --}}
            <div x-show="lineCount > 0" x-cloak
                 class="fixed inset-x-0 bottom-0 z-30 border-t border-shop-border bg-shop-background/95 backdrop-blur">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <p class="text-sm text-shop-foreground">
                        <span x-text="lineCount"></span> <span x-text="lineCount === 1 ? @js(__('restaurant.cart_line')) : @js(__('restaurant.cart_lines'))"></span>
                        · <span class="font-mono" x-text="formatEur(netTotal)"></span> <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span>
                    </p>
                    <button type="button" @click="openSubmit = true"
                            class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                        {{ __('restaurant.cart_continue') }}
                    </button>
                </div>
            </div>

            {{-- Submit panel --}}
            <div x-show="openSubmit" x-cloak class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 sm:items-center" @click.self="openSubmit = false">
                <form method="POST" action="{{ route('app.restaurant.orders.store') }}" class="max-h-[90vh] w-full max-w-lg overflow-y-auto bg-shop-background p-6 shadow-xl">
                    @csrf
                    <input type="hidden" name="expected_delivery_date" value="{{ $selectedDate?->toDateString() }}">
                    <template x-for="line in basketLines" :key="line.key">
                        <div>
                            <input type="hidden" :name="`items[${line.i}][product_id]`" :value="line.productId">
                            <input type="hidden" :name="`items[${line.i}][variant_id]`" :value="line.variantId ?? ''">
                            <input type="hidden" :name="`items[${line.i}][quantity]`" :value="line.quantity">
                        </div>
                    </template>

                    <h2 class="font-display text-2xl text-shop-foreground">{{ __('restaurant.cart_review_title') }}</h2>
                    <p class="mt-1 text-sm text-shop-muted">{{ __('restaurant.cart_review_intro') }}</p>

                    <ul class="mt-4 divide-y divide-shop-border text-sm">
                        <template x-for="line in basketLines" :key="'r'+line.key">
                            <li class="flex justify-between gap-3 py-2">
                                <span class="text-shop-foreground" x-text="`${line.displayQty} ${line.unitSymbol} · ${line.name}`"></span>
                                <span class="font-mono text-shop-muted" x-text="formatEur(line.net)"></span>
                            </li>
                        </template>
                    </ul>

                    <div class="mt-5 space-y-3">
                        <p class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.cart_delivery_address') }}</p>
                        <input name="delivery_street" x-model="details.street" required placeholder="{{ __('restaurant.field_billing_street') }}" class="block w-full border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none">
                        <div class="grid grid-cols-3 gap-3">
                            <input name="delivery_postal_code" x-model="details.postal" required placeholder="{{ __('restaurant.field_billing_postal_code') }}" class="border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none">
                            <input name="delivery_city" x-model="details.city" required placeholder="{{ __('restaurant.field_billing_city') }}" class="col-span-2 border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none">
                        </div>
                        <textarea name="delivery_notes" rows="2" placeholder="{{ __('restaurant.cart_notes') }}" class="block w-full border border-shop-border bg-shop-background px-3 py-2 text-shop-foreground focus:border-shop-primary focus:outline-none"></textarea>
                    </div>

                    @error('expected_delivery_date')<p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>@enderror

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <button type="button" @click="openSubmit = false" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted hover:text-shop-primary">{{ __('restaurant.cart_back') }}</button>
                        <button type="submit" class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                            {{ __('restaurant.cart_submit') }}
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script>
            function restaurantCatalog(config) {
                return {
                    rows: config.rows,
                    quantities: {},
                    openSubmit: false,
                    details: { street: config.prefill.street, postal: config.prefill.postal, city: config.prefill.city },
                    rowFor(key) { return this.rows.find(r => r.key === key); },
                    clamp(key, q) {
                        const r = this.rowFor(key);
                        if (!r) return 0;
                        q = Math.max(0, Math.round(q / r.step) * r.step);
                        if (r.cap !== null && q > r.cap) q = r.cap;
                        return q;
                    },
                    inc(key) { const r = this.rowFor(key); this.quantities[key] = this.clamp(key, (this.quantities[key] || 0) + r.step); },
                    dec(key) { const r = this.rowFor(key); this.quantities[key] = this.clamp(key, (this.quantities[key] || 0) - r.step); },
                    setFromDisplay(key, val) {
                        const r = this.rowFor(key);
                        const parsed = parseFloat(String(val).replace(',', '.')) || 0;
                        this.quantities[key] = this.clamp(key, parsed * r.factor);
                    },
                    displayValue(key) {
                        const r = this.rowFor(key);
                        const q = this.quantities[key] || 0;
                        if (q === 0) return '';
                        return (q / r.factor).toLocaleString('de-DE', { maximumFractionDigits: 3 });
                    },
                    get basketLines() {
                        let i = 0;
                        return this.rows
                            .filter(r => (this.quantities[r.key] || 0) > 0)
                            .map(r => {
                                const q = this.quantities[r.key];
                                return {
                                    i: i++, key: r.key, productId: r.productId, variantId: r.variantId,
                                    quantity: q, name: r.name, unitSymbol: r.unitSymbol,
                                    displayQty: (q / r.factor).toLocaleString('de-DE', { maximumFractionDigits: 3 }),
                                    net: Math.round((q / r.factor) * r.net * 100) / 100,
                                };
                            });
                    },
                    get lineCount() { return this.basketLines.length; },
                    get netTotal() { return this.basketLines.reduce((s, l) => s + l.net, 0); },
                    formatEur(v) { return (v || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }); },
                };
            }
    </script>
</x-shop-layout>
