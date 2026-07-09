<x-shop-layout :title="__('restaurant.catalog_title')" restaurant-nav="shop">
    @php
        // Flat JS registry of every orderable grade for the basket maths. The
        // shop shows ONLY in-stock rows, so every grade has a hard cap (= stock).
        $jsRows = [];
        foreach ($products as $row) {
            foreach ($row['grades'] as $grade) {
                if (($grade['cap'] ?? 0) <= 0) {
                    continue; // this grade has no stock — not sellable in the shop
                }
                $jsRows[] = [
                    'key' => $grade['key'],
                    'productId' => $row['product']->id,
                    'variantId' => $grade['variantId'],
                    'name' => $row['product']->name.($grade['label'] ? ' · '.$grade['label'] : ''),
                    'unitSymbol' => $grade['unitSymbol'],
                    'factor' => $grade['displayFactor'],
                    'step' => $grade['step'],
                    'cap' => $grade['cap'] ?? 0,
                    'net' => $grade['netPerDisplay'],
                ];
            }
        }
    @endphp

    <div
        x-data="restaurantCatalog({
            rows: {{ Illuminate\Support\Js::from($jsRows) }},
            date: @js($selectedDate?->toDateString()),
            subcategoriesByCategory: @js($alpineSubcategories),
            defaultShopCategory: @js($defaultShopCategory),
            weeklyLabel: @js(__('restaurant.recurrence_weekly')),
            untilLabel: @js(__('restaurant.recurrence_until')),
            openEndedLabel: @js(__('restaurant.recurrence_open_ended')),
            recurrenceWeeks: {{ Illuminate\Support\Js::from($recurrenceWeeks) }},
            requestUrl: @js(route('app.restaurant.requests.index', ['date' => $selectedDate?->toDateString()])),
            reorderPrefill: {{ Illuminate\Support\Js::from($reorderPrefill) }},
            prefill: {{ Illuminate\Support\Js::from([
                'street' => $profile?->billing_street ?? '',
                'postal' => $profile?->billing_postal_code ?? '',
                'city' => $profile?->billing_city ?? '',
            ]) }},
        })"
    >
        {{-- ── Delivery-day picker ─────────────────────────────────────── --}}
        <section class="border-b border-shop-border py-10" data-testid="restaurant-date-picker"
                 x-data="restaurantDateFilter({
                     allDates: @js($alpineDates),
                     selectedIso: @js($selectedDate?->toDateString() ?? ''),
                     shopUrl: @js(route('app.restaurant.catalog')),
                     availableSuffix: @js(__('restaurant.date_in_stock_suffix')),
                     availableSuffixOne: @js(__('restaurant.date_in_stock_suffix_one')),
                     onRequestLabel: @js(__('restaurant.date_on_request_only')),
                 })">
            <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
                <p class="eyebrow text-shop-primary">{{ __('restaurant.catalog_eyebrow') }}</p>
                <h1 class="mt-2 font-display text-3xl text-shop-foreground sm:text-4xl">{{ __('restaurant.catalog_title') }}</h1>

                @if ($selectableDates->isEmpty())
                    <div class="shop-card mt-8 p-8 text-center" data-testid="restaurant-no-stock-days">
                        <p class="text-shop-muted">{{ __('restaurant.no_stock_days') }}</p>
                        @if ($hasOpenDays)
                            <a href="{{ route('app.restaurant.requests.index') }}"
                               class="mt-4 inline-block border border-shop-primary px-5 py-2 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary transition hover:bg-shop-primary hover:text-shop-primary-foreground">{{ __('restaurant.teaser_cta') }} &rarr;</a>
                        @endif
                    </div>
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
            {{-- ── Filters + in-stock grid ─────────────────────────────── --}}
            <section class="py-10">
                <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
                    {{-- Reorder handoff: the past order's lines are preloaded into
                         the basket; checkout is the confirmation step. --}}
                    <div x-show="reorderOrderId" x-cloak data-testid="restaurant-reorder-banner"
                         class="mb-6 border-l-2 border-shop-primary bg-shop-paper px-4 py-3 text-sm text-shop-foreground">
                        <span x-text="reorderBanner"></span>
                        <span x-show="reorderMissingNames.length > 0" x-cloak class="block pt-1 text-amber-800" x-text="reorderMissingText"></span>
                        <template x-if="reorderBetterDate">
                            <a :href="reorderBetterDate.url" data-testid="restaurant-reorder-better-date"
                               class="mt-1 inline-block font-medium text-shop-primary underline underline-offset-4 hover:no-underline"
                               x-text="reorderBetterDateText"></a>
                        </template>
                    </div>
                    @if ($products->isNotEmpty())
                        <div class="space-y-4">
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
                    @endif

                    <div class="mt-10 grid grid-cols-1 gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3 items-start">
                        @foreach ($products as $row)
                            @php
                                $product = $row['product'];
                                $imageUrl = $product->thumbUrl();
                            @endphp
                            <div x-show="cardVisible({{ json_encode(mb_strtolower($product->name)) }}, '{{ $product->category }}', {{ $product->subcategory_id ?? 0 }})"
                                 data-testid="restaurant-catalog-item" data-product-id="{{ $product->id }}">
                                <article class="group flex flex-col">
                                    <a href="{{ route('public.shop.products.show', $product) }}" class="relative block aspect-square overflow-hidden bg-shop-paper">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="{{ $product->name }}" width="600" height="600" loading="lazy" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" />
                                        @else
                                            <span class="flex h-full w-full items-center justify-center text-shop-muted" aria-hidden="true">
                                                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
                                            </span>
                                        @endif
                                    </a>

                                    <h3 class="mt-4 font-display text-2xl leading-tight">
                                        <a href="{{ route('public.shop.products.show', $product) }}" class="underline-offset-4 hover:underline">{{ $product->name }}</a>
                                    </h3>
                                    <div class="mt-2 h-px bg-shop-border"></div>
                                    @if ($product->producer)
                                        <p class="mt-3 font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary">{{ $product->producer->name }}</p>
                                    @endif

                                    {{-- Grade rows: net price + capped quantity dropdown + recurrence.
                                         Grades without stock are not sellable here. --}}
                                    <div class="mt-4 space-y-3">
                                        @foreach (array_filter($row['grades'], fn ($g) => ($g['cap'] ?? 0) > 0) as $grade)
                                            <div class="border-t border-shop-border/60 pt-3 first:border-t-0 first:pt-0">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="min-w-0">
                                                        @if ($grade['label'])<p class="truncate text-sm text-shop-foreground">{{ $grade['label'] }}</p>@endif
                                                        <p class="font-mono text-sm tabular-nums text-shop-foreground">@eur($grade['netPerDisplay'])<span class="text-xs text-shop-muted">/{{ $grade['unitSymbol'] }} {{ __('restaurant.plus_vat_suffix') }}</span></p>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-1">
                                                        <button type="button" @click="dec(@js($grade['key']))" class="h-9 w-9 border border-shop-border text-shop-foreground transition hover:border-shop-primary" aria-label="−">−</button>
                                                        {{-- Quantity dropdown, hard-capped at what's in stock. --}}
                                                        <div class="relative h-9 w-28 border border-shop-border bg-shop-background">
                                                            <div class="pointer-events-none flex h-full w-full items-center justify-between gap-1 px-2 text-sm tabular-nums text-shop-foreground">
                                                                <span x-text="displayValue(@js($grade['key'])) || '–'"></span>
                                                                <svg class="h-3 w-3 shrink-0 text-shop-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                                                            </div>
                                                            <select x-data="{ optsOpen: false }"
                                                                    @pointerdown="optsOpen = true" @focus="optsOpen = true"
                                                                    aria-label="{{ __('restaurant.special_qty') }}"
                                                                    data-testid="restaurant-qty-{{ $grade['key'] }}"
                                                                    :value="String(quantities[@js($grade['key'])] || 0)"
                                                                    @change="quantities[@js($grade['key'])] = +$event.target.value"
                                                                    class="absolute inset-0 w-full cursor-pointer appearance-none border-0 bg-transparent opacity-0 focus:outline-none">
                                                                <option value="0">–</option>
                                                                <template x-for="o in (optsOpen ? qtyOptions(@js($grade['key'])) : [])" :key="o.v">
                                                                    <option :value="String(o.v)" x-text="o.l" :selected="(quantities[@js($grade['key'])] || 0) === o.v"></option>
                                                                </template>
                                                            </select>
                                                        </div>
                                                        <button type="button" @click="inc(@js($grade['key']))" class="h-9 w-9 border border-shop-border text-shop-foreground transition hover:border-shop-primary" aria-label="+">+</button>
                                                        <span class="w-7 text-xs text-shop-muted">{{ $grade['unitSymbol'] }}</span>
                                                    </div>
                                                </div>
                                                {{-- At the stock cap: requesting more is a deliberate step into the Anfragen area. --}}
                                                <p x-show="atCap(@js($grade['key']))" x-cloak class="mt-1 text-[0.65rem] text-shop-muted" data-testid="restaurant-cap-hint-{{ $grade['key'] }}">
                                                    {{ __('restaurant.cap_reached') }}
                                                    <a :href="requestUrl + '&product={{ $product->id }}&qty=' + (quantities[@js($grade['key'])] || 0)" class="font-medium text-shop-primary underline underline-offset-2">{{ __('restaurant.cap_request_link') }}</a>
                                                </p>
                                                {{-- Per-product recurrence, surfaced the moment a quantity is added --}}
                                                <div x-show="(quantities[@js($grade['key'])] || 0) > 0" x-cloak
                                                     class="mt-2 flex flex-wrap items-center gap-2 border-l-2 border-shop-primary/40 pl-3 text-xs">
                                                    <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.recurrence_label') }}</span>
                                                    {{-- "Wöchentlich" is only offered while a future week still has
                                                         stock — the shop never sells a schedule it can't see. --}}
                                                    <select x-model="recurrence[@js($grade['key'])]"
                                                            @change="onRecurrenceChange(@js($grade['key']))"
                                                            class="border border-shop-border bg-shop-background py-1 pl-2 pr-7 text-xs text-shop-foreground focus:border-shop-primary focus:outline-none">
                                                        <option value="one_time">{{ __('restaurant.recurrence_one_time') }}</option>
                                                        <template x-if="weeksFor(@js($grade['key'])).length > 0">
                                                            <option value="weekly">{{ __('restaurant.recurrence_weekly') }}</option>
                                                        </template>
                                                    </select>
                                                    {{-- End of the weekly delivery: a WEEK picker, coloured by stock. --}}
                                                    <div x-show="recurrence[@js($grade['key'])] === 'weekly'" class="flex items-center gap-1 text-shop-muted"
                                                         x-data="{ wkOpen: false }" @keydown.escape="wkOpen = false" @click.outside="wkOpen = false">
                                                        <span>{{ __('restaurant.recurrence_until') }}</span>
                                                        <div class="relative">
                                                            <button type="button" @click="wkOpen = !wkOpen" :aria-expanded="wkOpen"
                                                                    data-testid="restaurant-until-{{ $grade['key'] }}"
                                                                    class="border border-shop-border bg-shop-background px-2 py-0.5 text-xs text-shop-foreground hover:border-shop-primary"
                                                                    x-text="untilLabelFor(@js($grade['key']))"></button>
                                                            <div x-show="wkOpen" x-cloak x-transition.origin.top
                                                                 class="absolute left-0 z-30 mt-1 max-h-64 w-56 overflow-y-auto border border-shop-border bg-shop-card shadow-lg">
                                                                <template x-for="w in weeksFor(@js($grade['key']))" :key="w.iso">
                                                                    <button type="button" @click="setUntil(@js($grade['key']), w.iso); wkOpen = false"
                                                                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs hover:bg-shop-paper"
                                                                            :class="until[@js($grade['key'])] === w.iso ? 'bg-shop-paper' : ''">
                                                                        <span class="text-shop-foreground" x-text="'KW ' + w.kw + ' · ' + w.label"></span>
                                                                        <span class="font-medium" :class="weekInStock(@js($grade['key']), w) ? 'text-emerald-700' : 'text-amber-700'"
                                                                              x-text="weekInStock(@js($grade['key']), w) ? @js(__('restaurant.week_legend_in_stock')) : @js(__('restaurant.week_legend_on_request'))"></span>
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                        <span class="ml-1 text-[0.6rem] text-shop-muted">
                                                            <span class="text-emerald-700">&#9679;</span> {{ __('restaurant.week_legend_in_stock') }}
                                                            · <span class="text-amber-700">&#9679;</span> {{ __('restaurant.week_legend_on_request') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            </div>
                        @endforeach

                        @if ($products->isEmpty())
                            <div class="shop-card col-span-full p-8 text-center" data-testid="restaurant-stock-empty">
                                <p class="text-shop-muted">{{ __('restaurant.tab_in_stock_empty') }}</p>
                                @if ($seasonCount > 0)
                                    <a href="{{ route('app.restaurant.requests.index', ['date' => $selectedDate?->toDateString()]) }}"
                                       class="mt-4 inline-block border border-shop-primary px-5 py-2 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary transition hover:bg-shop-primary hover:text-shop-primary-foreground">{{ __('restaurant.teaser_cta') }} &rarr;</a>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- ── In-Saison teaser: discovery without diluting the shop ── --}}
                    @if ($seasonCount > 0)
                        <div class="mt-14 flex flex-wrap items-center justify-between gap-4 border-l-2 border-amber-400 bg-shop-paper px-5 py-4" data-testid="restaurant-season-teaser">
                            <p class="text-sm text-shop-foreground">{{ trans_choice('restaurant.teaser_season', $seasonCount, ['count' => $seasonCount]) }}</p>
                            <a href="{{ route('app.restaurant.requests.index', ['date' => $selectedDate?->toDateString()]) }}"
                               class="shrink-0 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">{{ __('restaurant.teaser_cta') }} &rarr;</a>
                        </div>
                    @endif
                </div>
            </section>

            <div aria-hidden="true" class="h-24"></div>

            {{-- Sticky basket bar (summary click expands the mini cart) --}}
            <div x-show="hasAnything" x-cloak
                 class="fixed inset-x-0 bottom-0 z-30 border-t border-shop-border bg-shop-background shadow-[0_-4px_12px_rgba(0,0,0,0.06)]">
                {{-- Mini cart --}}
                <div x-show="miniCartOpen" x-cloak x-transition.origin.bottom class="border-b border-shop-border/60">
                    <ul class="mx-auto max-w-[1100px] divide-y divide-shop-border/50 px-4 py-2 text-sm sm:px-6 lg:px-12">
                        <template x-for="line in basketLines" :key="'m'+line.key">
                            <li class="flex items-center justify-between gap-3 py-1.5">
                                <span class="min-w-0 truncate text-shop-foreground" x-text="`${line.displayQty} ${line.unitSymbol} · ${line.name}`"></span>
                                <span class="flex shrink-0 items-center gap-3">
                                    <span class="font-mono text-shop-muted" x-text="formatEur(line.net)"></span>
                                    <button type="button" @click="quantities[line.key] = 0" class="text-shop-muted transition hover:text-chapter-meat" aria-label="&times;">&times;</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
                <div class="mx-auto flex max-w-[1100px] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-12">
                    <button type="button" @click="miniCartOpen = !miniCartOpen" class="text-left text-sm text-shop-foreground" data-testid="restaurant-mini-cart-toggle">
                        <span x-text="lineCount"></span> <span x-text="lineCount === 1 ? @js(__('restaurant.cart_line')) : @js(__('restaurant.cart_lines'))"></span>
                        · <span class="font-mono" x-text="formatEur(netTotal)"></span> <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span>
                        <svg class="ml-1 inline h-3 w-3 text-shop-primary transition-transform" :class="miniCartOpen && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 15l-6-6-6 6"/></svg>
                        <span class="ml-2 hidden text-xs text-emerald-700 sm:inline">{{ __('restaurant.cart_trust_line') }}</span>
                    </button>
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

                    <h2 class="font-display text-2xl text-shop-foreground">{{ __('restaurant.cart_review_title') }}</h2>
                    {{-- Weekly orders read as a schedule, not a single date. --}}
                    <p class="mt-1 text-sm text-shop-foreground" x-text="deliveryLineText()"></p>
                    <p class="mt-1 text-sm text-emerald-700">{{ __('restaurant.cart_trust_line') }}</p>

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
                        <li class="flex justify-between gap-3 py-2 font-semibold text-shop-foreground">
                            <span>{{ __('restaurant.order_net_total') }}</span>
                            <span class="font-mono" x-text="formatEur(netTotal)"></span>
                        </li>
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

                    {{-- No payment step: make the commitment explicit — the click places
                         a binding order that is billed by invoice after delivery. --}}
                    <p class="mt-5 border-t border-shop-border pt-4 text-xs leading-relaxed text-shop-muted">{{ __('restaurant.cart_payment_note') }}</p>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <button type="button" @click="openSubmit = false" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted hover:text-shop-primary">{{ __('restaurant.cart_back') }}</button>
                        <button type="submit" data-testid="restaurant-cart-submit" class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                            {{ __('restaurant.cart_submit_binding') }}
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
                availableSuffixOne: config.availableSuffixOne || config.availableSuffix,
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
                    if (d.inStockCount <= 0) return this.onRequestLabel;
                    return d.inStockCount + ' ' + (d.inStockCount === 1 ? this.availableSuffixOne : this.availableSuffix);
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
                openSubmit: {{ $errors->any() ? 'true' : 'false' }},
                miniCartOpen: false,

                // ── Reorder handoff (misclick-safe "Nochmal bestellen") ────
                reorderOrderId: null,
                reorderMissingNames: [],
                reorderBetterDate: null,
                reorderBanner: '',
                reorderMissingText: '',
                reorderBetterDateText: '',
                reorderBannerTemplate: @js(__('restaurant.reorder_banner')),
                reorderMissingTemplate: @js(__('restaurant.reorder_banner_missing')),
                reorderBetterAllTemplate: @js(__('restaurant.reorder_better_date_all')),
                reorderBetterPartialTemplate: @js(__('restaurant.reorder_better_date_partial')),
                init() {
                    const pre = config.reorderPrefill;
                    if (!pre || !Array.isArray(pre.lines)) return;
                    this.reorderOrderId = pre.orderId;
                    for (const line of pre.lines) {
                        const r = this.rowFor(line.key);
                        if (!r || r.cap <= 0) continue;
                        const snapped = Math.max(r.step, Math.round(line.qty / r.step) * r.step);
                        this.quantities[line.key] = Math.min(snapped, r.cap);
                    }
                    this.reorderBanner = this.reorderBannerTemplate.replace(':id', pre.orderId);
                    // Which lines the day can't cover (and a better day, if any)
                    // is computed server-side from the same caps used above.
                    this.reorderMissingNames = pre.missingNames || [];
                    this.reorderMissingText = this.reorderMissingTemplate.replace(':names', this.reorderMissingNames.join(', '));
                    this.reorderBetterDate = pre.betterDate || null;
                    if (this.reorderBetterDate) {
                        const b = this.reorderBetterDate;
                        this.reorderBetterDateText = (b.all ? this.reorderBetterAllTemplate : this.reorderBetterPartialTemplate)
                            .replace(':date', b.label)
                            .replace(':available', b.available)
                            .replace(':total', b.total);
                    }
                },
                requestUrl: config.requestUrl || '#',
                weeklyLabel: config.weeklyLabel || 'Wöchentlich',
                untilLabel: config.untilLabel || 'bis',
                deliveryDateLabel: @js($selectedDate?->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') ?? ''),
                deliveryWeekdayLabel: @js($selectedDate?->locale(app()->getLocale())->isoFormat('dddd') ?? ''),
                deliverOnPrefix: @js(__('restaurant.checkout_delivery_on')),
                deliverEveryTemplate: @js(__('restaurant.checkout_delivery_every')),
                deliverMixedTemplate: @js(__('restaurant.checkout_delivery_mixed')),
                deliveryLineText() {
                    const lines = this.basketLines;
                    const weekly = lines.filter((l) => l.recurrence === 'weekly').length;
                    if (weekly > 0 && weekly === lines.length) {
                        return this.deliverEveryTemplate.replace(':weekday', this.deliveryWeekdayLabel).replace(':date', this.deliveryDateLabel);
                    }
                    if (weekly > 0) {
                        return this.deliverMixedTemplate.replace(':date', this.deliveryDateLabel).replace(':weekday', this.deliveryWeekdayLabel);
                    }
                    return this.deliverOnPrefix + ' ' + this.deliveryDateLabel;
                },
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
                rowFor(key) { return this.rows.find(r => r.key === key); },
                // The shop sells only what's in stock — the cap is a HARD max.
                clamp(key, q) {
                    const r = this.rowFor(key);
                    if (!r) return 0;
                    q = Math.max(0, Math.round(q / r.step) * r.step);
                    if (q > r.cap) q = r.cap;
                    return q;
                },
                atCap(key) {
                    const r = this.rowFor(key);
                    return !!r && r.cap > 0 && (this.quantities[key] || 0) >= r.cap;
                },
                // Dropdown options: fine steps first (max 20 rows), then 1-unit
                // and 5-unit coarsening up to the in-stock cap. Never beyond stock.
                qtyOptions(key) {
                    const r = this.rowFor(key);
                    if (!r) return [];
                    const fmt = (q) => (q / r.factor).toLocaleString('de-DE', { maximumFractionDigits: 3 }) + ' ' + r.unitSymbol;
                    const values = new Set();
                    for (let v = r.step; v <= Math.min(r.cap, 20 * r.step); v += r.step) values.add(v);
                    const unit = Math.max(r.factor, r.step);
                    for (let v = unit; v <= Math.min(r.cap, 20 * r.factor); v += unit) values.add(v);
                    for (let v = 5 * r.factor; v <= r.cap; v += 5 * r.factor) values.add(v);
                    values.add(r.cap);
                    const out = [...values].filter((v) => v > 0 && v <= r.cap).sort((a, b) => a - b).slice(0, 400).map((v) => ({ v, l: fmt(v) }));
                    const cur = this.quantities[key] || 0;
                    if (cur > 0 && !out.some((o) => o.v === cur)) {
                        out.push({ v: cur, l: fmt(cur) });
                        out.sort((a, b) => a.v - b.v);
                    }
                    return out;
                },
                // ── Weekly "until" week picker ─────────────────────────────
                recurrenceWeeks: config.recurrenceWeeks || [],
                openEndedLabel: config.openEndedLabel || 'Unbegrenzt',
                // The picker ends at the LAST week this product still has stock —
                // the shop never offers an end date beyond known stock. (Weeks in
                // between may still be amber when stock is short of the quantity.)
                weeksFor(key) {
                    const r = this.rowFor(key);
                    if (!r) return [];
                    let last = -1;
                    this.recurrenceWeeks.forEach((w, i) => {
                        if (((w.remainingByProduct && w.remainingByProduct[r.productId]) || 0) > 0) last = i;
                    });
                    return this.recurrenceWeeks.slice(0, last + 1);
                },
                setUntil(key, iso) { this.until[key] = iso; },
                // The shop only sells while stock is known: switching a line to
                // weekly defaults the end to the LAST stocked week.
                onRecurrenceChange(key) {
                    if (this.recurrence[key] === 'weekly' && !this.until[key]) {
                        const weeks = this.weeksFor(key);
                        if (weeks.length) this.until[key] = weeks[weeks.length - 1].iso;
                    }
                },
                weekInStock(key, w) {
                    const r = this.rowFor(key);
                    if (!r) return false;
                    const remaining = (w.remainingByProduct && w.remainingByProduct[r.productId]) || 0;
                    return remaining >= (this.quantities[key] || 0) && remaining > 0;
                },
                untilLabelFor(key) {
                    const iso = this.until[key] || '';
                    const w = this.recurrenceWeeks.find((x) => x.iso === iso);
                    if (w) return 'KW ' + w.kw + ' · ' + w.label;
                    const weeks = this.weeksFor(key);
                    return weeks.length ? ('KW ' + weeks[weeks.length - 1].kw + ' · ' + weeks[weeks.length - 1].label) : this.openEndedLabel;
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
                            // Weekly lines always end at a known-stock week — never open-ended.
                            let until = '';
                            if (rec === 'weekly') {
                                until = this.until[r.key] || '';
                                if (!until) {
                                    const weeks = this.weeksFor(r.key);
                                    until = weeks.length ? weeks[weeks.length - 1].iso : '';
                                }
                            }
                            return {
                                i: i++, key: r.key, productId: r.productId, variantId: r.variantId,
                                quantity: q, name: r.name, unitSymbol: r.unitSymbol,
                                recurrence: rec, until,
                                displayQty: (q / r.factor).toLocaleString('de-DE', { maximumFractionDigits: 3 }),
                                net: Math.round((q / r.factor) * r.net * 100) / 100,
                            };
                        });
                },
                get lineCount() { return this.basketLines.length; },
                get hasAnything() { return this.lineCount > 0; },
                get netTotal() { return this.basketLines.reduce((s, l) => s + l.net, 0); },
                formatEur(v) { return (v || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR' }); },
            };
        }
    </script>
</x-shop-layout>
