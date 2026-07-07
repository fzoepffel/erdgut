<x-shop-layout :title="__('restaurant.requests_title')" restaurant-nav="requests">
    @php
        // Grade registry for the request basket: season rows (in window, not in
        // stock on the selected day). Amounts are free — nothing reserves here.
        $jsRows = [];
        foreach ($seasonRows as $row) {
            foreach ($row['grades'] as $grade) {
                $jsRows[] = [
                    'key' => $grade['key'],
                    'productId' => $row['product']->id,
                    'variantId' => $grade['variantId'],
                    'name' => $row['product']->name.($grade['label'] ? ' · '.$grade['label'] : ''),
                    'unitSymbol' => $grade['unitSymbol'],
                    'factor' => $grade['displayFactor'],
                    'step' => $grade['step'],
                    'net' => $grade['netPerDisplay'],
                ];
            }
        }
    @endphp

    <div
        x-data="restaurantRequests({
            rows: {{ Illuminate\Support\Js::from($jsRows) }},
            recurrenceWeeks: {{ Illuminate\Support\Js::from($recurrenceWeeks) }},
            weeklyLabel: @js(__('restaurant.recurrence_weekly')),
            untilLabel: @js(__('restaurant.recurrence_until')),
            openEndedLabel: @js(__('restaurant.recurrence_open_ended')),
            prefillProductId: {{ (int) $prefillProductId }},
            prefillQty: {{ (float) $prefillQty }},
            prefill: {{ Illuminate\Support\Js::from([
                'street' => $profile?->billing_street ?? '',
                'postal' => $profile?->billing_postal_code ?? '',
                'city' => $profile?->billing_city ?? '',
            ]) }},
        })"
    >
        <div class="mx-auto max-w-[1100px] px-4 py-10 sm:px-6 lg:px-12">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow text-amber-700">{{ __('restaurant.requests_eyebrow') }}</p>
                    <h1 class="mt-2 font-display text-3xl text-shop-foreground sm:text-4xl">{{ __('restaurant.requests_title') }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-shop-muted">{{ __('restaurant.requests_intro') }}</p>
                </div>
                @if ($selectableDates->isNotEmpty() && $selectedDate)
                    <form method="GET" action="{{ route('app.restaurant.requests.index') }}" class="flex flex-col gap-1">
                        <label for="date" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.catalog_date_label') }}</label>
                        <select id="date" name="date" onchange="this.form.submit()"
                                class="min-w-[14rem] border border-shop-border bg-shop-background py-2 pl-3 pr-8 text-shop-foreground focus:border-shop-primary focus:outline-none">
                            @foreach ($selectableDates as $row)
                                @php $iso = $row['date']->toDateString(); @endphp
                                <option value="{{ $iso }}" @selected($selectedDate->toDateString() === $iso)>
                                    {{ $row['date']->locale(app()->getLocale())->isoFormat('dd, D. MMM') }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>

            @if ($selectableDates->isEmpty())
                <div class="shop-card mt-8 p-8 text-center text-shop-muted">{{ __('restaurant.no_open_days') }}</div>
            @else
                {{-- ── My requests: the reader's own open/past requests first ── --}}
                @if ($myRequests->isNotEmpty())
                    <div class="mt-8">
                        <p class="eyebrow text-shop-primary">{{ __('restaurant.my_requests_list_title') }}</p>
                        <div class="mt-3 divide-y divide-shop-border border border-shop-border">
                            @foreach ($myRequests as $req)
                                <a href="{{ route('app.restaurant.orders.show', $req) }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-shop-card">
                                    <div>
                                        <p class="text-sm text-shop-foreground">{{ __('restaurant.request_ref', ['id' => $req->id]) }}</p>
                                        <p class="text-xs text-shop-muted">{{ $req->expected_delivery_date->translatedFormat('d.m.Y') }} · {{ $req->items_count }} {{ trans_choice('restaurant.cart_line_count', $req->items_count) }}</p>
                                    </div>
                                    <span class="rounded-full bg-shop-card px-2.5 py-0.5 text-[11px] font-medium text-shop-foreground">{{ __('restaurant.order_status_'.$req->status->value) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Two ways to ask: pick from the season list — or free-form. --}}
                <div class="mt-10 border-l-2 border-amber-400 bg-amber-50/60 px-4 py-3 text-sm text-shop-foreground" data-testid="requests-two-paths">
                    {{ __('restaurant.requests_two_paths') }}
                </div>

                {{-- ── Free-form wishes ────────────────────────────────────── --}}
                <div class="mt-10 shop-card p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-xl text-shop-foreground">{{ __('restaurant.special_title') }}</h2>
                            <p class="text-xs text-shop-muted">{{ __('restaurant.special_hint') }}</p>
                        </div>
                        <button type="button" @click="addSpecialRequest()" data-testid="restaurant-add-special"
                                class="shrink-0 border border-shop-border px-3 py-1.5 font-mono text-[0.7rem] uppercase tracking-widest text-shop-foreground hover:border-shop-primary">
                            {{ __('restaurant.special_add') }}
                        </button>
                    </div>
                    <div class="mt-4 space-y-3">
                        <template x-for="(s, i) in specialRequests" :key="i">
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="text" x-model="s.name" maxlength="160" placeholder="{{ __('restaurant.special_name') }}"
                                       class="min-w-[10rem] flex-1 border border-shop-border bg-shop-background px-3 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                <input type="text" inputmode="decimal" x-model="s.quantity" placeholder="{{ __('restaurant.special_qty') }}"
                                       class="w-20 border border-shop-border bg-shop-background px-2 py-2 text-center text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                <select x-model="s.unit" class="border border-shop-border bg-shop-background py-2 pl-2 pr-8 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                    @foreach (['piece', 'bunch', 'kg', 'g', 'l', 'ml'] as $u)
                                        <option value="{{ $u }}">{{ \App\Enums\ProductUnit::displaySymbol($u) }}</option>
                                    @endforeach
                                </select>
                                <select x-model="s.recurrence" class="border border-shop-border bg-shop-background py-2 pl-2 pr-8 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                    <option value="one_time">{{ __('restaurant.recurrence_one_time') }}</option>
                                    <option value="weekly">{{ __('restaurant.recurrence_weekly') }}</option>
                                </select>
                                <span x-show="s.recurrence === 'weekly'" class="whitespace-nowrap text-xs text-shop-muted">{{ $selectedDate?->translatedFormat('d.m.Y') }} &ndash;</span>
                                <select x-show="s.recurrence === 'weekly'" x-model="s.until"
                                        aria-label="{{ __('restaurant.recurrence_until') }}"
                                        class="border border-shop-border bg-shop-background py-2 pl-2 pr-8 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                    <option value="">{{ __('restaurant.recurrence_open_ended') }}</option>
                                    <template x-for="w in recurrenceWeeks" :key="w.iso">
                                        <option :value="w.iso" x-text="'KW ' + w.kw + ' · ' + w.label"></option>
                                    </template>
                                </select>
                                <button type="button" @click="removeSpecialRequest(i)" class="px-2 text-shop-muted hover:text-chapter-meat" aria-label="{{ __('restaurant.special_remove') }}">✕</button>
                            </div>
                        </template>
                        <p x-show="specialRequests.length === 0" class="text-sm text-shop-muted">{{ __('restaurant.special_empty') }}</p>
                    </div>
                </div>

                {{-- ── Path B: season products (in window, not in stock) ────── --}}
                <div class="mt-10">
                    <p class="eyebrow text-shop-primary">{{ __('restaurant.requests_season_title') }}</p>
                    <p class="mt-1 text-xs text-shop-muted">{{ __('restaurant.requests_season_hint') }}</p>
                    @if ($seasonRows->isEmpty())
                        <div class="shop-card mt-3 p-6 text-sm text-shop-muted" data-testid="requests-season-empty">{{ __('restaurant.tab_on_request_empty') }}</div>
                    @else
                        <div class="mt-3 divide-y divide-shop-border border border-shop-border" data-testid="requests-season-list">
                            @foreach ($seasonRows as $row)
                                @php $product = $row['product']; @endphp
                                @foreach ($row['grades'] as $grade)
                                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" data-testid="request-season-item" data-product-id="{{ $product->id }}">
                                        <div class="min-w-0">
                                            <p class="text-sm text-shop-foreground">{{ $product->name }}@if ($grade['label']) · {{ $grade['label'] }}@endif</p>
                                            <p class="text-xs text-shop-muted">{{ $product->producer?->name }} · @eur($grade['netPerDisplay']) {{ __('restaurant.net_suffix') }}/{{ $grade['unitSymbol'] }}</p>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                                            <div class="flex items-center gap-1">
                                                <button type="button" @click="dec(@js($grade['key']))" class="h-8 w-8 border border-shop-border text-shop-foreground hover:border-shop-primary" aria-label="−">−</button>
                                                <div class="relative h-8 w-24 border border-shop-border bg-shop-background">
                                                    <div class="pointer-events-none flex h-full w-full items-center justify-between gap-1 px-2 text-sm tabular-nums text-shop-foreground">
                                                        <span x-text="displayValue(@js($grade['key'])) || '–'"></span>
                                                        <svg class="h-3 w-3 shrink-0 text-shop-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                                                    </div>
                                                    <select x-data="{ optsOpen: false }" @pointerdown="optsOpen = true" @focus="optsOpen = true"
                                                            aria-label="{{ __('restaurant.special_qty') }}"
                                                            data-testid="request-qty-{{ $grade['key'] }}"
                                                            :value="String(quantities[@js($grade['key'])] || 0)"
                                                            @change="quantities[@js($grade['key'])] = +$event.target.value"
                                                            class="absolute inset-0 w-full cursor-pointer appearance-none border-0 bg-transparent opacity-0 focus:outline-none">
                                                        <option value="0">–</option>
                                                        <template x-for="o in (optsOpen ? qtyOptions(@js($grade['key'])) : [])" :key="o.v">
                                                            <option :value="String(o.v)" x-text="o.l" :selected="(quantities[@js($grade['key'])] || 0) === o.v"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <button type="button" @click="inc(@js($grade['key']))" class="h-8 w-8 border border-shop-border text-shop-foreground hover:border-shop-primary" aria-label="+">+</button>
                                                <span class="w-7 text-xs text-shop-muted">{{ $grade['unitSymbol'] }}</span>
                                            </div>
                                            <div x-show="(quantities[@js($grade['key'])] || 0) > 0" x-cloak class="flex items-center gap-1 text-xs text-shop-muted">
                                                <select x-model="recurrence[@js($grade['key'])]" class="border border-shop-border bg-shop-background py-1 pl-2 pr-7 text-xs text-shop-foreground focus:outline-none">
                                                    <option value="one_time">{{ __('restaurant.recurrence_one_time') }}</option>
                                                    <option value="weekly">{{ __('restaurant.recurrence_weekly') }}</option>
                                                </select>
                                                <span x-show="recurrence[@js($grade['key'])] === 'weekly'" class="whitespace-nowrap text-xs text-shop-muted">{{ $selectedDate?->translatedFormat('d.m.Y') }} &ndash;</span>
                                                <select x-show="recurrence[@js($grade['key'])] === 'weekly'" x-model="until[@js($grade['key'])]"
                                                        aria-label="{{ __('restaurant.recurrence_until') }}"
                                                        class="border border-shop-border bg-shop-background py-1 pl-2 pr-7 text-xs text-shop-foreground focus:outline-none">
                                                    <option value="">{{ __('restaurant.recurrence_open_ended') }}</option>
                                                    <template x-for="w in recurrenceWeeks" :key="w.iso">
                                                        <option :value="w.iso" x-text="'KW ' + w.kw + ' · ' + w.label"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @if (! $selectableDates->isEmpty())
            <div aria-hidden="true" class="h-24"></div>

            {{-- Sticky request bar --}}
            <div x-show="hasAnything" x-cloak
                 class="fixed inset-x-0 bottom-0 z-30 border-t border-amber-300 bg-amber-50/95 backdrop-blur">
                <div class="mx-auto flex max-w-[1100px] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-12">
                    <p class="text-sm text-amber-900">
                        <span x-text="totalCount"></span> <span x-text="totalCount === 1 ? @js(__('restaurant.cart_line')) : @js(__('restaurant.cart_lines'))"></span>
                        · {{ __('restaurant.request_bar_note') }}
                    </p>
                    <button type="button" @click="openSubmit = true" data-testid="restaurant-request-continue"
                            class="inline-flex h-[3rem] items-center justify-center border border-amber-700 bg-amber-700 px-6 font-mono text-[0.7rem] uppercase tracking-widest text-white transition hover:bg-amber-800">
                        {{ __('restaurant.request_continue') }}
                    </button>
                </div>
            </div>

            {{-- Submit panel --}}
            <div x-show="openSubmit" x-cloak class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 sm:items-center" @click.self="openSubmit = false">
                <form method="POST" action="{{ route('app.restaurant.requests.store') }}" class="max-h-[90vh] w-full max-w-lg overflow-y-auto bg-shop-background p-6 shadow-xl">
                    @csrf
                    <input type="hidden" name="expected_delivery_date" value="{{ $selectedDate?->toDateString() }}">
                    <template x-for="line in basketLines" :key="line.key">
                        <div>
                            <input type="hidden" :name="`items[${line.i}][product_id]`" :value="line.productId">
                            <input type="hidden" :name="`items[${line.i}][variant_id]`" :value="line.variantId ?? ''">
                            <input type="hidden" :name="`items[${line.i}][quantity]`" :value="line.quantity">
                            <input type="hidden" :name="`items[${line.i}][recurrence]`" :value="line.recurrence">
                            <input type="hidden" :name="`items[${line.i}][until]`" :value="line.until">
                        </div>
                    </template>
                    <template x-for="s in filledSpecials" :key="'s'+s.j">
                        <div>
                            <input type="hidden" :name="`items[${basketLines.length + s.j}][name]`" :value="s.name">
                            <input type="hidden" :name="`items[${basketLines.length + s.j}][unit]`" :value="s.unit">
                            <input type="hidden" :name="`items[${basketLines.length + s.j}][quantity]`" :value="s.quantity">
                            <input type="hidden" :name="`items[${basketLines.length + s.j}][recurrence]`" :value="s.recurrence">
                            <input type="hidden" :name="`items[${basketLines.length + s.j}][until]`" :value="s.until">
                        </div>
                    </template>

                    <h2 class="font-display text-2xl text-shop-foreground">{{ __('restaurant.request_review_title') }}</h2>
                    <p class="mt-1 text-sm text-shop-foreground"><span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.checkout_delivery_on') }}</span> {{ $selectedDate?->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') }}</p>
                    <p class="mt-1 text-sm text-shop-muted">{{ __('restaurant.request_review_intro') }}</p>

                    <ul class="mt-4 divide-y divide-shop-border text-sm">
                        <template x-for="line in basketLines" :key="'r'+line.key">
                            <li class="flex justify-between gap-3 py-2">
                                <span class="text-shop-foreground">
                                    <span x-text="`${line.displayQty} ${line.unitSymbol} · ${line.name}`"></span>
                                    <template x-if="line.recurrence === 'weekly'">
                                        <span class="ml-2 inline-block bg-shop-primary/10 px-1.5 py-0.5 font-mono text-[0.6rem] uppercase tracking-widest text-shop-primary" x-text="weeklyBadge(line.until)"></span>
                                    </template>
                                </span>
                                <span class="font-mono text-shop-muted" x-text="formatEur(line.net)"></span>
                            </li>
                        </template>
                        <template x-for="s in filledSpecials" :key="'rs'+s.j">
                            <li class="flex justify-between gap-3 py-2">
                                <span class="text-shop-foreground">
                                    <span x-text="`${s.quantity} ${s.unit} · ${s.name}`"></span>
                                    <template x-if="s.recurrence === 'weekly'">
                                        <span class="ml-1 inline-block bg-shop-primary/10 px-1.5 py-0.5 font-mono text-[0.6rem] uppercase tracking-widest text-shop-primary" x-text="weeklyBadge(s.until)"></span>
                                    </template>
                                </span>
                                <span class="font-mono text-shop-muted">{{ __('restaurant.price_on_request') }}</span>
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
                    @error('items')<p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>@enderror

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <button type="button" @click="openSubmit = false" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted hover:text-shop-primary">{{ __('restaurant.cart_back') }}</button>
                        <button type="submit" data-testid="restaurant-request-submit" class="inline-flex h-[3rem] items-center justify-center border border-amber-700 bg-amber-700 px-6 font-mono text-[0.7rem] uppercase tracking-widest text-white transition hover:bg-amber-800">
                            {{ __('restaurant.request_submit') }}
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script>
        function restaurantRequests(config) {
            return {
                rows: config.rows,
                quantities: {},
                recurrence: {},
                until: {},
                specialRequests: [],
                openSubmit: {{ $errors->any() ? 'true' : 'false' }},
                recurrenceWeeks: config.recurrenceWeeks || [],
                weeklyLabel: config.weeklyLabel || 'Wöchentlich',
                untilLabel: config.untilLabel || 'bis',
                openEndedLabel: config.openEndedLabel || 'Unbegrenzt',
                details: { street: config.prefill.street, postal: config.prefill.postal, city: config.prefill.city },
                init() {
                    // Shop handoff: preselect the product (its default grade) with
                    // the desired quantity, snapped to the grade's step.
                    const pid = config.prefillProductId || 0;
                    const qty = config.prefillQty || 0;
                    if (pid > 0) {
                        const r = this.rows.find((x) => x.productId === pid && x.variantId === null) || this.rows.find((x) => x.productId === pid);
                        if (r && qty > 0) {
                            this.quantities[r.key] = Math.max(r.step, Math.round(qty / r.step) * r.step);
                        }
                    }
                },
                weeklyBadge(until) {
                    let s = this.weeklyLabel;
                    if (until) {
                        const d = new Date(until + 'T00:00:00');
                        s += ' · ' + this.untilLabel + ' ' + d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
                    }
                    return s;
                },
                addSpecialRequest() { this.specialRequests.push({ name: '', unit: 'piece', quantity: '', recurrence: 'one_time', until: '' }); },
                removeSpecialRequest(i) { this.specialRequests.splice(i, 1); },
                get filledSpecials() {
                    return this.specialRequests
                        .map((s) => ({ name: (s.name || '').trim(), unit: s.unit, quantity: parseFloat(String(s.quantity).replace(',', '.')) || 0, recurrence: s.recurrence || 'one_time', until: s.recurrence === 'weekly' ? (s.until || '') : '' }))
                        .filter((s) => s.name !== '' && s.quantity > 0)
                        .map((s, j) => ({ ...s, j }));
                },
                rowFor(key) { return this.rows.find(r => r.key === key); },
                clamp(key, q) {
                    const r = this.rowFor(key);
                    if (!r) return 0;
                    q = Math.max(0, Math.round(q / r.step) * r.step);
                    const ceiling = 100 * r.factor; // requests run up to 100 display units
                    if (q > ceiling) q = ceiling;
                    return q;
                },
                // Request amounts: progressive list up to 100 display units.
                qtyOptions(key) {
                    const r = this.rowFor(key);
                    if (!r) return [];
                    const fmt = (q) => (q / r.factor).toLocaleString('de-DE', { maximumFractionDigits: 3 }) + ' ' + r.unitSymbol;
                    const values = new Set();
                    for (let v = r.step; v <= 10 * r.step; v += r.step) values.add(v);
                    const unit = Math.max(r.factor, r.step);
                    for (let v = unit; v <= 20 * r.factor; v += unit) values.add(v);
                    for (let v = 5 * r.factor; v <= 100 * r.factor; v += 5 * r.factor) values.add(v);
                    const out = [...values].sort((a, b) => a - b).slice(0, 300).map((v) => ({ v, l: fmt(v) }));
                    const cur = this.quantities[key] || 0;
                    if (cur > 0 && !out.some((o) => o.v === cur)) {
                        out.push({ v: cur, l: fmt(cur) });
                        out.sort((a, b) => a.v - b.v);
                    }
                    return out;
                },
                inc(key) { const r = this.rowFor(key); this.quantities[key] = this.clamp(key, (this.quantities[key] || 0) + r.step); },
                dec(key) { const r = this.rowFor(key); this.quantities[key] = this.clamp(key, (this.quantities[key] || 0) - r.step); },
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
                            const rec = this.recurrence[r.key] || 'one_time';
                            return {
                                i: i++, key: r.key, productId: r.productId, variantId: r.variantId,
                                quantity: q, name: r.name, unitSymbol: r.unitSymbol,
                                recurrence: rec, until: rec === 'weekly' ? (this.until[r.key] || '') : '',
                                displayQty: (q / r.factor).toLocaleString('de-DE', { maximumFractionDigits: 3 }),
                                net: Math.round((q / r.factor) * r.net * 100) / 100,
                            };
                        });
                },
                get totalCount() { return this.basketLines.length + this.filledSpecials.length; },
                get hasAnything() { return this.totalCount > 0; },
                formatEur(v) { return (v || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }); },
            };
        }
    </script>
</x-shop-layout>
