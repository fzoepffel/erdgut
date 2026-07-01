<x-shop-layout :title="__('restaurant.catalog_title')" restaurant-nav="shop">
    @php
        // Flat JS registry of every orderable grade (both tabs) for the basket maths.
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
        $stockCount = $products->where('inStock', true)->count();
        $seasonCount = $products->filter(fn ($r) => $r['inSeason'] && ! $r['inStock'])->count();
    @endphp

    <div
        x-data="restaurantCatalog({
            rows: {{ Illuminate\Support\Js::from($jsRows) }},
            submitUrl: @js(route('app.restaurant.orders.store')),
            date: @js($selectedDate?->toDateString()),
            subcategoriesByCategory: @js($alpineSubcategories),
            defaultShopCategory: @js($defaultShopCategory),
            weeklyLabel: @js(__('restaurant.recurrence_weekly')),
            untilLabel: @js(__('restaurant.recurrence_until')),
            prefill: {{ Illuminate\Support\Js::from([
                'street' => $profile?->billing_street ?? '',
                'postal' => $profile?->billing_postal_code ?? '',
                'city' => $profile?->billing_city ?? '',
            ]) }},
            csrf: @js(csrf_token()),
        })"
    >
        {{-- ── Delivery-day picker ─────────────────────────────────────── --}}
        <section class="border-b border-shop-border py-10" data-testid="restaurant-date-picker"
                 x-data="restaurantDateFilter({
                     allDates: @js($alpineDates),
                     selectedIso: @js($selectedDate?->toDateString() ?? ''),
                     shopUrl: @js(route('app.restaurant.catalog')),
                     availableSuffix: @js(__('restaurant.date_in_stock_suffix')),
                     onRequestLabel: @js(__('restaurant.date_on_request_only')),
                 })">
            <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
                <p class="eyebrow text-shop-primary">{{ __('restaurant.catalog_eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl text-shop-foreground sm:text-4xl">{{ __('restaurant.catalog_title') }}</h1>

                @if ($selectableDates->isEmpty())
                    <div class="shop-card mt-8 p-8 text-center text-shop-muted">{{ __('restaurant.no_open_days') }}</div>
                @else
                    <div class="relative mt-8 max-w-xl" @keydown.escape="dateOpen = false" @click.outside="dateOpen = false">
                        <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.catalog_date_label') }}</p>
                        <button type="button" @click="dateOpen = !dateOpen" :aria-expanded="dateOpen"
                                data-testid="restaurant-date-button"
                                class="mt-1 flex w-full items-end justify-between gap-6 border-b border-shop-foreground bg-transparent px-1 pb-3 pt-2 text-left transition hover:border-shop-primary">
                            <span class="flex flex-col gap-2">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted"
                                      x-text="selectedDateData ? ('KW ' + selectedDateData.kw + ' · ' + stockNote(selectedDateData)) : ''"></span>
                                <span class="font-display text-3xl leading-tight md:text-4xl" x-text="selectedDateData?.longLabel ?? ''"></span>
                            </span>
                            <svg viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2 h-3 w-3 shrink-0 text-shop-primary transition-transform" :class="dateOpen && 'rotate-180'" aria-hidden="true">
                                <path d="M1 1.5 6 6.5 11 1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div x-show="dateOpen" x-transition.origin.top x-cloak
                             class="absolute left-0 right-0 z-20 mt-2 max-h-96 overflow-y-auto border border-shop-border bg-shop-card shadow-[0_20px_40px_-20px_rgba(0,0,0,0.25)]"
                             role="listbox" data-testid="restaurant-date-menu">
                            <template x-for="group in datesByKw" :key="group.kw">
                                <div>
                                    <p class="sticky top-0 bg-shop-paper px-5 py-2 font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted" x-text="'KW ' + group.kw"></p>
                                    <template x-for="d in group.dates" :key="d.iso">
                                        <button type="button" role="option" :aria-selected="d.iso === selectedIso"
                                                @click="selectDate(d.iso)"
                                                class="flex w-full items-center justify-between gap-4 px-5 py-3 text-left transition hover:bg-shop-paper"
                                                :class="d.iso === selectedIso ? 'text-shop-primary bg-shop-paper' : 'text-shop-foreground'">
                                            <span class="flex items-center gap-2">
                                                <span x-show="d.iso === selectedIso" class="text-shop-primary" aria-hidden="true">&#9679;</span>
                                                <span class="font-display text-xl" x-text="d.label"></span>
                                            </span>
                                            <span class="text-right font-mono text-[0.65rem] uppercase tracking-widest"
                                                  :class="d.inStockCount > 0 ? 'text-shop-primary' : 'text-shop-muted'"
                                                  x-text="stockNote(d)"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if (! $selectableDates->isEmpty())
            {{-- ── Tabs + filters + product grids ──────────────────────── --}}
            <section class="py-10">
                <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
                    {{-- Tabs --}}
                    <div class="flex flex-wrap gap-1 border-b border-shop-border" role="tablist" data-testid="restaurant-tabs">
                        <button type="button" @click="activeTab = 'stock'" role="tab" :aria-selected="activeTab === 'stock'"
                                data-testid="restaurant-tab-stock"
                                class="-mb-px border-b-2 px-4 py-3 font-mono text-[0.7rem] uppercase tracking-widest transition"
                                :class="activeTab === 'stock' ? 'border-shop-primary text-shop-primary' : 'border-transparent text-shop-muted hover:text-shop-foreground'">
                            {{ __('restaurant.tab_in_stock') }} <span class="opacity-60">({{ $stockCount }})</span>
                        </button>
                        <button type="button" @click="activeTab = 'season'" role="tab" :aria-selected="activeTab === 'season'"
                                data-testid="restaurant-tab-season"
                                class="-mb-px border-b-2 px-4 py-3 font-mono text-[0.7rem] uppercase tracking-widest transition"
                                :class="activeTab === 'season' ? 'border-shop-primary text-shop-primary' : 'border-transparent text-shop-muted hover:text-shop-foreground'">
                            {{ __('restaurant.tab_on_request') }} <span class="opacity-60">({{ $seasonCount }})</span>
                        </button>
                        <button type="button" @click="activeTab = 'special'" role="tab" :aria-selected="activeTab === 'special'"
                                data-testid="restaurant-tab-special"
                                class="-mb-px border-b-2 px-4 py-3 font-mono text-[0.7rem] uppercase tracking-widest transition"
                                :class="activeTab === 'special' ? 'border-shop-primary text-shop-primary' : 'border-transparent text-shop-muted hover:text-shop-foreground'">
                            {{ __('restaurant.tab_special') }}
                        </button>
                    </div>

                    {{-- Filters (both product tabs) --}}
                    <div x-show="activeTab !== 'special'" class="mt-8 space-y-4">
                        <div>
                            <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.grid_filter_search_label') }}</p>
                            <input type="text" x-model="nameQuery" autocomplete="off"
                                   :placeholder="@js(__('shop.grid_filter_search_placeholder'))"
                                   data-testid="restaurant-name-filter"
                                   class="mt-1 w-full border-b border-shop-foreground bg-transparent px-1 pb-3 pt-2 font-display text-2xl leading-tight text-shop-foreground placeholder:text-shop-muted focus:border-shop-primary focus:outline-none md:text-3xl">
                        </div>
                        @if (count($availableCategories) >= 1)
                            <div>
                                <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.grid_filter_category_label') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" @click="setCategory('')"
                                            :class="activeCategory === '' ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                            class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition">{{ __('shop.grid_filter_all') }}</button>
                                    @foreach ($availableCategories as $cat)
                                        <button type="button" @click="setCategory('{{ $cat }}')"
                                                :class="activeCategory === '{{ $cat }}' ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                                class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition">{{ __('admin.category.'.$cat) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div x-show="activeCategory !== '' && availableSubcategories.length > 0" x-cloak>
                            <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.grid_filter_subcategory_label') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button" @click="activeSubcategoryId = 0"
                                        :class="activeSubcategoryId === 0 ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                        class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition">{{ __('shop.grid_filter_all') }}</button>
                                <template x-for="s in availableSubcategories" :key="s.id">
                                    <button type="button" @click="activeSubcategoryId = s.id"
                                            :class="activeSubcategoryId === s.id ? 'bg-shop-foreground text-shop-background' : 'border border-shop-border text-shop-foreground hover:border-shop-foreground'"
                                            class="px-3 py-1 font-mono text-[0.6rem] uppercase tracking-wider transition" x-text="s.label"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Product grid (in-stock + on-request cards; each tagged with its tab) --}}
                    <div x-show="activeTab !== 'special'" class="mt-10 grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 items-start">
                        @foreach ($products as $row)
                            @php
                                $product = $row['product'];
                                $tab = $row['inStock'] ? 'stock' : 'season';
                                $imageUrl = $product->thumbUrl();
                            @endphp
                            <div x-show="activeTab === '{{ $tab }}' && cardVisible({{ json_encode(mb_strtolower($product->name)) }}, '{{ $product->category }}', {{ $product->subcategory_id ?? 0 }})"
                                 data-testid="restaurant-catalog-item" data-tab="{{ $tab }}" data-product-id="{{ $product->id }}">
                                <article class="group flex flex-col">
                                    <a href="{{ route('public.shop.products.show', $product) }}" class="relative block aspect-square overflow-hidden bg-shop-paper {{ $row['inStock'] ? '' : 'grayscale' }}">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" width="600" height="600" loading="lazy" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" />
                                        @else
                                            <span class="flex h-full w-full items-center justify-center text-shop-muted" aria-hidden="true">
                                                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
                                            </span>
                                        @endif
                                        <span class="absolute left-3 top-3 px-2 py-1 font-mono text-[0.6rem] uppercase tracking-widest {{ $row['inStock'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                            {{ $row['inStock'] ? __('restaurant.in_stock') : __('restaurant.on_request') }}
                                        </span>
                                    </a>

                                    <h3 class="mt-4 font-display text-2xl leading-tight">
                                        <a href="{{ route('public.shop.products.show', $product) }}" class="underline-offset-4 hover:underline">{{ $product->name }}</a>
                                    </h3>
                                    <div class="mt-2 h-px bg-shop-border"></div>
                                    @if ($product->producer)
                                        <p class="mt-3 font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary">{{ $product->producer->name }}</p>
                                    @endif

                                    {{-- Grade rows: net price + stepper + per-line recurrence --}}
                                    <div class="mt-4 space-y-3">
                                        @foreach ($row['grades'] as $grade)
                                            <div class="border-t border-shop-border/60 pt-3 first:border-t-0 first:pt-0">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="min-w-0">
                                                        @if ($grade['label'])<p class="truncate text-sm text-shop-foreground">{{ $grade['label'] }}</p>@endif
                                                        <p class="font-mono text-sm tabular-nums text-shop-foreground">@eur($grade['netPerDisplay']) <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}/{{ $grade['unitSymbol'] }}</span></p>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-1">
                                                        <button type="button" @click="dec(@js($grade['key']))" class="h-9 w-9 border border-shop-border text-shop-foreground transition hover:border-shop-primary" aria-label="−">−</button>
                                                        <input type="text" inputmode="decimal" :value="displayValue(@js($grade['key']))"
                                                               @change="setFromDisplay(@js($grade['key']), $event.target.value)"
                                                               class="w-16 border border-shop-border bg-shop-background px-2 py-1.5 text-center text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                                        <button type="button" @click="inc(@js($grade['key']))" class="h-9 w-9 border border-shop-border text-shop-foreground transition hover:border-shop-primary" aria-label="+">+</button>
                                                        <span class="w-7 text-xs text-shop-muted">{{ $grade['unitSymbol'] }}</span>
                                                    </div>
                                                </div>
                                                {{-- Per-product recurrence, surfaced the moment a quantity is added --}}
                                                <div x-show="(quantities[@js($grade['key'])] || 0) > 0" x-cloak
                                                     class="mt-2 flex flex-wrap items-center gap-2 border-l-2 border-shop-primary/40 pl-3 text-xs">
                                                    <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.recurrence_label') }}</span>
                                                    <select x-model="recurrence[@js($grade['key'])]" class="border border-shop-border bg-shop-background px-2 py-1 text-xs text-shop-foreground focus:border-shop-primary focus:outline-none">
                                                        <option value="one_time">{{ __('restaurant.recurrence_one_time') }}</option>
                                                        <option value="weekly">{{ __('restaurant.recurrence_weekly') }}</option>
                                                    </select>
                                                    <label x-show="recurrence[@js($grade['key'])] === 'weekly'" class="flex items-center gap-1 text-shop-muted">
                                                        {{ __('restaurant.recurrence_until') }}
                                                        <input type="date" x-model="until[@js($grade['key'])]" class="border border-shop-border bg-shop-background px-1 py-0.5 text-xs text-shop-foreground focus:border-shop-primary focus:outline-none">
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            </div>
                        @endforeach

                        {{-- Per-tab empty states --}}
                        @if ($stockCount === 0)
                            <div x-show="activeTab === 'stock'" x-cloak class="shop-card col-span-full p-8 text-center text-shop-muted" data-testid="restaurant-stock-empty">{{ __('restaurant.tab_in_stock_empty') }}</div>
                        @endif
                        @if ($seasonCount === 0)
                            <div x-show="activeTab === 'season'" x-cloak class="shop-card col-span-full p-8 text-center text-shop-muted" data-testid="restaurant-season-empty">{{ __('restaurant.tab_on_request_empty') }}</div>
                        @endif
                    </div>

                    {{-- Special request tab (off-catalog: wild herbs, pea tops, …) --}}
                    <div x-show="activeTab === 'special'" x-cloak class="mt-10">
                        <div class="shop-card p-6">
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
                                        <select x-model="s.unit" class="border border-shop-border bg-shop-background px-2 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                            @foreach (['piece', 'bunch', 'kg', 'g', 'l', 'ml'] as $u)
                                                <option value="{{ $u }}">{{ \App\Enums\ProductUnit::displaySymbol($u) }}</option>
                                            @endforeach
                                        </select>
                                        <select x-model="s.recurrence" class="border border-shop-border bg-shop-background px-2 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                            <option value="one_time">{{ __('restaurant.recurrence_one_time') }}</option>
                                            <option value="weekly">{{ __('restaurant.recurrence_weekly') }}</option>
                                        </select>
                                        <input x-show="s.recurrence === 'weekly'" type="date" x-model="s.until"
                                               class="border border-shop-border bg-shop-background px-2 py-2 text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                        <button type="button" @click="removeSpecialRequest(i)" class="px-2 text-shop-muted hover:text-chapter-meat" aria-label="{{ __('restaurant.special_remove') }}">✕</button>
                                    </div>
                                </template>
                                <p x-show="specialRequests.length === 0" class="text-sm text-shop-muted">{{ __('restaurant.special_empty') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div aria-hidden="true" class="h-24"></div>

            {{-- Sticky basket bar --}}
            <div x-show="hasAnything" x-cloak
                 class="fixed inset-x-0 bottom-0 z-30 border-t border-shop-border bg-shop-background/95 backdrop-blur">
                <div class="mx-auto flex max-w-[1100px] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-12">
                    <p class="text-sm text-shop-foreground">
                        <span x-text="totalCount"></span> <span x-text="totalCount === 1 ? @js(__('restaurant.cart_line')) : @js(__('restaurant.cart_lines'))"></span>
                        · <span class="font-mono" x-text="formatEur(netTotal)"></span> <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span>
                    </p>
                    <button type="button" @click="openSubmit = true" data-testid="restaurant-cart-continue"
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

                    <h2 class="font-display text-2xl text-shop-foreground">{{ __('restaurant.cart_review_title') }}</h2>
                    <p class="mt-1 text-sm text-shop-muted">{{ __('restaurant.cart_review_intro') }}</p>

                    {{-- One cohesive list: catalog + special lines together, each
                         showing its own delivery schedule (weekly lines carry a badge). --}}
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
                                    <span class="ml-2 inline-block bg-amber-100 px-1.5 py-0.5 font-mono text-[0.6rem] uppercase tracking-widest text-amber-800">{{ __('restaurant.on_request') }}</span>
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

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <button type="button" @click="openSubmit = false" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted hover:text-shop-primary">{{ __('restaurant.cart_back') }}</button>
                        <button type="submit" data-testid="restaurant-cart-submit" class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                            {{ __('restaurant.cart_submit') }}
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script>
        function restaurantDateFilter(config) {
            return {
                allDates: config.allDates,
                selectedIso: config.selectedIso,
                shopUrl: config.shopUrl,
                availableSuffix: config.availableSuffix,
                onRequestLabel: config.onRequestLabel,
                dateOpen: false,
                get selectedDateData() {
                    return this.allDates.find((d) => d.iso === this.selectedIso) || null;
                },
                get datesByKw() {
                    const groups = [];
                    let cur = null;
                    for (const d of this.allDates) {
                        if (!cur || cur.kw !== d.kw) { cur = { kw: d.kw, dates: [] }; groups.push(cur); }
                        cur.dates.push(d);
                    }
                    return groups;
                },
                stockNote(d) {
                    return d.inStockCount > 0 ? (d.inStockCount + ' ' + this.availableSuffix) : this.onRequestLabel;
                },
                selectDate(iso) {
                    const url = new URL(this.shopUrl, window.location.origin);
                    url.searchParams.set('date', iso);
                    window.location.href = url.toString();
                },
            };
        }

        function restaurantCatalog(config) {
            return {
                rows: config.rows,
                quantities: {},
                recurrence: {},
                until: {},
                specialRequests: [],
                openSubmit: false,
                // ── Tabs + grid filters (mirrors the consumer storefront) ──
                activeTab: 'stock',
                weeklyLabel: config.weeklyLabel || 'Wöchentlich',
                untilLabel: config.untilLabel || 'bis',
                weeklyBadge(until) {
                    let s = this.weeklyLabel;
                    if (until) {
                        const d = new Date(until + 'T00:00:00');
                        s += ' · ' + this.untilLabel + ' ' + d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
                    }
                    return s;
                },
                nameQuery: '',
                activeCategory: config.defaultShopCategory || '',
                activeSubcategoryId: 0,
                subcategoriesByCategory: config.subcategoriesByCategory || {},
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
                details: { street: config.prefill.street, postal: config.prefill.postal, city: config.prefill.city },
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
                get lineCount() { return this.basketLines.length; },
                get totalCount() { return this.lineCount + this.filledSpecials.length; },
                get hasAnything() { return this.totalCount > 0; },
                get netTotal() { return this.basketLines.reduce((s, l) => s + l.net, 0); },
                formatEur(v) { return (v || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }); },
            };
        }
    </script>
</x-shop-layout>
