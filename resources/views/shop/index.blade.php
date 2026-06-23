<x-shop-layout nav-active="shop">
    @php
        // Mono eyebrow — prototype voice: JetBrains Mono, uppercase, 0.1em
        // tracking (Tailwind `tracking-widest`), primary green.
        $eyebrow = 'font-mono text-xs font-medium uppercase tracking-widest text-shop-primary';
        // Underline contact input — prototype style.
        $inputClass = 'mt-2 w-full border-b border-shop-border bg-transparent py-2 text-base text-shop-foreground placeholder:text-[oklch(45%_0.03_110_/_0.6)] focus:border-shop-primary focus:outline-none';
        // Form-field label. Mono micro-caps are too small to read here, so the
        // contact + address labels step up to the readable sans (Inter) voice.
        $fieldLabel = 'font-sans text-sm font-medium text-shop-foreground';

        // Build the Alpine product registry. Each orderable product carries
        // its precomputed `steps` (canonical value + server-formatted label)
        // so the [− value +] stepper can walk a fixed ladder, plus its unit
        // price for the live total. Greyed week-only products carry no
        // quantity field, so they never enter the cart.
        //
        // Grades (variants): a product with active grades emits one entry per
        // grade — the default/"Standard" grade (keyed by the product id, posting
        // quantities[id]) plus each named grade (keyed "v{id}", posting
        // variant_quantities[id]). Each grade has its own price and its own
        // remaining stock, so each gets its own stepper ladder. The card loop
        // below reads $gradeRowsByProduct to render one stepper per grade.
        $inventoryService = app(\App\Services\Admin\InventoryService::class);
        $priceResolver = app(\App\Services\Shop\PriceResolver::class);
        $locale = app()->getLocale();

        // Format a canonical (€/g) price in the product's display unit (€/kg).
        $formatCanonical = function ($product, float $canonical) use ($locale) {
            $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit();

            return $product->unit !== null && $displayUnit !== null
                ? $product->unit->formatPrice($canonical, $displayUnit, $locale)
                : number_format($canonical, 2, ',', '.').' €';
        };

        $buildSteps = function ($product, float $remainingRaw) {
            $step = $product->effectiveQuantityStep();
            $remaining = (int) floor($remainingRaw);
            $isCount = in_array($product->unit?->value, ['piece', 'bunch'], true);
            // Cap = real remaining stock (floored to a step boundary for
            // mass/volume so the last option is reachable).
            $cap = $isCount
                ? $remaining
                : (int) (floor($remaining / $step) * $step);

            // {@see \App\Support\QuantityLadder} keeps the option list bounded:
            // fine `step` increments in the everyday range, then progressively
            // coarser ones so a large lot doesn't produce a ~1000-entry <select>.
            $steps = [['v' => 0, 'l' => __('shop.quantity_none')]];
            foreach (\App\Support\QuantityLadder::values($step, $cap, $isCount) as $q) {
                $steps[] = ['v' => $q, 'l' => $product->displayQuantity($q)];
            }

            return ['step' => $step, 'cap' => $cap, 'steps' => $steps];
        };

        $gradeRowsByProduct = $products
            ->filter(fn ($row) => $row['available_on_selected'])
            ->mapWithKeys(function ($row) use ($buildSteps, $inventoryService, $priceResolver, $formatCanonical, $selectedDate, $locale) {
                $product = $row['product'];
                $activeVariants = $product->activeVariants();
                $entries = [];

                // Prices are resolved for the selected delivery date and a
                // private customer, so date overrides / grade prices show on the
                // card exactly as they'll be charged at checkout.
                $resolveFor = fn (?int $variantId, $variant = null) => $priceResolver
                    ->resolve($product, $selectedDate, \App\Enums\CustomerType::Private, null, $variantId, $variant);

                // Default/"Standard" grade. Its cap is the default grade's own
                // stock when named grades exist, otherwise the product total
                // (identical when there are no grades).
                $standardRemaining = $activeVariants->isEmpty()
                    ? (float) $row['remaining']
                    : $inventoryService->remainingForGrade($product, $selectedDate, null);
                $standardResolved = $resolveFor(null);
                $standardPrice = $standardResolved->unitPriceEur;
                // Whether volume tiers are in effect: a date override outranks a
                // tier, so when one is active the client must not re-apply a tier.
                $standardTiersApply = $standardResolved->reason !== 'period_override';
                // Volume tiers for the default grade: {min canonical qty, price
                // canonical}. Sorted ascending so the deepest applicable tier
                // wins client-side (mirrors the resolver). Date overrides /
                // customer-group rules are excluded — those aren't tiers.
                $tiers = $product->priceRules
                    ->filter(fn ($r) => $r->is_active && $r->min_quantity !== null
                        && $r->variant_id === null && $r->customer_type === null
                        && $r->starts_on === null && $r->ends_on === null)
                    ->sortBy(fn ($r) => (float) $r->min_quantity)
                    ->map(fn ($r) => ['min' => (float) $r->min_quantity, 'price' => (float) $r->unit_price])
                    ->values()
                    ->all();
                // Only show the default/"Standard" grade when it actually has
                // stock that day. A product with no named grades is always shown
                // (its card surfaced because the product total is positive).
                if ($activeVariants->isEmpty() || $standardRemaining > 0) {
                    $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit();
                    $displayFactor = $product->unit && $displayUnit
                        ? $product->unit->canonicalUnitsPerDisplay($displayUnit)
                        : 1;
                    $tiersHint = $tiers !== [] ? collect($tiers)
                        ->map(fn ($t) => __('shop.tier_from', [
                            'qty' => $product->displayQuantity($t['min']),
                            'price' => $formatCanonical($product, $t['price']),
                        ]))
                        ->implode(' · ') : null;
                    $entries[] = array_merge($buildSteps($product, $standardRemaining), [
                        'key' => (string) $product->id,
                        'jsId' => $product->id,
                        'productId' => $product->id,
                        'productName' => $product->name,
                        'producerName' => $product->producer?->name,
                        'unitKind' => $product->unit?->value,
                        'unitSymbol' => $product->canonicalUnitLabel(),
                        'displaySymbol' => $product->localizedUnit(),
                        'displayFactor' => $displayFactor,
                        'formName' => 'quantities['.$product->id.']',
                        'gradeLabel' => $activeVariants->isEmpty() ? null : __('shop.variant.standard'),
                        'name' => $product->name,
                        'unitPrice' => $standardPrice,
                        'vatRate' => (int) $product->vat_rate_percent,
                        'priceDisplay' => $formatCanonical($product, $standardPrice),
                        'tiers' => $tiers,
                        'tiersApply' => $standardTiersApply,
                        'tiersHint' => $tiersHint,
                    ]);
                }

                foreach ($activeVariants as $variant) {
                    $remaining = $inventoryService->remainingForGrade($product, $selectedDate, $variant->id);
                    // Skip grades with no stock on the selected day — no selector.
                    if ($remaining <= 0) {
                        continue;
                    }
                    $resolved = $resolveFor($variant->id, $variant);
                    $price = $resolved->unitPriceEur;
                    $tiersApply = $resolved->reason !== 'period_override';
                    // This grade's own volume tiers (default tiers don't leak to
                    // named grades, mirroring the resolver).
                    $variantTiers = $product->priceRules
                        ->filter(fn ($r) => $r->is_active && $r->min_quantity !== null
                            && (int) $r->variant_id === (int) $variant->id && $r->customer_type === null
                            && $r->starts_on === null && $r->ends_on === null)
                        ->sortBy(fn ($r) => (float) $r->min_quantity)
                        ->map(fn ($r) => ['min' => (float) $r->min_quantity, 'price' => (float) $r->unit_price])
                        ->values()
                        ->all();
                    $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit();
                    $displayFactor = $product->unit && $displayUnit
                        ? $product->unit->canonicalUnitsPerDisplay($displayUnit)
                        : 1;
                    $variantTiersHint = $variantTiers !== [] ? collect($variantTiers)
                        ->map(fn ($t) => __('shop.tier_from', [
                            'qty' => $product->displayQuantity($t['min']),
                            'price' => $formatCanonical($product, $t['price']),
                        ]))
                        ->implode(' · ') : null;
                    $entries[] = array_merge($buildSteps($product, $remaining), [
                        'key' => 'v'.$variant->id,
                        'jsId' => 'v'.$variant->id,
                        'productId' => $product->id,
                        'productName' => $product->name,
                        'producerName' => $product->producer?->name,
                        'unitKind' => $product->unit?->value,
                        'unitSymbol' => $product->canonicalUnitLabel(),
                        'displaySymbol' => $product->localizedUnit(),
                        'displayFactor' => $displayFactor,
                        'formName' => 'variant_quantities['.$variant->id.']',
                        'gradeLabel' => $variant->localizedName($locale),
                        'name' => $product->name.' · '.$variant->localizedName($locale),
                        'unitPrice' => $price,
                        'vatRate' => (int) $product->vat_rate_percent,
                        'priceDisplay' => $formatCanonical($product, $price),
                        'tiers' => $variantTiers,
                        'tiersApply' => $tiersApply,
                        'tiersHint' => $variantTiersHint,
                    ]);
                }

                return [$product->id => $entries];
            });

        // Flat registry handed to Alpine — every grade across every product.
        $alpineProducts = $gradeRowsByProduct
            ->flatten(1)
            ->map(fn ($e) => [
                'id' => $e['jsId'],
                'productId' => $e['productId'],
                'productName' => $e['productName'],
                'producerName' => $e['producerName'],
                'gradeLabel' => $e['gradeLabel'],
                'name' => $e['name'],
                'unitKind' => $e['unitKind'],
                'unitSymbol' => $e['unitSymbol'],
                'displaySymbol' => $e['displaySymbol'],
                'displayFactor' => $e['displayFactor'],
                'unitPrice' => $e['unitPrice'],
                'priceDisplay' => $e['priceDisplay'],
                'vatRate' => $e['vatRate'],
                'step' => $e['step'],
                'cap' => $e['cap'],
                'steps' => $e['steps'],
                'tiers' => $e['tiers'] ?? [],
                'tiersApply' => $e['tiersApply'] ?? true,
                'tiersHint' => $e['tiersHint'] ?? null,
            ])
            ->values();

        // Date-stamp in the hero corner: MONTH · YEAR · HOFLADEN.
        $stampDate = $selectedDate ?? now();
        $monthStamp = mb_strtoupper($stampDate->locale(app()->getLocale())->isoFormat('MMMM')).' · '.$stampDate->format('Y').' · HOFLADEN';

        // Selected-date summary for the picker button trigger line.
        $selectedRow = $selectedDate
            ? $availableDates->first(fn ($row) => $row['date']->toDateString() === $selectedDate->toDateString())
            : null;

        // Alpine date data: each date carries its label, KW, product count,
        // product-count label (pre-formatted for pluralisation), and product IDs.
        $alpineDates = $availableDates->map(fn ($row) => [
            'iso' => $row['date']->toDateString(),
            'label' => $row['date']->locale(app()->getLocale())->isoFormat('dddd, D. MMMM'),
            'shortLabel' => $row['date']->locale(app()->getLocale())->isoFormat('D. MMMM'),
            'kw' => $row['date']->isoWeek(),
            'productCount' => $row['product_count'],
            'productCountLabel' => trans_choice('shop.date_product_count', $row['product_count'], ['count' => $row['product_count']]),
            'productIds' => $row['product_ids'],
        ])->values();

        // Alpine product filter data: id, name, producer, date count.
        $alpineFilterProducts = $filterProducts->map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'producer' => $p['producer'],
            'dateCount' => $p['date_count'],
        ])->values();

        // On a validation bounce, the form may resubmit either the legacy
        // `quantities[]` / `variant_quantities[]` maps (kept for backwards
        // compat) OR the canonical `items[]` shape that the basket emits.
        // Build a lookup so the per-card hidden inputs can rehydrate from
        // either: keyed by "<productId>" for default grades, "v<variantId>"
        // for named grades — matching the Alpine jsId scheme.
        $oldItemQuantitiesByJsId = [];
        foreach ((array) old('items', []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $pid = (int) ($line['product_id'] ?? 0);
            $vid = isset($line['variant_id']) && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null;
            $qty = (float) ($line['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $key = $vid !== null ? 'v'.$vid : (string) $pid;
            $oldItemQuantitiesByJsId[$key] = $qty;
        }
        // Fallback: the customer is "editing" their basket from step 2/3 —
        // pull the items out of the persisted checkout draft so they see
        // their cart instead of an empty grid. old('items') (a validation
        // bounce) still wins.
        foreach ((array) ($draftItems ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $pid = (int) ($line['product_id'] ?? 0);
            $vid = isset($line['variant_id']) && $line['variant_id'] !== null && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null;
            $qty = (float) ($line['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $key = $vid !== null ? 'v'.$vid : (string) $pid;
            if (! isset($oldItemQuantitiesByJsId[$key])) {
                $oldItemQuantitiesByJsId[$key] = $qty;
            }
        }
    @endphp

    <div
        class="bg-shop-background text-shop-foreground"
        x-data="shopStorefront({
            products: @js($alpineProducts),
            deliveryFee: {{ (float) $deliveryFeeEur }},
            mode: {{ json_encode(old('fulfillment_mode', 'pickup')) }},
            payment: {{ json_encode(old('payment_method', 'online')) }},
            pickupLocation: {{ json_encode(old('pickup_location', '')) }},
            email: {{ json_encode(old('email', '')) }},
            couponPreviewUrl: @js(route('public.shop.coupon.preview')),
            quoteUrl: @js(route('public.shop.quote')),
            selectedDate: @js($selectedDate?->toDateString() ?? ''),
            numberSep: @js(\App\Support\Money::separators()),
            csrf: @js(csrf_token()),
            couponErrorGeneric: @js(__('coupon.errors.generic')),
            subcategoriesByCategory: @js($alpineSubcategories),
            defaultShopCategory: @js($defaultShopCategory),
            deliveryVatRate: {{ (int) config('shop.vat_rate_percent', 7) }},
            vatLineTemplate: @js(__('shop.summary_vat_rate_line')),
            trackUrl: @js(route('public.shop.engagement')),
            trackEnabled: {{ config('shop.ranking.enabled', true) ? 'true' : 'false' }},
            impressionThreshold: {{ (float) config('shop.ranking.impression_visibility', 0.5) }},
            beaconDebounce: {{ (int) config('shop.ranking.beacon_debounce_ms', 2000) }},
            pickupLocationNames: @js(collect($pickupLocations)->map(fn($loc) => $loc['name'])->all()),
            selectedDateLabel: @js($selectedDate?->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') ?? ''),
            confirmTitle: @js(__('shop.confirm_order_title')),
            confirmBody: @js(__('shop.confirm_order_body')),
            confirmPositionsLabel: @js(__('shop.confirm_order_positions')),
            confirmDateLabel: @js(__('shop.confirm_order_date')),
            confirmLocationLabel: @js(__('shop.confirm_order_location')),
            confirmTotalLabel: @js(__('shop.confirm_order_total')),
            confirmSubmitLabel: @js(__('shop.confirm_order_submit')),
            confirmCancelLabel: @js(__('shop.confirm_order_cancel')),
        })"
    >
        {{-- ── Hero ──────────────────────────────────────────────────── --}}
        <x-reveal as="section" class="relative border-b border-shop-border">
            <div class="absolute right-6 top-6 hidden font-mono text-xs tracking-widest text-shop-primary lg:block">
                {{ $monthStamp }}
            </div>
            <div class="mx-auto max-w-[1100px] px-6 py-16 lg:px-12 lg:py-24">
                <p class="{{ $eyebrow }}">{{ __('shop.eyebrow') }}</p>
                <h1 class="mt-6 text-balance text-5xl leading-[1.02] tracking-[-0.025em] md:text-7xl lg:text-[5.25rem]">
                    {{ __('shop.heading_main') }} <em class="font-normal italic text-shop-primary">{{ __('shop.heading_italic') }}</em>
                </h1>
                <p class="mt-8 max-w-xl text-base leading-relaxed text-shop-muted">
                    {{ __('shop.intro') }}
                </p>
            </div>
        </x-reveal>

        @if (session('error'))
            <div class="mx-auto max-w-[1100px] px-6 pt-8 lg:px-12">
                <div class="border border-shop-border bg-shop-paper p-4 text-sm leading-relaxed text-shop-foreground" role="alert">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        @if ($availableDates->isEmpty())
            {{-- Empty state: no admin stock anywhere in the horizon. --}}
            <div class="mx-auto max-w-[1100px] px-6 py-20 lg:px-12">
                <div class="border border-shop-border bg-shop-paper p-10 text-center" data-testid="shop-empty">
                    <p class="{{ $eyebrow }}">{{ __('shop.empty_eyebrow') }}</p>
                    <p class="mt-4 font-display text-3xl text-shop-foreground">{{ __('shop.empty_heading') }}</p>
                    <p class="mt-2 text-sm text-shop-muted">{{ __('shop.empty_body') }}</p>
                </div>
            </div>
        @else
            @include('shop._partials.checkout-progress', ['active' => 1])
            <form
                method="POST"
                action="{{ route('public.shop.checkout.start') }}"
                data-testid="shop-form"
            >
                @csrf
                <input type="hidden" name="expected_delivery_date" value="{{ $selectedDate?->toDateString() }}">
                {{-- Canonical cart payload driven by the basket. The backend
                     prefers `items[]` over the legacy `quantities[]` /
                     `variant_quantities[]` maps (see ShopOrderRequest), so
                     this is the single source of truth on submit. The
                     per-card hidden inputs remain in place to drive
                     old() rehydration on a validation bounce. --}}
                <template x-for="(line, i) in basket.lines" :key="line.jsId">
                    <div>
                        <input type="hidden" :name="`items[${i}][product_id]`" :value="line.productId">
                        <input type="hidden" :name="`items[${i}][variant_id]`" :value="line.variantId ?? ''">
                        <input type="hidden" :name="`items[${i}][quantity]`" :value="line.quantity">
                    </div>
                </template>

                {{-- ── Delivery-date picker + product filter ─────────── --}}
                <x-reveal as="section" class="relative z-10 border-b border-shop-border py-14" data-testid="shop-date-picker">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p class="{{ $eyebrow }}">{{ __('shop.date_picker_label') }}</p>
                            </div>
                            <p class="max-w-md text-sm leading-relaxed text-shop-muted">{{ __('shop.date_picker_intro') }}</p>
                        </header>

                        <div
                            class="max-w-xl space-y-8"
                            x-data="shopDateFilter({
                                allDates: @js($alpineDates),
                                filterProducts: @js($alpineFilterProducts),
                                selectedIso: @js($selectedDate?->toDateString() ?? ''),
                                activeProductIds: @js($activeProductIds),
                                shopUrl: @js(route('public.shop.index')),
                                availableSuffix: @js(__('shop.date_available_suffix')),
                                allProductsLabel: @js(__('shop.product_filter_all')),
                                noFilterLabel: @js(__('shop.product_filter_no_filter')),
                                notAvailableLabel: @js(__('shop.product_filter_not_available')),
                                unavailableTemplate: @js(__('shop.product_unavailable_on_date')),
                                unavailableComboOne: @js(__('shop.product_unavailable_combo_one')),
                                unavailableComboMany: @js(__('shop.product_unavailable_combo_many')),
                                noProductText: @js(__('shop.product_filter_no_product')),
                                noComboDateText: @js(__('shop.product_filter_no_combo_date')),
                                daysSuffix: @js(__('shop.product_filter_days_suffix')),
                                productsLabel: @js(__('shop.product_filter_products_label')),
                            })"
                        >
                            {{-- ── Date dropdown ──────────────────────────── --}}
                            <div class="relative" @keydown.escape="dateOpen = false" @click.outside="dateOpen = false">
                                <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.date_picker_label') }}</p>

                                <button
                                    type="button"
                                    @click="!noComboDate && (dateOpen = !dateOpen)"
                                    :disabled="noComboDate"
                                    class="mt-1 flex w-full items-end justify-between gap-6 border-b border-shop-foreground bg-transparent px-1 pb-3 pt-2 text-left transition"
                                    :class="noComboDate ? 'opacity-40 cursor-not-allowed' : 'hover:border-shop-primary'"
                                    data-testid="shop-date-button"
                                    aria-haspopup="listbox"
                                    :aria-expanded="dateOpen"
                                >
                                    <span class="flex flex-col gap-2">
                                        <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">
                                            KW {{ $selectedRow ? $selectedRow['date']->isoWeek() : '—' }} ·
                                            {{ $selectedRow ? trans_choice('shop.date_product_count', $selectedRow['product_count'], ['count' => $selectedRow['product_count']]) : '' }}
                                            {{ __('shop.date_available_suffix') }}
                                        </span>
                                        <span class="font-display text-3xl leading-tight md:text-4xl">
                                            {{ $selectedDate?->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}
                                        </span>
                                    </span>
                                    <svg viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2 h-3 w-3 shrink-0 text-shop-primary transition-transform" :class="dateOpen && 'rotate-180'" aria-hidden="true">
                                        <path d="M1 1.5 6 6.5 11 1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>

                                {{-- "Not available on selected date" notice --}}
                                <div
                                    x-show="productUnavailableOnDate"
                                    x-cloak
                                    class="mt-3 border-l-2 border-shop-primary bg-shop-paper px-4 py-3"
                                    data-testid="shop-product-unavailable-notice"
                                >
                                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                        <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-primary" x-text="notAvailableLabel"></span>
                                        <span class="text-sm text-shop-foreground" x-text="unavailableNoticeText"></span>
                                    </div>
                                    <div class="mt-2 text-right" x-show="nextAvailableDate">
                                        <button
                                            type="button"
                                            @click="selectDate(nextAvailableDate.iso)"
                                            class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-primary underline underline-offset-2 hover:opacity-75"
                                            x-text="'→ ' + (nextAvailableDate?.label ?? '')"
                                        ></button>
                                    </div>
                                </div>

                                {{-- Date popover --}}
                                <div
                                    x-show="dateOpen"
                                    x-transition.origin.top
                                    x-cloak
                                    class="absolute left-0 right-0 z-20 mt-2 max-h-96 overflow-y-auto border border-shop-border bg-shop-card shadow-[0_20px_40px_-20px_rgba(0,0,0,0.25)]"
                                    role="listbox"
                                    data-testid="shop-date-menu"
                                >
                                    <template x-for="group in datesByKw" :key="group.kw">
                                        <div>
                                            <p class="sticky top-0 bg-shop-paper px-5 py-2 font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted" x-text="'KW ' + group.kw"></p>
                                            <template x-for="d in group.dates" :key="d.iso">
                                                <button
                                                    type="button"
                                                    role="option"
                                                    :aria-selected="d.iso === selectedIso"
                                                    @click="selectDate(d.iso)"
                                                    class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left transition hover:bg-shop-paper"
                                                    :class="d.iso === selectedIso ? 'text-shop-primary bg-shop-paper' : 'text-shop-foreground'"
                                                >
                                                    <span class="flex items-center gap-2">
                                                        <span x-show="d.iso === selectedIso" class="text-shop-primary" aria-hidden="true">&#9679;</span>
                                                        <span class="font-display text-xl" x-text="d.label"></span>
                                                    </span>
                                                    <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted" x-text="d.productCountLabel + ' ' + availableSuffix"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    {{-- No dates: distinguish "no stock at all" from "no day has all selected products" --}}
                                    <p
                                        x-show="visibleDates.length === 0 && activeProductIds.length <= 1"
                                        x-cloak
                                        class="px-5 py-4 text-sm text-shop-muted"
                                    >{{ __('shop.no_products_for_date') }}</p>
                                    <p
                                        x-show="visibleDates.length === 0 && activeProductIds.length > 1"
                                        x-cloak
                                        class="px-5 py-4 text-sm text-shop-muted"
                                    >{{ __('shop.product_filter_no_combo_date') }}</p>
                                </div>
                            </div>

                            {{-- ── Product filter combobox ─────────────────── --}}
                            <div class="relative" @keydown.escape="closeProductDropdown()" @click.outside="closeProductDropdown()">
                                <div class="flex items-baseline justify-between">
                                    <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.product_filter_label') }}</p>
                                    <button
                                        type="button"
                                        x-show="activeProductIds.length > 0"
                                        x-cloak
                                        @click="clearProductFilter()"
                                        class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted underline underline-offset-2 hover:text-shop-primary"
                                        data-testid="shop-product-filter-reset"
                                    >{{ __('shop.product_filter_reset') }}</button>
                                </div>

                                {{-- Closed: show as button --}}
                                <button
                                    type="button"
                                    x-show="!productOpen"
                                    @click="productOpen = true; $nextTick(() => $refs.productInput?.focus())"
                                    class="mt-1 flex w-full items-end justify-between gap-6 border-b border-shop-foreground bg-transparent px-1 pb-3 pt-2 text-left transition hover:border-shop-primary"
                                    aria-haspopup="listbox"
                                    :aria-expanded="productOpen"
                                    data-testid="shop-product-filter-button"
                                >
                                    <span
                                        class="font-display text-2xl leading-tight md:text-3xl"
                                        :class="activeProductIds.length === 0 ? 'text-shop-muted' : 'text-shop-foreground'"
                                        x-text="triggerLabel"
                                    ></span>
                                    <svg viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2 h-3 w-3 shrink-0 text-shop-primary" aria-hidden="true">
                                        <path d="M1 1.5 6 6.5 11 1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>

                                {{-- Open: show as text input --}}
                                <input
                                    x-ref="productInput"
                                    x-show="productOpen"
                                    type="text"
                                    autocomplete="off"
                                    x-model="productQuery"
                                    :placeholder="triggerLabel"
                                    class="mt-1 w-full border-b border-shop-foreground bg-transparent px-1 pb-3 pt-2 font-display text-2xl leading-tight text-shop-foreground placeholder:text-shop-muted focus:border-shop-primary focus:outline-none md:text-3xl"
                                    data-testid="shop-product-filter-input"
                                />

                                {{-- Product popover — stays open for multi-select --}}
                                <div
                                    x-show="productOpen"
                                    x-transition.origin.top
                                    x-cloak
                                    class="absolute left-0 right-0 z-20 mt-2 max-h-96 overflow-y-auto border border-shop-border bg-shop-card shadow-[0_20px_40px_-20px_rgba(0,0,0,0.25)]"
                                    role="listbox"
                                    aria-multiselectable="true"
                                    data-testid="shop-product-filter-menu"
                                >
                                    {{-- "All products" fixed option — clears selection and closes --}}
                                    <button
                                        type="button"
                                        role="option"
                                        :aria-selected="activeProductIds.length === 0"
                                        @click="clearProductFilter()"
                                        class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left transition hover:bg-shop-paper"
                                        :class="activeProductIds.length === 0 ? 'bg-shop-paper' : ''"
                                    >
                                        <span class="flex items-center gap-2">
                                            <span x-show="activeProductIds.length === 0" class="text-shop-primary" aria-hidden="true">&#9679;</span>
                                            <span class="font-display text-xl" :class="activeProductIds.length === 0 ? 'text-shop-primary' : 'text-shop-foreground'" x-text="allProductsLabel"></span>
                                        </span>
                                        <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted" x-text="noFilterLabel"></span>
                                    </button>

                                    <template x-for="p in filteredProducts" :key="p.id">
                                        <button
                                            type="button"
                                            role="option"
                                            :aria-selected="activeProductIds.includes(p.id)"
                                            @click="toggleProduct(p.id)"
                                            class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left transition hover:bg-shop-paper"
                                            :class="activeProductIds.includes(p.id) ? 'bg-shop-paper' : ''"
                                        >
                                            <span class="flex items-start gap-2">
                                                <span x-show="activeProductIds.includes(p.id)" class="mt-1 shrink-0 text-shop-primary" aria-hidden="true">&#9679;</span>
                                                <span>
                                                    <span class="block font-display text-xl" :class="activeProductIds.includes(p.id) ? 'text-shop-primary' : 'text-shop-foreground'" x-text="p.name"></span>
                                                    <span class="block font-mono text-[0.55rem] uppercase tracking-widest text-shop-muted" x-text="p.producer"></span>
                                                </span>
                                            </span>
                                            <span class="shrink-0 font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted" x-text="p.dateCount + ' ' + daysSuffix"></span>
                                        </button>
                                    </template>

                                    <p x-show="filteredProducts.length === 0" x-cloak class="px-5 py-4 text-sm text-shop-muted" x-text="noProductText"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-reveal>

                {{-- ── Products ──────────────────────────────────────── --}}
                <x-reveal as="section" class="border-b border-shop-border py-14">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p class="{{ $eyebrow }}">{{ __('shop.section_products') }}</p>
                            </div>
                            <p class="max-w-md text-sm leading-relaxed text-shop-muted">{{ __('shop.weekly_intro') }}</p>
                        </header>

                        {{-- ── Product grid filters ──────────────────────── --}}
                        @if (! $products->isEmpty())
                            <div class="mb-8 space-y-4">
                                {{-- Name search --}}
                                <div>
                                    <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.grid_filter_search_label') }}</p>
                                    <input
                                        type="text"
                                        x-model="nameQuery"
                                        :placeholder="@js(__('shop.grid_filter_search_placeholder'))"
                                        autocomplete="off"
                                        class="mt-1 w-full border-b border-shop-foreground bg-transparent px-1 pb-3 pt-2 font-display text-2xl leading-tight text-shop-foreground placeholder:text-shop-muted focus:border-shop-primary focus:outline-none md:text-3xl"
                                        data-testid="shop-name-filter"
                                    />
                                </div>

                                {{-- Category chip rail --}}
                                @if (count($availableCategories) >= 1)
                                    <div>
                                        <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.grid_filter_category_label') }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button type="button"
                                                    @click="setCategory('')"
                                                    :class="activeCategory === '' ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                                    class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition">
                                                {{ __('shop.grid_filter_all') }}
                                            </button>
                                            @foreach ($availableCategories as $cat)
                                                <button type="button"
                                                        @click="setCategory('{{ $cat }}')"
                                                        :class="activeCategory === '{{ $cat }}' ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                                        class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition">
                                                    {{ __('admin.category.'.$cat) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Subcategory chip rail (appears when a category is active) --}}
                                <div x-show="activeCategory !== '' && availableSubcategories.length > 0" x-cloak>
                                    <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.grid_filter_subcategory_label') }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button type="button"
                                                @click="activeSubcategoryId = 0"
                                                :class="activeSubcategoryId === 0 ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                                class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition">
                                            {{ __('shop.grid_filter_all') }}
                                        </button>
                                        <template x-for="s in availableSubcategories" :key="s.id">
                                            <button type="button"
                                                    @click="activeSubcategoryId = s.id"
                                                    :class="activeSubcategoryId === s.id ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                                    class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition"
                                                    x-text="s.label">
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($products->isEmpty())
                            <div class="border border-shop-border bg-shop-paper p-6 text-sm text-shop-muted" data-testid="shop-no-products">
                                {{ __('shop.no_products_for_date') }}
                            </div>
                        @else
                            {{-- Conditional min-h on optional slots (producer, tags, tier
                                 hint): reserve space ONLY when at least one card on the
                                 page actually carries that content (via :has() below).
                                 A row with one product that has none of those collapses
                                 to zero — no dead gap. Structured slots stay aligned
                                 across heterogeneous rows where some cards do have content. --}}
                            <style>
                                .shop-cards-grid:has(.shop-card-producer-fill) .shop-card-producer-slot { min-height: 1rem; }
                                .shop-cards-grid:has(.shop-card-tags-fill) .shop-card-tags-slot { min-height: 1.5rem; }
                                .shop-cards-grid:has(.shop-card-tier-fill) .shop-card-tier-slot { min-height: 1rem; }
                            </style>
                            <div class="shop-cards-grid grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 items-start">
                                @foreach ($products as $row)
                                    @php
                                        $product = $row['product'];
                                        $availableToday = $row['available_on_selected'];
                                        $imageUrl = $product->thumbUrl();
                                        $badge = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
                                        $dateList = collect($row['available_dates'])
                                            ->map(fn ($d) => $d['date']->locale(app()->getLocale())->isoFormat('dd, D. MMM'))
                                            ->implode(' · ');
                                        $oldQty = (int) old('quantities.'.$product->id, 0);
                                    @endphp

                                    @php
                                        $shopTasteTags = $product->shopTasteTags();
                                        $gradeRows = $gradeRowsByProduct[$product->id] ?? [];
                                        $firstGrade = $gradeRows[0] ?? null;
                                        $extraGrades = array_slice($gradeRows, 1);
                                        // Unit descriptor (weight/volume per piece) — shown
                                        // alongside the tags slot when set, otherwise the tags
                                        // slot is just the tag chips.
                                        $unitDescriptor = $product->unit_weight_grams || $product->unit_volume_ml
                                            ? __('ca.').' '.($product->displayUnitWeight() ?? $product->displayUnitVolume()).' '.__('per').' '.$product->localizedUnit()
                                            : null;
                                    @endphp
                                    <div x-show="cardVisible({{ json_encode(mb_strtolower($product->name)) }}, '{{ $product->category }}', {{ $product->subcategory_id ?? 0 }})" data-shop-card data-product-id="{{ $product->id }}">
                                        <article class="group flex flex-col" data-testid="shop-product-card{{ $availableToday ? '' : '-unavailable' }}">
                                            {{-- Image --}}
                                            <a href="{{ route('public.shop.products.show', $product) }}" class="relative block aspect-square overflow-hidden bg-shop-paper {{ $availableToday ? '' : 'grayscale' }}">
                                                @if ($imageUrl)
                                                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}" width="600" height="600" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" loading="lazy" />
                                                @else
                                                    <span class="flex h-full w-full items-center justify-center text-shop-muted" aria-hidden="true">
                                                        <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
                                                    </span>
                                                @endif
                                                <span class="absolute left-3 top-3 bg-[oklch(96.5%_0.025_90_/_0.9)] px-2 py-1 font-mono text-[0.65rem] tracking-widest text-shop-primary">№ {{ $badge }}</span>
                                            </a>

                                            {{-- Title + divider --}}
                                            <h3 class="mt-4 font-display text-2xl leading-tight">
                                                <a href="{{ route('public.shop.products.show', $product) }}" class="underline-offset-4 hover:underline">{{ $product->name }}</a>
                                            </h3>
                                            <div class="mt-2 h-px bg-shop-border"></div>

                                            {{-- Producer (height reserved by the parent's :has rule
                                                 only when at least one card has a producer). --}}
                                            <div class="shop-card-producer-slot mt-3">
                                                @if ($product->producer)
                                                    <a href="{{ route('public.shop.producers.show', $product->producer) }}" class="shop-card-producer-fill font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">
                                                        {{ $product->producer->name }}
                                                    </a>
                                                @endif
                                            </div>

                                            {{-- Tags + unit descriptor (height reserved by the parent's
                                                 :has rule only when at least one card has tags or a unit
                                                 descriptor). --}}
                                            <div class="shop-card-tags-slot mt-2">
                                                @if ($shopTasteTags !== [])
                                                    <div class="shop-card-tags-fill flex flex-wrap gap-1" aria-label="{{ __('Taste profile tags') }}">
                                                        @foreach ($shopTasteTags as $tag)
                                                            <span class="inline-flex items-center border border-shop-border/80 px-1.5 py-px font-mono text-[0.55rem] uppercase tracking-wider text-shop-muted">{{ $tag }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if ($unitDescriptor)
                                                    <p class="shop-card-tags-fill mt-1 font-mono text-[0.65rem] text-shop-muted">{{ $unitDescriptor }}</p>
                                                @endif
                                            </div>

                                            @if ($availableToday && $firstGrade !== null)
                                                @php
                                                    // Cheapest grade drives the "ab" headline price; for a
                                                    // single grade it's just that grade's price.
                                                    $cheapestGrade = collect($gradeRows)->sortBy('unitPrice')->first() ?? $firstGrade;
                                                @endphp

                                                {{-- Headline price (cheapest grade; or just the only grade). --}}
                                                <p class="mt-4 font-mono text-sm tabular-nums text-shop-foreground">
                                                    @if (count($gradeRows) > 1)
                                                        {{ __('shop.price_from_label', ['price' => $cheapestGrade['priceDisplay']]) }}
                                                    @else
                                                        {{ $cheapestGrade['priceDisplay'] }}
                                                    @endif
                                                </p>

                                                {{-- Tier hint. Surfaces the Standard grade's own tier when it
                                                     has one; otherwise falls back to the first named variant
                                                     that does — with the variant's label as a small eyebrow
                                                     so it's clear which grade the discount applies to. --}}
                                                @php
                                                    $tieredGrade = ! empty($firstGrade['tiers'])
                                                        ? $firstGrade
                                                        : collect($gradeRows)->skip(1)->first(fn ($g) => ! empty($g['tiers']));
                                                @endphp
                                                <div class="shop-card-tier-slot mt-1">
                                                    @if ($tieredGrade !== null)
                                                        <p class="shop-card-tier-fill font-mono text-[0.6rem] leading-snug text-shop-muted">
                                                            @if ($tieredGrade !== $firstGrade)
                                                                <span class="font-mono uppercase tracking-wider text-shop-primary">{{ $tieredGrade['gradeLabel'] }}</span>
                                                            @endif
                                                            @foreach ($tieredGrade['tiers'] as $tier)
                                                                {{ __('shop.tier_from', ['qty' => $product->displayQuantity($tier['min']), 'price' => $formatCanonical($product, $tier['price'])]) }}@if (! $loop->last) · @endif
                                                            @endforeach
                                                        </p>
                                                    @endif
                                                </div>

                                                {{-- Hidden form inputs — one per grade — so the existing
                                                     POST /shop payload shape stays identical. They mirror
                                                     `quantities[jsId]` which the picker modal sets on confirm. --}}
                                                @foreach ($gradeRows as $entry)
                                                    @php
                                                        $jsId = $entry['jsId'];
                                                        $entryOld = (float) old(
                                                            str_replace(['[', ']'], ['.', ''], $entry['formName']),
                                                            $oldItemQuantitiesByJsId[(string) $jsId] ?? 0,
                                                        );
                                                    @endphp
                                                    <input type="hidden" id="shop-qty-{{ $jsId }}" name="{{ $entry['formName'] }}" value="{{ $entryOld }}" :value="quantities[@js($jsId)] || 0" data-testid="shop-qty-stepper">
                                                @endforeach

                                                @if (count($gradeRows) > 1)
                                                    {{-- Multi-variant: a short status line + the modal CTA so
                                                         the picker can show every variant at once. --}}
                                                    <p class="mt-4 font-mono text-xs tabular-nums text-shop-muted"
                                                       x-show="cartLabelFor({{ $product->id }})"
                                                       x-cloak>
                                                        <span x-text="cartLabelFor({{ $product->id }})"></span>
                                                    </p>
                                                    {{-- Same explicit height as the single-variant stepper on
                                                         the sister card so the two read as the same control
                                                         when shown side-by-side in a row. --}}
                                                    <button
                                                        type="button"
                                                        @click="openPicker({{ $product->id }})"
                                                        class="touch-manipulation mt-3 inline-flex h-[3.25rem] w-full items-center justify-center gap-2 border border-shop-primary bg-shop-primary px-5 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover"
                                                        data-testid="shop-card-pick-{{ $product->id }}"
                                                    >
                                                        <span x-show="!cartLabelFor({{ $product->id }})">{{ __('shop.card_cta_pick') }}</span>
                                                        <span x-show="cartLabelFor({{ $product->id }})" x-cloak>{{ __('shop.card_cta_edit') }}</span>
                                                    </button>
                                                @else
                                                    {{-- Single-variant: inline stepper-with-dropdown directly
                                                         on the card (no modal needed since there's nothing to
                                                         choose between). --}}
                                                    @php($singleJsId = $firstGrade['jsId'])
                                                    {{-- Stepper drives the staged value before the customer adds
                                                         to the basket; after that it edits the in-basket line
                                                         live (Amazon-style — no re-confirm). cardState() decides
                                                         which side of that line we're on. --}}
                                                    <div class="mt-3 flex h-[3.25rem] items-stretch border border-shop-border bg-shop-background" data-testid="shop-card-stepper-{{ $product->id }}">
                                                        <button
                                                            type="button"
                                                            @click="cardState({{ $product->id }}) === 'in_basket' ? dec(@js($singleJsId)) : stageDec({{ $product->id }}, @js($singleJsId))"
                                                            :disabled="cardState({{ $product->id }}) === 'in_basket' ? (quantities[@js($singleJsId)] || 0) <= 0 : stageQty({{ $product->id }}) <= 0"
                                                            class="touch-manipulation w-12 py-3 font-mono text-lg text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                                            aria-label="−"
                                                        >−</button>
                                                        <div class="relative flex flex-1 border-x border-shop-border bg-shop-paper">
                                                            <div class="pointer-events-none flex w-full items-center justify-between gap-3 px-3 py-3 font-mono text-sm tabular-nums">
                                                                <span x-text="cardState({{ $product->id }}) === 'in_basket' ? (label(@js($singleJsId)) || @js(__('shop.quantity_none'))) : (stagedLabel({{ $product->id }}, @js($singleJsId)) || @js(__('shop.quantity_none')))">{{ __('shop.quantity_none') }}</span>
                                                                <svg class="h-4 w-4 shrink-0 text-shop-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                                                </svg>
                                                            </div>
                                                            <select
                                                                aria-label="{{ __('shop.quantity_label') }}"
                                                                :value="cardState({{ $product->id }}) === 'in_basket' ? String(quantities[@js($singleJsId)] || 0) : String(stageQty({{ $product->id }}))"
                                                                @change="cardState({{ $product->id }}) === 'in_basket' ? (quantities[@js($singleJsId)] = +$event.target.value, +$event.target.value <= 0 ? removeFromBasketByProduct({{ $product->id }}) : (refreshQuote(), scheduleCouponRefresh())) : setStage({{ $product->id }}, +$event.target.value)"
                                                                class="touch-manipulation absolute inset-0 w-full cursor-pointer appearance-none border-0 bg-transparent font-mono opacity-0 focus:outline-none"
                                                            >
                                                                <option value="0">{{ __('shop.quantity_none') }}</option>
                                                                <template x-for="step in cardStepOptions(@js($singleJsId))" :key="step.v">
                                                                    <option :value="String(step.v)" x-text="step.l"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            @click="cardState({{ $product->id }}) === 'in_basket' ? inc(@js($singleJsId)) : stageInc({{ $product->id }}, @js($singleJsId))"
                                                            :disabled="cardState({{ $product->id }}) === 'in_basket' ? atCap(@js($singleJsId)) : stagedAtCap({{ $product->id }}, @js($singleJsId))"
                                                            class="touch-manipulation w-12 py-3 font-mono text-lg text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                                            aria-label="+"
                                                        >+</button>
                                                    </div>

                                                    {{-- Staged but not yet in basket → explicit Add-to-basket CTA
                                                         so the customer always confirms the first time. --}}
                                                    <button
                                                        type="button"
                                                        x-show="cardState({{ $product->id }}) === 'staged'"
                                                        x-cloak
                                                        @click="commitStage({{ $product->id }})"
                                                        class="touch-manipulation mt-3 inline-flex h-[3.25rem] w-full items-center justify-center gap-2 border border-shop-primary bg-shop-primary px-5 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover"
                                                        data-testid="shop-card-add-{{ $product->id }}"
                                                    >{{ __('shop.basket_add') }}</button>

                                                    {{-- Already in basket → status row with subtotal + remove. --}}
                                                    <div x-show="cardState({{ $product->id }}) === 'in_basket'"
                                                         x-cloak
                                                         class="mt-3 flex flex-wrap items-center justify-between gap-2 font-mono text-xs tabular-nums text-shop-muted">
                                                        <span>
                                                            <span x-text="label(@js($singleJsId))"></span>
                                                            <span class="text-shop-foreground/60">{{ __('shop.basket_in_cart_label') }}</span>
                                                            <span class="ml-1 text-shop-foreground" x-text="lineLabel(@js($singleJsId))"></span>
                                                        </span>
                                                        <button
                                                            type="button"
                                                            @click="removeFromBasketByProduct({{ $product->id }})"
                                                            class="touch-manipulation underline-offset-2 text-shop-primary hover:underline"
                                                            data-testid="shop-card-remove-{{ $product->id }}"
                                                        >{{ __('shop.basket_remove') }}</button>
                                                    </div>
                                                @endif
                                            @else
                                                {{-- Unavailable: dim placeholder + weekly availability note. --}}
                                                <div class="shop-card-tier-slot mt-1"></div>
                                                <div class="mt-4">
                                                    <p class="text-xs leading-relaxed text-shop-muted" data-testid="shop-weekly-note">
                                                        <span class="font-mono uppercase tracking-widest text-shop-primary">{{ __('shop.weekly_unavailable_label') }}</span><br>
                                                        {{ __('shop.weekly_available_on', ['dates' => $dateList]) }}
                                                    </p>
                                                </div>
                                            @endif
                                        </article>
                                    </div>{{-- /cardVisible wrapper --}}
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('items')" class="mt-4" />
                        @endif
                    </div>
                </x-reveal>

                {{-- ── Step 1 → 2 continue ───────────────────────────── --}}
                <x-reveal as="section" class="py-14" id="shop-checkout">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <div class="flex flex-wrap items-center gap-5">
                            <button
                                type="submit"
                                class="rounded-sm bg-shop-primary px-7 py-3 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="basket.itemCount === 0"
                                data-testid="shop-submit"
                            >{{ __('shop.checkout.start_button') }}</button>
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted"
                                  x-show="basket.itemCount === 0">
                                {{ __('shop.checkout.start_disabled_hint') }}
                            </span>
                        </div>
                    </div>
                </x-reveal>
            </form>

            @include('shop._partials.basket-chip')


            {{-- ── Variant + quantity picker modal ─────────────────────── --}}
            <div
                x-show="pickerOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="pickerTitleFor()"
                data-testid="shop-picker-modal"
            >
                {{-- Backdrop --}}
                <div class="absolute inset-0 bg-brand-ink/60 backdrop-blur-sm" @click="closePicker()"></div>

                {{-- Panel --}}
                <div class="relative w-full sm:max-w-xl max-h-[92vh] overflow-y-auto bg-shop-paper shadow-xl" data-testid="shop-picker-panel">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-primary">{{ __('shop.picker_eyebrow') }}</p>
                                <h2 class="mt-2 font-display text-3xl leading-tight text-shop-foreground" x-text="pickerTitleFor()"></h2>
                                <p class="mt-1 font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted"
                                   x-show="pickerProducerName()"
                                   x-cloak
                                   x-text="@js(__('shop.picker_producer_prefix')) + ' ' + pickerProducerName()"></p>
                            </div>
                            <button
                                type="button"
                                @click="closePicker()"
                                class="touch-manipulation -mt-2 -mr-2 p-2 font-mono text-xl text-shop-muted hover:text-shop-foreground"
                                aria-label="{{ __('shop.picker_close') }}"
                                data-testid="shop-picker-close-x"
                            >×</button>
                        </div>

                        <p class="mt-5 text-sm leading-relaxed text-shop-muted" x-text="pickerIntro()"></p>

                        {{-- Variant rows: each grade gets a −/value/+ stepper. The
                             middle value is clickable to open a dropdown that lists
                             every valid step value at once (one tap to jump to any
                             quantity instead of pressing + dozens of times). --}}
                        <div class="mt-6 space-y-6" data-testid="shop-picker-variants">
                            <template x-for="(v, idx) in pickerVariants" :key="v.id">
                                <div :class="idx > 0 ? 'pt-6 border-t border-shop-border' : ''">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary"
                                              x-text="v.gradeLabel || @js(__('shop.variant.standard'))"></span>
                                        <span class="font-mono text-sm tabular-nums text-shop-muted" x-text="v.priceDisplay"></span>
                                    </div>

                                    {{-- Stepper: −/value/+ — the middle "value" is an
                                         invisible native <select> overlay so tapping it
                                         shows the OS picker (iOS wheel / Android sheet)
                                         instead of a custom dropdown that fights for
                                         z-index and steals scroll. --}}
                                    <div class="mt-3 flex items-stretch border border-shop-border bg-shop-background" data-testid="shop-picker-stepper">
                                        <button
                                            type="button"
                                            @click="pickerDec(v.id)"
                                            :disabled="(pickerLocalQty[v.id] || 0) <= 0"
                                            class="touch-manipulation w-12 py-3 font-mono text-lg text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                            aria-label="−"
                                        >−</button>
                                        {{-- Middle area uses bg-shop-paper + a prominent chevron
                                             so it visually reads as a dropdown control distinct
                                             from the outline-only −/+ buttons next to it. --}}
                                        <div class="relative flex flex-1 border-x border-shop-border bg-shop-paper">
                                            <div class="pointer-events-none flex w-full items-center justify-between gap-3 px-3 py-3 font-mono text-sm tabular-nums">
                                                <span x-text="pickerCurrentLabel(v.id) || @js(__('shop.quantity_none'))"></span>
                                                <svg class="h-4 w-4 shrink-0 text-shop-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                                </svg>
                                            </div>
                                            <select
                                                :aria-label="@js(__('shop.quantity_label'))"
                                                :value="String(pickerLocalQty[v.id] || 0)"
                                                @change="pickerSet(v.id, +$event.target.value)"
                                                class="touch-manipulation absolute inset-0 w-full cursor-pointer appearance-none border-0 bg-transparent font-mono opacity-0 focus:outline-none"
                                                data-testid="shop-picker-step-open"
                                            >
                                                <option value="0">{{ __('shop.quantity_none') }}</option>
                                                <template x-for="step in pickerStepOptions(v)" :key="step.v">
                                                    <option :value="String(step.v)" x-text="step.l"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <button
                                            type="button"
                                            @click="pickerInc(v.id)"
                                            :disabled="pickerAtCap(v.id)"
                                            class="touch-manipulation w-12 py-3 font-mono text-lg text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                            aria-label="+"
                                        >+</button>
                                    </div>

                                    {{-- Live per-variant subtotal once a qty is picked. --}}
                                    <p class="mt-2 font-mono text-xs tabular-nums text-shop-muted"
                                       x-show="(pickerLocalQty[v.id] || 0) > 0"
                                       x-cloak>
                                        <span x-text="pickerLineLabelFor(v)"></span>
                                    </p>

                                    {{-- Volume tiers for this variant. --}}
                                    <p class="mt-1 font-mono text-[0.6rem] leading-snug text-shop-muted"
                                       x-show="v.tiers && v.tiers.length > 0"
                                       x-cloak
                                       x-text="pickerTierHintFor(v)"></p>
                                </div>
                            </template>
                        </div>

                        {{-- Cross-variant subtotal. --}}
                        <div class="mt-8 border-t border-shop-border pt-5 flex items-baseline justify-between gap-4">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.picker_subtotal') }}</span>
                            <span class="font-display text-2xl tabular-nums text-shop-foreground" x-text="money(pickerSubtotal)"></span>
                        </div>

                        {{-- Actions --}}
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                @click="confirmPicker()"
                                :disabled="pickerSubtotal <= 0"
                                class="touch-manipulation inline-flex items-center justify-center gap-2 bg-shop-primary px-6 py-3 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                                data-testid="shop-picker-confirm"
                            >
                                {{ __('shop.picker_confirm') }}
                            </button>
                            <button
                                type="button"
                                @click="closePicker()"
                                class="touch-manipulation font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:underline"
                                data-testid="shop-picker-cancel"
                            >{{ __('shop.picker_cancel') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Inline (not @stack): the isolated shop layout has no scripts stack.
         A plain inline script runs during parse, before the deferred Alpine
         module, so the function is global by the time Alpine evaluates
         x-data="shopStorefront(...)" / x-data="shopDateFilter(...)". --}}
    <script>
        function shopDateFilter(config) {
            return {
                allDates: config.allDates,
                filterProducts: config.filterProducts,
                selectedIso: config.selectedIso,
                activeProductIds: config.activeProductIds,
                shopUrl: config.shopUrl,
                // Localised strings
                availableSuffix: config.availableSuffix,
                allProductsLabel: config.allProductsLabel,
                noFilterLabel: config.noFilterLabel,
                notAvailableLabel: config.notAvailableLabel,
                unavailableTemplate: config.unavailableTemplate,
                unavailableComboOne: config.unavailableComboOne,
                unavailableComboMany: config.unavailableComboMany,
                noProductText: config.noProductText,
                noComboDateText: config.noComboDateText,
                daysSuffix: config.daysSuffix,
                productsLabel: config.productsLabel,
                // UI state
                dateOpen: false,
                productOpen: false,
                productQuery: '',

                // ── Computed helpers ───────────────────────────────────
                get visibleDates() {
                    if (this.activeProductIds.length === 0) return this.allDates;
                    // AND logic: only show dates where every selected product is available.
                    return this.allDates.filter(
                        (d) => this.activeProductIds.every((id) => d.productIds.includes(id)),
                    );
                },
                get datesByKw() {
                    const groups = [];
                    let cur = null;
                    for (const d of this.visibleDates) {
                        if (!cur || cur.kw !== d.kw) {
                            cur = { kw: d.kw, dates: [] };
                            groups.push(cur);
                        }
                        cur.dates.push(d);
                    }
                    return groups;
                },
                get selectedDateData() {
                    return this.allDates.find((d) => d.iso === this.selectedIso) || null;
                },
                get noComboDate() {
                    return this.activeProductIds.length > 1 && this.visibleDates.length === 0;
                },
                get productUnavailableOnDate() {
                    if (this.activeProductIds.length === 0) return false;
                    // No date has the full combo → always show the notice.
                    if (this.noComboDate) return true;
                    if (!this.selectedDateData) return false;
                    // True when the selected date is missing at least one of the chosen products.
                    return !this.activeProductIds.every((id) =>
                        this.selectedDateData.productIds.includes(id),
                    );
                },
                get nextAvailableDate() {
                    return this.visibleDates.length > 0 ? this.visibleDates[0] : null;
                },
                get triggerLabel() {
                    const n = this.activeProductIds.length;
                    if (n === 0) return this.allProductsLabel;
                    if (n === 1) {
                        const p = this.filterProducts.find((x) => x.id === this.activeProductIds[0]);
                        return p ? p.name : this.allProductsLabel;
                    }
                    return n + ' ' + this.productsLabel;
                },
                get filteredProducts() {
                    const q = this.productQuery.trim().toLowerCase();
                    if (!q) return this.filterProducts;
                    return this.filterProducts.filter(
                        (p) =>
                            p.name.toLowerCase().includes(q) ||
                            p.producer.toLowerCase().includes(q),
                    );
                },
                get unavailableNoticeText() {
                    const total = this.activeProductIds.length;
                    // No date has the full combo at all → surface the combo message directly.
                    if (this.noComboDate) return this.noComboDateText;
                    if (!this.selectedDateData) return '';
                    // Single product: keep the simple sentence with the full date label.
                    if (total <= 1) {
                        return this.unavailableTemplate.replace(':date', this.selectedDateData.label);
                    }
                    // Multi-select: report how many of the chosen products are missing.
                    const missing = this.activeProductIds.filter(
                        (id) => !this.selectedDateData.productIds.includes(id),
                    ).length;
                    const tpl = missing === 1 ? this.unavailableComboOne : this.unavailableComboMany;
                    return tpl
                        .replace(':count', missing)
                        .replace(':total', total)
                        .replace(':date', this.selectedDateData.shortLabel);
                },

                // ── Actions ────────────────────────────────────────────
                selectDate(iso) {
                    const url = new URL(this.shopUrl);
                    url.searchParams.set('date', iso);
                    for (const id of this.activeProductIds) {
                        url.searchParams.append('product[]', id);
                    }
                    window.location.href = url.toString();
                },
                // Toggle a product in/out of the selection; keep popover open.
                toggleProduct(id) {
                    const i = this.activeProductIds.indexOf(id);
                    if (i === -1) {
                        this.activeProductIds = [...this.activeProductIds, id];
                    } else {
                        this.activeProductIds = this.activeProductIds.filter((x) => x !== id);
                    }
                    this.productQuery = '';
                },
                clearProductFilter() {
                    this.activeProductIds = [];
                    this.productOpen = false;
                    this.productQuery = '';
                },
                // Close the product dropdown. The date is never changed
                // automatically — the notice offers a manual jump link instead.
                closeProductDropdown() {
                    this.productOpen = false;
                    this.productQuery = '';
                },
            };
        }

        function shopStorefront(config) {
            return {
                products: config.products,
                deliveryFee: config.deliveryFee,
                mode: config.mode || 'pickup',
                payment: config.payment || 'online',
                pickupLocation: config.pickupLocation || '',
                quantities: {},
                // Single-variant cards stage a candidate quantity before the
                // customer commits with "+ In den Korb". The map is keyed by
                // catalog product id (not the Alpine jsId), since each card
                // has at most one staged value pre-commit. Once committed,
                // the value moves into `quantities` and the stepper writes
                // there live (Amazon-style edit-after-add).
                _stagedByProduct: {},
                email: config.email || '',
                // ── Grid filters ───────────────────────────────────────
                nameQuery: '',
                activeCategory: config.defaultShopCategory || '',
                activeSubcategoryId: 0,
                subcategoriesByCategory: config.subcategoriesByCategory || {},
                deliveryVatRate: config.deliveryVatRate || 7,
                vatLineTemplate: config.vatLineTemplate || 'davon :rate% MwSt.: :amount',
                get availableSubcategories() {
                    return this.subcategoriesByCategory[this.activeCategory] ?? [];
                },
                setCategory(cat) {
                    this.activeCategory = this.activeCategory === cat ? '' : cat;
                    this.activeSubcategoryId = 0;
                },
                cardVisible(name, category, subcategoryId) {
                    if (this.nameQuery.trim() && !name.includes(this.nameQuery.trim().toLowerCase())) return false;
                    if (this.activeCategory && category !== this.activeCategory) return false;
                    if (this.activeSubcategoryId && subcategoryId !== this.activeSubcategoryId) return false;
                    return true;
                },
                // ── Ground-truth pricing ────────────────────────────────
                // The server quote is the authoritative source of money: when a
                // fresh quote is in hand it drives every figure shown, and it
                // creates the order at checkout. While a quote is being
                // re-fetched (after a cart change) we show an OPTIMISTIC
                // client-side estimate that mirrors the resolver (base/grade
                // price + the deepest applicable volume tier, unless a date
                // override is in effect) so the price updates instantly instead
                // of lagging the stepper. The estimate and the server value
                // agree, so reconciliation is seamless.
                quoteUrl: config.quoteUrl,
                selectedDate: config.selectedDate || '',
                numberSep: config.numberSep || ['.', ','],
                quote: null,
                quoteStale: true,
                _quoteTimer: null,
                _quoteSeq: 0,
                // Coupon state.
                couponPreviewUrl: config.couponPreviewUrl,
                csrf: config.csrf,
                couponErrorGeneric: config.couponErrorGeneric,
                couponCode: '',
                appliedCode: '',
                couponApplied: false,
                couponType: '',
                couponMessage: '',
                applyingCoupon: false,
                _couponTimer: null,
                // ── In-person confirmation modal ─────────────────────────
                showConfirmModal: false,
                confirmSubmitting: false,
                _confirmBypass: false,
                pickupLocationNames: config.pickupLocationNames || {},
                selectedDateLabel: config.selectedDateLabel || '',
                confirmTitle: config.confirmTitle,
                confirmBody: config.confirmBody,
                confirmPositionsLabel: config.confirmPositionsLabel,
                confirmDateLabel: config.confirmDateLabel,
                confirmLocationLabel: config.confirmLocationLabel,
                confirmTotalLabel: config.confirmTotalLabel,
                confirmSubmitLabel: config.confirmSubmitLabel,
                confirmCancelLabel: config.confirmCancelLabel,
                get confirmPickupName() {
                    return this.pickupLocationNames[this.pickupLocation] || this.pickupLocation;
                },
                // Cart positions for the review modal: name + display-unit
                // quantity (e.g. "1,5 kg") + €-line total. Empty when the
                // cart is empty — caller hides the section in that case.
                // Confirm-modal positions: the server quote when fresh, else the
                // instant client estimate (kept consistent with the cart).
                get cartLines() {
                    if (!this.quoteStale && this.quote?.lines) {
                        const lines = this.quote.lines;
                        return Object.keys(lines).map((id) => ({
                            id, name: lines[id].name, qty: lines[id].qty_label, lineTotalLabel: lines[id].line_total_label,
                        }));
                    }
                    return this.basket.lines.map((line) => {
                        const p = this.products.find((pp) => pp.id === line.jsId);
                        const name = line.gradeLabel
                            ? line.productName + ' · ' + line.gradeLabel
                            : (p?.name || line.productName);
                        return { id: line.jsId, name, qty: this.label(line.jsId), lineTotalLabel: this.money(this.lineEstimate(line.jsId)) };
                    });
                },
                // ── Basket: the single source of cart structure ───────────
                // Every read of "what's in the cart" — the confirm modal,
                // the mini-basket chip, the hidden items[] inputs, the live
                // /shop/quote payload — goes through this object. Writes
                // route through `set()` / `remove()` so the underlying
                // `quantities` map stays consistent (and the picker modal
                // commits without re-implementing the per-line projection).
                get basket() {
                    const self = this;
                    return {
                        get lines() {
                            const out = [];
                            self.products.forEach((v) => {
                                const qty = self.quantities[v.id] || 0;
                                if (qty <= 0) return;
                                const variantId = (typeof v.id === 'string' && v.id.startsWith('v'))
                                    ? parseInt(v.id.slice(1), 10)
                                    : null;
                                out.push({
                                    productId: v.productId,
                                    variantId,
                                    productName: v.productName,
                                    gradeLabel: v.gradeLabel || '',
                                    quantity: qty,
                                    jsId: v.id,
                                });
                            });
                            return out;
                        },
                        find(productId, variantId) {
                            return this.lines.find((l) => l.productId === productId && (l.variantId ?? null) === (variantId ?? null)) || null;
                        },
                        has(productId, variantId) {
                            return this.find(productId, variantId) !== null;
                        },
                        set(jsId, qty) {
                            self._setQuantity(jsId, Math.max(0, Number(qty) || 0));
                        },
                        remove(jsId) {
                            self._setQuantity(jsId, 0);
                        },
                        get itemCount() { return this.lines.length; },
                        get totalEstimate() { return self.itemsTotalEstimate(); },
                    };
                },
                // Single-variant card state machine:
                //   'empty'     — no staged value, nothing in basket
                //   'staged'    — staged > 0 but not yet committed
                //   'in_basket' — basket has a line for this product
                cardState(productId) {
                    const v = this.products.find((p) => p.productId === productId);
                    if (!v) return 'empty';
                    if ((this.quantities[v.id] || 0) > 0) return 'in_basket';
                    if ((this._stagedByProduct[productId] || 0) > 0) return 'staged';
                    return 'empty';
                },
                stageQty(productId) {
                    return this._stagedByProduct[productId] || 0;
                },
                stagedLabel(productId, jsId) {
                    const p = this.products.find((pp) => pp.id === jsId);
                    if (!p || !p.steps) return '';
                    const qty = this._stagedByProduct[productId] || 0;
                    const step = p.steps.find((s) => s.v === qty);
                    return step ? step.l : '';
                },
                stageInc(productId, jsId) {
                    const p = this.products.find((pp) => pp.id === jsId);
                    if (!p || !p.steps) return;
                    const current = this._stagedByProduct[productId] || 0;
                    let i = p.steps.findIndex((s) => s.v === current);
                    if (i < 0) i = p.steps.findIndex((s) => s.v > current) - 1;
                    if (i < p.steps.length - 1) {
                        this._stagedByProduct[productId] = p.steps[i + 1].v;
                    }
                },
                stageDec(productId, jsId) {
                    const p = this.products.find((pp) => pp.id === jsId);
                    if (!p || !p.steps) return;
                    const current = this._stagedByProduct[productId] || 0;
                    const i = p.steps.findIndex((s) => s.v === current);
                    if (i > 0) {
                        this._stagedByProduct[productId] = p.steps[i - 1].v;
                    } else if (i === 0) {
                        this._stagedByProduct[productId] = 0;
                    }
                },
                stagedAtCap(productId, jsId) {
                    const p = this.products.find((pp) => pp.id === jsId);
                    if (!p || !p.steps) return true;
                    const current = this._stagedByProduct[productId] || 0;
                    const i = p.steps.findIndex((s) => s.v === current);
                    return i >= p.steps.length - 1;
                },
                setStage(productId, qty) {
                    const v = this.products.find((p) => p.productId === productId);
                    if (!v) return;
                    const n = Math.max(0, Number(qty) || 0);
                    this._stagedByProduct[productId] = n;
                },
                commitStage(productId) {
                    const v = this.products.find((p) => p.productId === productId);
                    if (!v) return;
                    const n = this._stagedByProduct[productId] || 0;
                    if (n <= 0) return;
                    const wasEmpty = (this.quantities[v.id] || 0) <= 0;
                    this.quantities[v.id] = n;
                    this._stagedByProduct[productId] = 0;
                    if (wasEmpty && typeof v.id === 'number') this.queueEvent(v.id, 'add_to_cart');
                    this.refreshQuote();
                    this.scheduleCouponRefresh && this.scheduleCouponRefresh();
                    this._pulseChip();
                },
                removeFromBasketByProduct(productId) {
                    const v = this.products.find((p) => p.productId === productId);
                    if (!v) return;
                    this.quantities[v.id] = 0;
                    this._stagedByProduct[productId] = 0;
                    this.refreshQuote();
                    this.scheduleCouponRefresh && this.scheduleCouponRefresh();
                },
                // ── Mini-basket chip ──────────────────────────────────────
                chipOpen: false,
                chipPulse: false,
                _pulseChip() {
                    this.chipPulse = true;
                    setTimeout(() => { this.chipPulse = false; }, 700);
                },
                // ── Inline card stepper (single-variant products) ─────────
                // Step values feed the native <select> options under each
                // stepper — same source of truth as the +/− buttons.
                cardStepOptions(jsId) {
                    const v = this.products.find((p) => p.id === jsId);
                    if (!v || !v.steps) return [];
                    return v.steps.filter((s) => s.v > 0);
                },

                // ── Variant + quantity picker modal ────────────────────────
                pickerOpen: false,
                pickerProductId: null,
                pickerLocalQty: {},   // jsId -> canonical quantity, modal-local
                openPicker(productId) {
                    this.pickerProductId = productId;
                    const variants = this.products.filter((p) => p.productId === productId);
                    this.pickerLocalQty = {};
                    variants.forEach((v) => {
                        this.pickerLocalQty[v.id] = this.quantities[v.id] || 0;
                    });
                    this.pickerOpen = true;
                },
                closePicker() {
                    this.pickerOpen = false;
                    this.pickerProductId = null;
                    this.pickerLocalQty = {};
                },
                confirmPicker() {
                    // Iterate the picker's variants directly (their typed
                    // jsId — numeric for default-grade, "vNN" string for
                    // named grades). Object.entries(this.pickerLocalQty)
                    // would stringify every key, which is fine for storage
                    // but misses the typed-id round-trip with `add_to_cart`
                    // analytics below.
                    let added = false;
                    this.pickerVariants.forEach((v) => {
                        const n = Number(this.pickerLocalQty[v.id] || 0);
                        const wasEmpty = (this.quantities[v.id] || 0) <= 0;
                        this.basket.set(v.id, n);
                        if (wasEmpty && n > 0) {
                            added = true;
                            if (typeof v.id === 'number') this.queueEvent(v.id, 'add_to_cart');
                        }
                    });
                    this.refreshQuote();
                    if (added) this._pulseChip();
                    this.closePicker();
                },
                _setQuantity(jsId, qty) {
                    this.quantities[jsId] = qty;
                },
                get pickerVariants() {
                    if (this.pickerProductId === null) return [];
                    return this.products.filter((p) => p.productId === this.pickerProductId);
                },
                pickerTitleFor() {
                    const v = this.pickerVariants[0];
                    return v ? v.productName : '';
                },
                pickerProducerName() {
                    const v = this.pickerVariants[0];
                    return v ? (v.producerName || '') : '';
                },
                pickerIntro() {
                    const count = this.pickerVariants.length;
                    return count > 1
                        ? @js(__('shop.picker_intro_multi'))
                        : @js(__('shop.picker_intro_single'));
                },
                pickerSet(jsId, canonicalValue) {
                    const v = this.products.find((p) => p.id === jsId);
                    if (!v) return;
                    const clamped = Math.max(0, Math.min(v.cap, canonicalValue));
                    const snapped = v.step > 0 ? Math.round(clamped / v.step) * v.step : clamped;
                    this.pickerLocalQty[jsId] = snapped;
                },
                // Stepper +/− walk through the product's pre-computed steps array.
                pickerInc(jsId) {
                    const v = this.products.find((p) => p.id === jsId);
                    if (!v || !v.steps) return;
                    const current = this.pickerLocalQty[jsId] || 0;
                    let i = v.steps.findIndex((s) => s.v === current);
                    if (i < 0) {
                        // Off-step value (shouldn't normally happen) — snap up.
                        i = v.steps.findIndex((s) => s.v > current) - 1;
                    }
                    if (i < v.steps.length - 1) {
                        this.pickerLocalQty[jsId] = v.steps[i + 1].v;
                    }
                },
                pickerDec(jsId) {
                    const v = this.products.find((p) => p.id === jsId);
                    if (!v || !v.steps) return;
                    const current = this.pickerLocalQty[jsId] || 0;
                    const i = v.steps.findIndex((s) => s.v === current);
                    if (i > 0) {
                        this.pickerLocalQty[jsId] = v.steps[i - 1].v;
                    }
                },
                pickerAtCap(jsId) {
                    const v = this.products.find((p) => p.id === jsId);
                    if (!v || !v.steps) return true;
                    const current = this.pickerLocalQty[jsId] || 0;
                    const i = v.steps.findIndex((s) => s.v === current);
                    return i >= v.steps.length - 1;
                },
                pickerCurrentLabel(jsId) {
                    const v = this.products.find((p) => p.id === jsId);
                    if (!v || !v.steps) return '';
                    const current = this.pickerLocalQty[jsId] || 0;
                    const step = v.steps.find((s) => s.v === current);
                    return step ? step.l : '';
                },
                // Options for the click-to-open dropdown — all step values
                // (excluding 0, which is reachable via stepper down).
                pickerStepOptions(v) {
                    if (!v || !v.steps) return [];
                    return v.steps.filter((s) => s.v > 0);
                },
                // Fallback helpers for off-step values (the picker normally
                // only stores values present in the steps array, but
                // cartLabelFor and pickerLineLabelFor stay defensive in case
                // a quantity arrives from somewhere else).
                _canonicalToDisplay(v, canonical) {
                    const factor = v.displayFactor || 1;
                    const display = canonical / factor;
                    return Number.isInteger(display) ? String(display) : Number(display.toFixed(4)).toString();
                },
                pickerInputUnit(v) {
                    return v?.displaySymbol || v?.unitSymbol || '';
                },
                pickerLineLabelFor(v) {
                    const qty = this.pickerLocalQty[v.id] || 0;
                    if (qty <= 0) return '';
                    // Look up exact label from steps when possible; else fall back
                    // to "<display> <symbol>" formatting.
                    const match = v.steps?.find((s) => s.v === qty);
                    const qtyLabel = match ? match.l : (this._canonicalToDisplay(v, qty) + ' ' + this.pickerInputUnit(v));
                    const eff = this._effectiveUnitPriceFor(v, qty);
                    const line = Math.round(eff * qty * 100) / 100;
                    return qtyLabel + ' · ' + this.money(line);
                },
                // Apply the deepest active volume tier <= qty (mirrors the server
                // resolver) so the live line preview reflects the price the
                // customer would actually pay.
                _effectiveUnitPriceFor(v, qty) {
                    if (!v.tiersApply || !v.tiers || v.tiers.length === 0) return v.unitPrice;
                    let best = v.unitPrice;
                    for (const t of v.tiers) {
                        if (qty + 1e-9 >= t.min) best = t.price;
                    }
                    return best;
                },
                pickerTierHintFor(v) {
                    return v?.tiersHint || '';
                },
                get pickerSubtotal() {
                    // Iterate the variants directly so we keep their typed `id`
                    // (number for default grades, "vNN" string for named ones).
                    // Going via Object.entries(pickerLocalQty) stringifies every
                    // key, which makes the strict-equality lookup against the
                    // products array miss numeric ids → sum stays at 0 → Add to
                    // basket gets disabled even after the customer picked a
                    // quantity for one variant.
                    let sum = 0;
                    this.pickerVariants.forEach((v) => {
                        const qty = this.pickerLocalQty[v.id] || 0;
                        if (!qty) return;
                        const eff = this._effectiveUnitPriceFor(v, qty);
                        sum += eff * qty;
                    });
                    return Math.round(sum * 100) / 100;
                },
                // Card-level "what's currently in the cart for this product" line.
                // Returns "" when nothing is selected so x-show hides the row.
                cartLabelFor(productId) {
                    const variants = this.products.filter((p) => p.productId === productId);
                    const filled = variants.filter((v) => (this.quantities[v.id] || 0) > 0);
                    if (filled.length === 0) return '';
                    return filled
                        .map((v) => {
                            const qty = this.quantities[v.id];
                            const match = v.steps?.find((s) => s.v === qty);
                            const qtyLabel = match ? match.l : this._canonicalToDisplay(v, qty) + ' ' + this.pickerInputUnit(v);
                            return v.gradeLabel ? qtyLabel + ' ' + v.gradeLabel : qtyLabel;
                        })
                        .join(' · ') + ' ' + @js(__('shop.picker_in_cart_suffix'));
                },
                requestConfirm(event) {
                    // Only intercept in-person pickup; online/delivery submit normally.
                    if (this.payment !== 'in_person' || this.mode !== 'pickup') return;
                    // Second pass after the modal confirm button — let the
                    // browser submit (validation + POST) without re-opening.
                    if (this._confirmBypass) {
                        this._confirmBypass = false;
                        return;
                    }
                    event.preventDefault();
                    // Re-price immediately so the modal shows the current total.
                    this._fetchQuote();
                    this.showConfirmModal = true;
                },
                submitConfirmed() {
                    const form = this.$refs.shopForm;
                    if (!form) {
                        return;
                    }
                    if (!form.checkValidity()) {
                        this.showConfirmModal = false;
                        form.reportValidity();
                        return;
                    }
                    this.confirmSubmitting = true;
                    this._confirmBypass = true;
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        const btn = form.querySelector('[data-testid="shop-submit"]');
                        if (btn) {
                            btn.click();
                        } else {
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    }
                },
                // ── Engagement tracking (ranking bandit) ─────────────────
                trackUrl: config.trackUrl,
                trackEnabled: config.trackEnabled !== false,
                impressionThreshold: config.impressionThreshold || 0.5,
                beaconDebounce: config.beaconDebounce || 2000,
                _trackQueue: [],
                _seenImpressions: new Set(),
                _trackTimer: null,
                init() {
                    // Seed quantities from the rendered hidden inputs so a
                    // validation bounce (old() values) keeps the cart.
                    this.products.forEach((p) => {
                        const el = document.getElementById('shop-qty-' + p.id);
                        this.quantities[p.id] = el ? (parseFloat(el.value) || 0) : 0;
                    });
                    // Delivery is always paid online.
                    this.$watch('mode', (v) => {
                        if (v === 'delivery') this.payment = 'online';
                        // Mode changes the delivery fee → re-price server-side.
                        this.refreshQuote();
                    });
                    this.initEngagementTracking();
                    // Initial ground-truth prices (covers an old() cart bounce).
                    this.refreshQuote();
                },
                // Observe product cards; the first time one is ≥threshold visible
                // we queue an impression. Flush batched events via sendBeacon.
                initEngagementTracking() {
                    if (!this.trackEnabled || typeof IntersectionObserver === 'undefined') return;
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) return;
                            const id = parseInt(entry.target.getAttribute('data-product-id'), 10);
                            if (!id || this._seenImpressions.has(id)) return;
                            this._seenImpressions.add(id);
                            this.queueEvent(id, 'impression');
                            observer.unobserve(entry.target);
                        });
                    }, { threshold: this.impressionThreshold });
                    this.$nextTick(() => {
                        document.querySelectorAll('[data-shop-card][data-product-id]').forEach((el) => observer.observe(el));
                    });
                    // Make sure queued events leave before the page is hidden/unloaded.
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'hidden') this.flushTrack();
                    });
                    window.addEventListener('pagehide', () => this.flushTrack());
                },
                queueEvent(productId, type) {
                    if (!this.trackEnabled || !productId) return;
                    this._trackQueue.push({ product_id: productId, type });
                    clearTimeout(this._trackTimer);
                    this._trackTimer = setTimeout(() => this.flushTrack(), this.beaconDebounce);
                },
                flushTrack() {
                    if (this._trackQueue.length === 0) return;
                    const events = this._trackQueue.splice(0, this._trackQueue.length);
                    const payload = JSON.stringify({ events, _token: this.csrf });
                    try {
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(this.trackUrl, new Blob([payload], { type: 'application/json' }));
                        } else {
                            fetch(this.trackUrl, {
                                method: 'POST', keepalive: true,
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                                body: payload,
                            });
                        }
                    } catch (e) { /* tracking must never break the page */ }
                },
                // ── Ground-truth quote fetch ─────────────────────────────
                // Re-prices the whole cart server-side and stores the result.
                // Debounced so rapid +/- clicks coalesce; a sequence guard
                // drops out-of-order responses. Marks the figures stale so the
                // UI falls back to the instant optimistic estimate meanwhile.
                refreshQuote() {
                    this.quoteStale = true;
                    clearTimeout(this._quoteTimer);
                    this._quoteTimer = setTimeout(() => this._fetchQuote(), 200);
                },
                _fetchQuote() {
                    const seq = ++this._quoteSeq;
                    fetch(this.quoteUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify({
                            fulfillment_mode: this.mode,
                            expected_delivery_date: this.selectedDate,
                            email: this.email,
                            coupon_code: this.appliedCode,
                            quantities: this.defaultQuantities(),
                            variant_quantities: this.variantQuantities(),
                        }),
                    })
                        .then((r) => r.json())
                        .then((d) => {
                            if (seq !== this._quoteSeq) return; // stale response
                            this.quote = d;
                            this.quoteStale = false;
                            // Reconcile coupon state from the authoritative quote.
                            if (this.appliedCode !== '') {
                                this.couponApplied = d.coupon.applied;
                                this.couponType = d.coupon.type || '';
                                if (d.coupon.applied) {
                                    this.couponMessage = '';
                                } else {
                                    this.couponMessage = d.coupon.message || this.couponErrorGeneric;
                                    this.appliedCode = '';
                                }
                            }
                        })
                        .catch(() => { /* keep the last good quote on a transient error */ });
                },
                // Split the flat quantities map (keys: product id or "v{id}")
                // into the two maps the server expects.
                defaultQuantities() {
                    const out = {};
                    Object.keys(this.quantities).forEach((id) => {
                        if (!id.startsWith('v')) {
                            const qty = this.quantities[id] || 0;
                            if (qty > 0) out[id] = qty;
                        }
                    });
                    return out;
                },
                variantQuantities() {
                    const out = {};
                    Object.keys(this.quantities).forEach((id) => {
                        if (id.startsWith('v')) {
                            const qty = this.quantities[id] || 0;
                            if (qty > 0) out[id.slice(1)] = qty;
                        }
                    });
                    return out;
                },
                // ── Coupon ───────────────────────────────────────────────
                applyCoupon() {
                    const code = this.couponCode.trim();
                    if (code === '' || this.applyingCoupon) return;
                    this.appliedCode = code;
                    this.couponMessage = '';
                    this.refreshQuote();
                },
                clearCoupon() {
                    this.couponApplied = false;
                    this.appliedCode = '';
                    this.couponType = '';
                    this.couponCode = '';
                    this.couponMessage = '';
                    this.refreshQuote();
                },
                product(id) { return this.products.find((p) => p.id === id); },
                stepIndex(id) {
                    const p = this.product(id);
                    if (!p) return 0;
                    const qty = this.quantities[id] || 0;
                    const i = p.steps.findIndex((s) => s.v === qty);
                    return i < 0 ? 0 : i;
                },
                label(id) {
                    const p = this.product(id);
                    if (!p) return '—';
                    return p.steps[this.stepIndex(id)].l;
                },
                atCap(id) {
                    const p = this.product(id);
                    if (!p) return true;
                    return this.stepIndex(id) >= p.steps.length - 1;
                },
                inc(id) {
                    const p = this.product(id);
                    if (!p) return;
                    const i = this.stepIndex(id);
                    if (i < p.steps.length - 1) {
                        const wasEmpty = (this.quantities[id] || 0) <= 0;
                        this.quantities[id] = p.steps[i + 1].v;
                        // First add of this product → an add-to-cart signal.
                        // Only product-level (numeric) ids feed the ranking
                        // bandit; grade entries ("v123") are skipped.
                        if (wasEmpty && typeof id === 'number') this.queueEvent(id, 'add_to_cart');
                        this.refreshQuote();
                    }
                },
                dec(id) {
                    const p = this.product(id);
                    if (!p) return;
                    const i = this.stepIndex(id);
                    if (i > 0) {
                        this.quantities[id] = p.steps[i - 1].v;
                        this.refreshQuote();
                    }
                },
                // ── Money: server value when fresh, else instant estimate ──
                money(v) {
                    const [dec, thou] = this.numberSep;
                    const fixed = (Math.round(v * 100) / 100).toFixed(2);
                    let [whole, frac] = fixed.split('.');
                    const neg = whole.startsWith('-');
                    if (neg) whole = whole.slice(1);
                    whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, thou);
                    return (neg ? '-' : '') + whole + dec + frac + ' €';
                },
                // Effective per-unit price for a quantity: the deepest volume
                // tier whose threshold is met (unless a date override is in
                // effect), else the base/grade price. Mirrors the resolver.
                unitPriceFor(p, qty) {
                    let price = p.unitPrice;
                    if (p.tiersApply !== false) {
                        for (const t of (p.tiers || [])) {
                            if (qty + 1e-9 >= t.min) price = t.price;
                        }
                    }
                    return price;
                },
                lineEstimate(id) {
                    const p = this.product(id);
                    if (!p) return 0;
                    return Math.round(this.unitPriceFor(p, this.quantities[id] || 0) * (this.quantities[id] || 0) * 100) / 100;
                },
                lineLabel(id) {
                    if (!this.quoteStale && this.quote?.lines?.[id]) return this.quote.lines[id].line_total_label;
                    const qty = this.quantities[id] || 0;
                    return qty > 0 ? this.money(this.lineEstimate(id)) : '';
                },
                itemsTotalEstimate() {
                    return Math.round(this.products.reduce((s, p) => s + this.lineEstimate(p.id), 0) * 100) / 100;
                },
                itemsTotalLabel() {
                    return (!this.quoteStale && this.quote) ? this.quote.items_total_label : this.money(this.itemsTotalEstimate());
                },
                totalLabel() {
                    if (!this.quoteStale && this.quote) return this.quote.total_label;
                    const discount = this.quote?.discount || 0;
                    const delivery = this.mode === 'delivery' ? this.deliveryFee : 0;
                    return this.money(Math.max(0, this.itemsTotalEstimate() - discount) + delivery);
                },
                get itemCount() {
                    return this.products.reduce((n, p) => n + ((this.quantities[p.id] || 0) > 0 ? 1 : 0), 0);
                },
            };
        }
    </script>
</x-shop-layout>
