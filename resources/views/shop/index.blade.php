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

        $buildSteps = function ($product, float $remainingRaw) use ($maxQuantity, $maxCount) {
            $step = $product->effectiveQuantityStep();
            $remaining = (int) floor($remainingRaw);
            $cap = in_array($product->unit?->value, ['piece', 'bunch'], true)
                ? min($remaining, (int) $maxCount)
                : min((int) (floor($remaining / $step) * $step), (int) $maxQuantity);

            $steps = [['v' => 0, 'l' => __('shop.quantity_none')]];
            for ($q = $step; $q <= $cap; $q += $step) {
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
                $resolveFor = fn (?int $variantId, $variant = null): float => $priceResolver
                    ->resolve($product, $selectedDate, \App\Enums\CustomerType::Private, null, $variantId, $variant)
                    ->unitPriceEur;

                // Default/"Standard" grade. Its cap is the default grade's own
                // stock when named grades exist, otherwise the product total
                // (identical when there are no grades).
                $standardRemaining = $activeVariants->isEmpty()
                    ? (float) $row['remaining']
                    : $inventoryService->remainingForGrade($product, $selectedDate, null);
                $standardPrice = $resolveFor(null);
                $entries[] = array_merge($buildSteps($product, $standardRemaining), [
                    'key' => (string) $product->id,
                    'jsId' => $product->id,
                    'formName' => 'quantities['.$product->id.']',
                    'gradeLabel' => $activeVariants->isEmpty() ? null : __('shop.variant.standard'),
                    'name' => $product->name,
                    'unitPrice' => $standardPrice,
                    'vatRate' => (int) $product->vat_rate_percent,
                    'priceDisplay' => $formatCanonical($product, $standardPrice),
                ]);

                foreach ($activeVariants as $variant) {
                    $remaining = $inventoryService->remainingForGrade($product, $selectedDate, $variant->id);
                    $price = $resolveFor($variant->id, $variant);
                    $entries[] = array_merge($buildSteps($product, $remaining), [
                        'key' => 'v'.$variant->id,
                        'jsId' => 'v'.$variant->id,
                        'formName' => 'variant_quantities['.$variant->id.']',
                        'gradeLabel' => $variant->localizedName($locale),
                        'name' => $product->name.' · '.$variant->localizedName($locale),
                        'unitPrice' => $price,
                        'vatRate' => (int) $product->vat_rate_percent,
                        'priceDisplay' => $formatCanonical($product, $price),
                    ]);
                }

                return [$product->id => $entries];
            });

        // Flat registry handed to Alpine — every grade across every product.
        $alpineProducts = $gradeRowsByProduct
            ->flatten(1)
            ->map(fn ($e) => [
                'id' => $e['jsId'],
                'name' => $e['name'],
                'unitPrice' => $e['unitPrice'],
                'vatRate' => $e['vatRate'],
                'step' => $e['step'],
                'cap' => $e['cap'],
                'steps' => $e['steps'],
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
            <form
                method="POST"
                action="{{ route('public.shop.store') }}"
                data-testid="shop-form"
                x-ref="shopForm"
                @submit="requestConfirm($event)"
            >
                @csrf
                <input type="hidden" name="expected_delivery_date" value="{{ $selectedDate?->toDateString() }}">

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
                            <div class="grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
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

                                    <div x-show="cardVisible({{ json_encode(mb_strtolower($product->name)) }}, '{{ $product->category }}', {{ $product->subcategory_id ?? 0 }})" data-shop-card data-product-id="{{ $product->id }}">
                                    @if ($availableToday)
                                        <article class="group flex h-full flex-col" data-testid="shop-product-card">
                                    @else
                                        <article class="group flex h-full flex-col" data-testid="shop-product-card-unavailable">
                                    @endif
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

                                        <h3 class="mt-4 font-display text-2xl leading-tight">
                                            <a href="{{ route('public.shop.products.show', $product) }}" class="underline-offset-4 hover:underline">{{ $product->name }}</a>
                                        </h3>
                                        <div class="mt-2 h-px bg-shop-border"></div>

                                        @if ($product->producer)
                                            <a href="{{ route('public.shop.producers.show', $product->producer) }}" class="mt-3 font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">
                                                {{ $product->producer->name }}
                                            </a>
                                        @endif
                                        {{-- Default-grade price resolved for the selected date (matches the Standard stepper). --}}
                                        <p class="mt-2 font-mono text-xs tabular-nums text-shop-muted">{{ $gradeRowsByProduct[$product->id][0]['priceDisplay'] ?? $product->displayPrice() }}</p>

                                        @php($shopTasteTags = $product->shopTasteTags())
                                        @if ($shopTasteTags !== [])
                                            <div class="mt-1.5 flex flex-wrap gap-1" aria-label="{{ __('Taste profile tags') }}">
                                                @foreach ($shopTasteTags as $tag)
                                                    <span class="inline-flex items-center border border-shop-border/80 px-1.5 py-px font-mono text-[0.55rem] uppercase tracking-wider text-shop-muted">{{ $tag }}</span>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Unit descriptor: weight/volume per piece or bunch --}}
                                        @if ($product->unit_weight_grams || $product->unit_volume_ml)
                                            <p class="mt-1 font-mono text-[0.65rem] text-shop-muted">
                                                {{ __('ca.') }}
                                                {{ $product->displayUnitWeight() ?? $product->displayUnitVolume() }}
                                                {{ __('per') }} {{ $product->localizedUnit() }}
                                            </p>
                                        @endif

                                        <div class="mt-auto pt-4">
                                            <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.quantity_label') }}</p>

                                            @if ($availableToday)
                                                @php($gradeRows = $gradeRowsByProduct[$product->id] ?? [])
                                                <div class="mt-2 space-y-3">
                                                    @foreach ($gradeRows as $entry)
                                                        @php($jsId = $entry['jsId'])
                                                        @php($entryOld = (int) old(str_replace(['[', ']'], ['.', ''], $entry['formName']), 0))
                                                        <div>
                                                            @if (! empty($entry['gradeLabel']))
                                                                <div class="mb-1 flex items-baseline justify-between gap-2">
                                                                    <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-primary">{{ $entry['gradeLabel'] }}</span>
                                                                    <span class="font-mono text-[0.65rem] tabular-nums text-shop-muted">{{ $entry['priceDisplay'] }}</span>
                                                                </div>
                                                            @endif
                                                            <input type="hidden" id="shop-qty-{{ $jsId }}" name="{{ $entry['formName'] }}" value="{{ $entryOld }}" :value="quantities[@js($jsId)] || 0" data-testid="shop-qty-stepper">
                                                            <div class="flex items-stretch border border-shop-border bg-shop-background">
                                                                <button
                                                                    type="button"
                                                                    @click="dec(@js($jsId))"
                                                                    class="w-10 py-3 font-mono text-sm text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                                                    :disabled="(quantities[@js($jsId)] || 0) <= 0"
                                                                    aria-label="−"
                                                                >−</button>
                                                                <div class="flex flex-1 items-center justify-center border-x border-shop-border py-3 font-mono text-sm tabular-nums" x-text="label(@js($jsId))">{{ $entryOld > 0 ? $product->displayQuantity($entryOld) : __('shop.quantity_none') }}</div>
                                                                <button
                                                                    type="button"
                                                                    @click="inc(@js($jsId))"
                                                                    class="w-10 py-3 font-mono text-sm text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                                                    :disabled="atCap(@js($jsId))"
                                                                    aria-label="+"
                                                                >+</button>
                                                            </div>
                                                            <p class="mt-1 font-mono text-xs tabular-nums text-shop-muted" x-show="(quantities[@js($jsId)] || 0) > 0" x-cloak>
                                                                <span x-text="money(lineTotal(@js($jsId)))"></span>
                                                            </p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="mt-2 flex items-stretch border border-shop-border bg-shop-paper opacity-60">
                                                    <div class="w-10 py-3 text-center font-mono text-sm text-shop-muted">−</div>
                                                    <div class="flex flex-1 items-center justify-center border-x border-shop-border py-3 font-mono text-sm text-shop-muted">{{ __('shop.quantity_none') }}</div>
                                                    <div class="w-10 py-3 text-center font-mono text-sm text-shop-muted">+</div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Footer reserved for week-only availability notes. --}}
                                        <div class="mt-2 min-h-[1.5rem]">
                                            @unless ($availableToday)
                                                <p class="text-xs leading-relaxed text-shop-muted" data-testid="shop-weekly-note">
                                                    <span class="font-mono uppercase tracking-widest text-shop-primary">{{ __('shop.weekly_unavailable_label') }}</span><br>
                                                    {{ __('shop.weekly_available_on', ['dates' => $dateList]) }}
                                                </p>
                                            @endunless
                                        </div>
                                    </article>
                                    </div>{{-- /cardVisible wrapper --}}
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('items')" class="mt-4" />
                        @endif
                    </div>
                </x-reveal>

                {{-- ── Fulfillment ───────────────────────────────────── --}}
                <x-reveal as="section" class="border-b border-shop-border py-14">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8">
                            <p class="{{ $eyebrow }}">{{ __('shop.section_fulfillment') }}</p>
                        </header>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label
                                class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                                :class="mode === 'pickup' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'"
                            >
                                <input type="radio" name="fulfillment_mode" value="pickup" x-model="mode" class="sr-only" @checked(old('fulfillment_mode', 'pickup') === 'pickup')>
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.fulfillment_pickup_eyebrow') }}</span>
                                <span class="font-display text-2xl leading-tight">{{ __('shop.fulfillment_pickup') }}</span>
                                <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.fulfillment_pickup_hint') }}</span>
                                <span class="mt-1 font-mono text-[0.6rem] uppercase tracking-widest" :class="mode === 'pickup' ? 'text-shop-primary' : 'text-shop-muted'" x-text="mode === 'pickup' ? @js(__('shop.option_selected')) : @js(__('shop.option_select'))"></span>
                            </label>
                            <label
                                class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                                :class="mode === 'delivery' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'"
                            >
                                <input type="radio" name="fulfillment_mode" value="delivery" x-model="mode" class="sr-only" @checked(old('fulfillment_mode') === 'delivery')>
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.fulfillment_delivery_eyebrow') }}</span>
                                <span class="font-display text-2xl leading-tight">{{ __('shop.fulfillment_delivery') }}</span>
                                <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.fulfillment_delivery_hint', ['fee' => \App\Support\Money::format($deliveryFeeEur)]) }}</span>
                                <span class="mt-1 font-mono text-[0.6rem] uppercase tracking-widest" :class="mode === 'delivery' ? 'text-shop-primary' : 'text-shop-muted'" x-text="mode === 'delivery' ? @js(__('shop.option_selected')) : @js(__('shop.option_select'))"></span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-4" />
                    </div>
                </x-reveal>

                {{-- ── Pickup location (pickup only) ─────────────────── --}}
                <x-reveal as="section" class="border-b border-shop-border py-14" x-show="mode === 'pickup'" x-cloak>
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p class="{{ $eyebrow }}">{{ __('shop.pickup_location_section') }}</p>
                            </div>
                            <p class="max-w-md text-sm leading-relaxed text-shop-muted">{{ __('shop.pickup_location_hint') }}</p>
                        </header>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach ($pickupLocations as $key => $loc)
                                <label
                                    class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                                    :class="pickupLocation === @js((string) $key) ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'"
                                >
                                    <input type="radio" name="pickup_location" value="{{ $key }}" x-model="pickupLocation" class="sr-only" @checked(old('pickup_location') === $key)>
                                    <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.pickup_location_eyebrow', ['index' => $loop->iteration]) }}</span>
                                    <span class="font-display text-2xl leading-snug">{{ $loc['street'] }}</span>
                                    <span class="text-sm leading-relaxed text-shop-muted">{{ $loc['postal_code'] }} {{ $loc['city'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('pickup_location')" class="mt-4" />
                    </div>
                </x-reveal>

                {{-- ── Payment (pickup only; delivery is always online) ── --}}
                <x-reveal as="section" class="border-b border-shop-border py-14" x-show="mode === 'pickup'" x-cloak>
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8">
                            <p class="{{ $eyebrow }}">{{ __('shop.section_payment') }}</p>
                        </header>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label
                                class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                                :class="payment === 'online' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'"
                            >
                                <input type="radio" name="payment_method" value="online" x-model="payment" class="sr-only" @checked(old('payment_method', 'online') === 'online')>
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.payment_online_eyebrow') }}</span>
                                <span class="font-display text-2xl leading-tight">{{ __('shop.payment_online') }}</span>
                                <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.payment_online_hint') }}</span>
                            </label>
                            <label
                                class="flex cursor-pointer flex-col items-start gap-3 border bg-shop-background px-5 py-5 text-left transition"
                                :class="payment === 'in_person' ? 'border-shop-primary' : 'border-shop-border hover:border-shop-primary'"
                            >
                                <input type="radio" name="payment_method" value="in_person" x-model="payment" class="sr-only" @checked(old('payment_method') === 'in_person')>
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.payment_in_person_eyebrow') }}</span>
                                <span class="font-display text-2xl leading-tight">{{ __('shop.payment_in_person') }}</span>
                                <span class="text-sm leading-relaxed text-shop-muted">{{ __('shop.payment_in_person_hint') }}</span>
                            </label>
                        </div>
                        <template x-if="mode === 'delivery'">
                            <input type="hidden" name="payment_method" value="online">
                        </template>
                        <x-input-error :messages="$errors->get('payment_method')" class="mt-4" />
                    </div>
                </x-reveal>

                {{-- ── Contact ───────────────────────────────────────── --}}
                <x-reveal as="section" class="border-b border-shop-border py-14">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8">
                            <p class="{{ $eyebrow }}">{{ __('shop.section_contact') }}</p>
                        </header>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="{{ $fieldLabel }}">{{ __('Name') }}</span>
                                <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="{{ $fieldLabel }}">{{ __('Email') }}</span>
                                <input type="email" name="email" value="{{ old('email') }}" x-model="email" required autocomplete="email" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="{{ $fieldLabel }}">
                                    {{ __('Phone') }}<span class="ml-2 text-sm font-normal text-shop-muted" x-cloak><span x-show="mode === 'delivery'">{{ __('required') }}</span><span x-show="mode !== 'delivery'">({{ __('optional') }})</span></span>
                                </span>
                                <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </label>
                        </div>
                    </div>
                </x-reveal>

                {{-- ── Delivery address (delivery only) ──────────────── --}}
                <x-reveal as="section" class="border-b border-shop-border py-14" x-show="mode === 'delivery'" x-cloak>
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8">
                            <p class="{{ $eyebrow }}">{{ __('shop.section_address') }}</p>
                        </header>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="{{ $fieldLabel }}">{{ __('shop.delivery_street') }}</span>
                                <input type="text" name="delivery_street" value="{{ old('delivery_street') }}" autocomplete="street-address" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('delivery_street')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="{{ $fieldLabel }}">{{ __('shop.delivery_postal_code') }}</span>
                                <input type="text" name="delivery_postal_code" value="{{ old('delivery_postal_code') }}" autocomplete="postal-code" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="{{ $fieldLabel }}">{{ __('shop.delivery_city') }}</span>
                                <input type="text" name="delivery_city" value="{{ old('delivery_city') }}" autocomplete="address-level2" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('delivery_city')" class="mt-2" />
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="{{ $fieldLabel }}">{{ __('shop.delivery_notes') }} <span class="font-normal text-shop-muted">({{ __('optional') }})</span></span>
                                <textarea name="delivery_notes" rows="2" class="{{ $inputClass }}">{{ old('delivery_notes') }}</textarea>
                                <x-input-error :messages="$errors->get('delivery_notes')" class="mt-2" />
                            </label>
                        </div>
                    </div>
                </x-reveal>

                {{-- ── Summary + submit ──────────────────────────────── --}}
                <x-reveal as="section" class="py-14">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <p class="{{ $eyebrow }}">{{ __('shop.summary_heading') }}</p>
                        <div class="mt-6 border-y border-shop-border py-6">
                            {{-- ── Coupon code ─────────────────────────── --}}
                            <div class="mb-6" data-testid="shop-coupon">
                                <span class="{{ $fieldLabel }}">{{ __('coupon.field_label') }}</span>

                                {{-- Entry row (hidden once a code is applied) --}}
                                <div class="mt-2 flex flex-wrap items-stretch gap-3" x-show="!couponApplied">
                                    <input
                                        type="text"
                                        x-model="couponCode"
                                        @keydown.enter.prevent="applyCoupon()"
                                        placeholder="{{ __('coupon.field_placeholder') }}"
                                        autocapitalize="characters"
                                        class="{{ $inputClass }} flex-1"
                                        data-testid="shop-coupon-input"
                                    />
                                    <button
                                        type="button"
                                        @click="applyCoupon()"
                                        :disabled="applyingCoupon || couponCode.trim() === ''"
                                        class="shrink-0 rounded-sm border border-shop-border px-5 py-2 font-mono text-[0.7rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary disabled:cursor-not-allowed disabled:opacity-40"
                                        data-testid="shop-coupon-apply"
                                    >
                                        <span x-show="!applyingCoupon">{{ __('coupon.apply') }}</span>
                                        <span x-show="applyingCoupon" x-cloak>{{ __('coupon.applying') }}</span>
                                    </button>
                                </div>

                                {{-- Applied row --}}
                                <div class="mt-2 flex items-center justify-between gap-3 border border-shop-primary/40 bg-shop-primary/5 px-4 py-3" x-show="couponApplied" x-cloak data-testid="shop-coupon-applied">
                                    <span class="font-mono text-[0.75rem] uppercase tracking-widest text-shop-primary" x-text="appliedCode"></span>
                                    <button
                                        type="button"
                                        @click="clearCoupon()"
                                        class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-foreground hover:underline"
                                        data-testid="shop-coupon-remove"
                                    >{{ __('coupon.remove') }}</button>
                                </div>

                                <p class="mt-2 text-sm leading-relaxed text-red-600" x-show="couponMessage" x-cloak x-text="couponMessage" data-testid="shop-coupon-message"></p>

                                <input type="hidden" name="coupon_code" :value="couponApplied ? appliedCode : ''">
                            </div>

                            {{-- ── Breakdown (subtotal / discount / delivery) ── --}}
                            <div class="space-y-2" x-show="couponApplied || effectiveDeliveryFee > 0" x-cloak>
                                <div class="flex items-baseline justify-between gap-4 text-sm text-shop-muted">
                                    <span>{{ __('shop.summary_line_items') }}</span>
                                    <span class="tabular-nums" x-text="money(itemsTotal)"></span>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 text-sm text-shop-primary" x-show="couponApplied" x-cloak data-testid="shop-coupon-discount-line">
                                    <span x-text="@js(__('coupon.discount_line')) + ' ' + appliedCode"></span>
                                    <span class="tabular-nums" x-text="'− ' + money(couponDiscount)"></span>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 text-sm text-shop-muted" x-show="effectiveDeliveryFee > 0" x-cloak>
                                    <span>{{ __('shop.summary_line_delivery') }}</span>
                                    <span class="tabular-nums" x-text="money(effectiveDeliveryFee)"></span>
                                </div>
                            </div>

                            <div class="mt-4 flex items-baseline justify-between gap-4">
                                <span class="font-display text-2xl">{{ __('shop.summary_line_total') }}</span>
                                <span class="font-display text-4xl text-shop-primary tabular-nums" x-text="money(total)">{{ \App\Support\Money::format(0) }}</span>
                            </div>
                            <template x-for="rate in vatRates" :key="rate">
                                <p class="mt-2 font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted" x-text="vatLine(rate)"></p>
                            </template>
                        </div>

                        <label class="mt-10 flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-shop-muted">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-1 h-4 w-4 accent-shop-primary">
                            <span>
                                {!! __('shop.consent_text', ['privacy' => '<a href="'.route('public.privacy').'" class="text-shop-foreground underline-offset-4 hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_privacy_link')).'</a>', 'terms' => '<a href="'.route('public.shop.terms').'" class="text-shop-foreground underline-offset-4 hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_terms_link')).'</a>']) !!}
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('consent')" class="mt-2" />

                        <div class="mt-10 flex flex-wrap items-center gap-5">
                            <button
                                type="submit"
                                class="rounded-sm bg-shop-primary px-7 py-3 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="itemCount === 0"
                                data-testid="shop-submit"
                            >
                                <span x-show="mode === 'delivery' || payment === 'online'">{{ __('shop.cta_continue_to_payment') }} →</span>
                                <span x-show="mode === 'pickup' && payment === 'in_person'" x-cloak>{{ __('shop.cta_place_order') }} →</span>
                            </button>
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">
                                <span x-show="payment !== 'in_person'">{{ __('shop.cta_helper') }}</span>
                                <span x-show="payment === 'in_person'" x-cloak>{{ __('shop.cta_helper_in_person') }}</span>
                            </span>
                        </div>
                    </div>
                </x-reveal>
            </form>

            {{-- ── In-person order confirmation modal ──────────────────── --}}
            <div
                x-show="showConfirmModal"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="confirmTitle"
                data-testid="shop-confirm-modal"
            >
                {{-- Backdrop --}}
                <div class="absolute inset-0 bg-brand-ink/60 backdrop-blur-sm" @click="showConfirmModal = false"></div>

                {{-- Panel --}}
                <div class="relative w-full max-w-md bg-shop-paper p-8 shadow-xl">
                    <p class="{{ $eyebrow }} mb-4" x-text="confirmTitle"></p>
                    <p class="mb-6 text-sm leading-relaxed text-shop-muted" x-text="confirmBody"></p>

                    {{-- Summary --}}
                    <div class="mb-6 border border-shop-border p-4 text-sm" data-testid="shop-confirm-summary">
                        {{-- Cart positions: one row per line, name + qty on
                             the left, line € on the right. Hidden when the
                             cart is empty (the submit guard catches that
                             elsewhere). --}}
                        <div class="mb-3 space-y-2 border-b border-shop-border pb-3" x-show="cartLines.length > 0" x-cloak>
                            <p class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted" x-text="confirmPositionsLabel"></p>
                            <ul class="space-y-1.5" data-testid="shop-confirm-positions">
                                <template x-for="line in cartLines" :key="line.id">
                                    <li class="flex items-baseline justify-between gap-4">
                                        <span class="text-shop-foreground">
                                            <span x-text="line.name"></span>
                                            <span class="text-shop-muted" x-text="' · ' + line.qty"></span>
                                        </span>
                                        <span class="text-right tabular-nums text-shop-foreground" x-text="money(line.lineTotal)"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <dl class="space-y-3">
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="text-shop-muted" x-text="confirmDateLabel"></dt>
                                <dd class="text-right font-medium text-shop-foreground" x-text="selectedDateLabel"></dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="text-shop-muted" x-text="confirmLocationLabel"></dt>
                                <dd class="text-right font-medium text-shop-foreground" x-text="confirmPickupName"></dd>
                            </div>
                            <div class="border-t border-shop-border pt-3 flex items-baseline justify-between gap-4">
                                <dt class="font-semibold text-shop-foreground" x-text="confirmTotalLabel"></dt>
                                <dd class="text-right font-semibold tabular-nums text-shop-foreground" x-text="money(total)"></dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            class="rounded-sm bg-shop-primary px-6 py-3 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="confirmSubmitting"
                            @click="submitConfirmed()"
                            data-testid="shop-confirm-submit"
                        >
                            <span x-show="!confirmSubmitting" x-text="confirmSubmitLabel"></span>
                            <span x-show="confirmSubmitting" x-cloak>…</span>
                        </button>
                        <button
                            type="button"
                            class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:underline"
                            :disabled="confirmSubmitting"
                            @click="showConfirmModal = false"
                            data-testid="shop-confirm-cancel"
                        >
                            <span x-text="confirmCancelLabel"></span>
                        </button>
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
                // Coupon state.
                couponPreviewUrl: config.couponPreviewUrl,
                csrf: config.csrf,
                couponErrorGeneric: config.couponErrorGeneric,
                couponCode: '',
                appliedCode: '',
                couponApplied: false,
                couponDiscount: 0,
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
                get cartLines() {
                    return this.products
                        .filter((p) => (this.quantities[p.id] || 0) > 0)
                        .map((p) => ({
                            id: p.id,
                            name: p.name,
                            qty: this.label(p.id),
                            lineTotal: this.lineTotal(p.id),
                        }));
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
                        // Delivery fee never changes a subtotal-only discount,
                        // but re-validate so caps/min-order stay authoritative.
                        this.scheduleCouponRefresh();
                    });
                    this.initEngagementTracking();
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
                // ── Coupon ───────────────────────────────────────────────
                applyCoupon() {
                    const code = this.couponCode.trim();
                    if (code === '' || this.applyingCoupon) return;
                    this.applyingCoupon = true;
                    this.couponMessage = '';
                    fetch(this.couponPreviewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        body: JSON.stringify({
                            coupon_code: code,
                            email: this.email,
                            fulfillment_mode: this.mode,
                            quantities: this.quantities,
                        }),
                    })
                        .then((r) => r.json())
                        .then((d) => {
                            if (d.applied) {
                                this.couponApplied = true;
                                this.appliedCode = d.code;
                                this.couponDiscount = d.discount_eur;
                                this.couponType = d.type || '';
                                this.couponMessage = '';
                            } else {
                                this.couponApplied = false;
                                this.appliedCode = '';
                                this.couponDiscount = 0;
                                this.couponType = '';
                                this.couponMessage = d.message || this.couponErrorGeneric;
                            }
                        })
                        .catch(() => { this.couponMessage = this.couponErrorGeneric; })
                        .finally(() => { this.applyingCoupon = false; });
                },
                clearCoupon() {
                    this.couponApplied = false;
                    this.appliedCode = '';
                    this.couponDiscount = 0;
                    this.couponType = '';
                    this.couponCode = '';
                    this.couponMessage = '';
                },
                // Re-validate an applied coupon after the cart changes so the
                // preview stays honest (the server re-checks again at submit).
                scheduleCouponRefresh() {
                    if (!this.couponApplied || !this.appliedCode) return;
                    clearTimeout(this._couponTimer);
                    this._couponTimer = setTimeout(() => {
                        this.couponCode = this.appliedCode;
                        this.applyCoupon();
                    }, 400);
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
                        this.scheduleCouponRefresh();
                    }
                },
                dec(id) {
                    const p = this.product(id);
                    if (!p) return;
                    const i = this.stepIndex(id);
                    if (i > 0) {
                        this.quantities[id] = p.steps[i - 1].v;
                        this.scheduleCouponRefresh();
                    }
                },
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
                // Free-delivery coupons waive the fee; all others cut the subtotal.
                get isFreeDelivery() { return this.couponApplied && this.couponType === 'free_delivery'; },
                get appliedDiscount() {
                    if (!this.couponApplied) return 0;
                    if (this.isFreeDelivery) return this.effectiveDeliveryFee;
                    return Math.min(this.couponDiscount, this.itemsTotal);
                },
                get total() {
                    if (this.isFreeDelivery) return Math.round(this.itemsTotal * 100) / 100;
                    return Math.round((Math.max(0, this.itemsTotal - this.appliedDiscount) + this.effectiveDeliveryFee) * 100) / 100;
                },
                money(v) { return v.toFixed(2).replace('.', ',') + ' €'; },
                // ── VAT breakdown (per rate, gross-inclusive) ───────────
                // Sums the VAT contained in each line + the delivery fee,
                // grouped by rate. Rendered as one summary line per rate.
                get vatBreakdown() {
                    const bd = {};
                    this.products.forEach((p) => {
                        const lt = this.lineTotal(p.id);
                        if (lt > 0) {
                            const r = p.vatRate;
                            bd[r] = Math.round(((bd[r] || 0) + lt * r / (100 + r)) * 100) / 100;
                        }
                    });
                    const fee = this.effectiveDeliveryFee;
                    if (fee > 0) {
                        const r = this.deliveryVatRate;
                        bd[r] = Math.round(((bd[r] || 0) + fee * r / (100 + r)) * 100) / 100;
                    }
                    return bd;
                },
                get vatRates() {
                    return Object.keys(this.vatBreakdown).map(Number).sort((a, b) => a - b);
                },
                vatLine(rate) {
                    return this.vatLineTemplate
                        .replace(':rate', rate)
                        .replace(':amount', this.money(this.vatBreakdown[rate]));
                },
            };
        }
    </script>
</x-shop-layout>
