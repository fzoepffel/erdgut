<x-shop-layout>
    @php
        // Mono eyebrow — prototype voice: JetBrains Mono, uppercase, 0.1em
        // tracking (Tailwind `tracking-widest`), primary green.
        $eyebrow = 'font-mono text-xs font-medium uppercase tracking-widest text-shop-primary';
        // Underline contact input — prototype style.
        $inputClass = 'mt-2 w-full border-b border-shop-border bg-transparent py-2 text-base text-shop-foreground placeholder:text-[oklch(45%_0.03_110_/_0.6)] focus:border-shop-primary focus:outline-none';

        // Build the Alpine product registry. Each orderable product carries
        // its precomputed `steps` (canonical value + server-formatted label)
        // so the [− value +] stepper can walk a fixed ladder, plus its unit
        // price for the live total. Greyed week-only products carry no
        // quantity field, so they never enter the cart.
        $alpineProducts = $products
            ->filter(fn ($row) => $row['available_on_selected'])
            ->map(function ($row) use ($quantityStep, $maxQuantity, $maxCount) {
                $product = $row['product'];
                $isCount = in_array($product->unit?->value, ['piece', 'bunch'], true);
                $step = $isCount ? 1 : (int) $quantityStep;
                $remaining = (int) floor($row['remaining']);
                $cap = $isCount
                    ? min($remaining, (int) $maxCount)
                    : min((int) (floor($remaining / $step) * $step), (int) $maxQuantity);

                $steps = [['v' => 0, 'l' => __('shop.quantity_none')]];
                for ($q = $step; $q <= $cap; $q += $step) {
                    $steps[] = ['v' => $q, 'l' => $product->displayQuantity($q)];
                }

                return [
                    'id' => $product->id,
                    'unitPrice' => (float) $product->unit_price,
                    'step' => $step,
                    'cap' => $cap,
                    'steps' => $steps,
                ];
            })->values();

        // Date-stamp in the hero corner: MONTH · YEAR · HOFLADEN.
        $stampDate = $selectedDate ?? now();
        $monthStamp = mb_strtoupper($stampDate->locale(app()->getLocale())->isoFormat('MMMM')).' · '.$stampDate->format('Y').' · HOFLADEN';

        // Selected-date summary for the picker button.
        $selectedRow = $selectedDate
            ? $availableDates->first(fn ($row) => $row['date']->toDateString() === $selectedDate->toDateString())
            : null;

        // Dropdown options grouped by ISO calendar week (KW).
        $datesByWeek = $availableDates->groupBy(fn ($row) => $row['date']->isoWeek());
    @endphp

    <div
        class="bg-shop-background text-shop-foreground"
        x-data="shopStorefront({
            products: @js($alpineProducts),
            deliveryFee: {{ (float) $deliveryFeeEur }},
            mode: {{ json_encode(old('fulfillment_mode', 'pickup')) }},
            payment: {{ json_encode(old('payment_method', 'online')) }},
            pickupLocation: {{ json_encode(old('pickup_location', '')) }},
        })"
    >
        {{-- ── Hero ──────────────────────────────────────────────────── --}}
        <section class="relative border-b border-shop-border">
            <div class="absolute right-6 top-6 hidden font-mono text-xs tracking-widest text-shop-primary lg:block">
                {{ $monthStamp }}
            </div>
            <div class="mx-auto max-w-[1100px] px-6 py-16 lg:px-12 lg:py-24">
                <p class="{{ $eyebrow }}">{{ __('shop.eyebrow') }}</p>
                <h1 class="mt-6 text-balance text-5xl leading-[1.02] tracking-[-0.025em] md:text-7xl lg:text-[5.25rem]">
                    {{ __('shop.heading_main') }} <em class="font-normal italic text-shop-primary">{{ __('shop.heading_italic') }}</em> {{ __('shop.heading_tail') }}
                </h1>
                <p class="mt-8 max-w-xl text-base leading-relaxed text-shop-muted">
                    {{ __('shop.intro') }}
                </p>
            </div>
        </section>

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
            >
                @csrf
                <input type="hidden" name="expected_delivery_date" value="{{ $selectedDate?->toDateString() }}">

                {{-- ── Delivery-date picker ──────────────────────────── --}}
                <section class="border-b border-shop-border py-14" data-testid="shop-date-picker">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p class="{{ $eyebrow }}">{{ __('shop.date_picker_label') }}</p>
                            </div>
                            <p class="max-w-md text-sm leading-relaxed text-shop-muted">{{ __('shop.date_picker_intro') }}</p>
                        </header>

                        <div class="relative max-w-xl" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                            <button
                                type="button"
                                @click="open = !open"
                                class="flex w-full items-end justify-between gap-6 border-b border-shop-foreground bg-transparent px-1 pb-3 pt-2 text-left transition hover:border-shop-primary"
                                data-testid="shop-date-button"
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
                                <svg viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-2 h-3 w-3 shrink-0 text-shop-primary transition-transform" :class="open && 'rotate-180'" aria-hidden="true">
                                    <path d="M1 1.5 6 6.5 11 1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-transition.origin.top
                                x-cloak
                                class="absolute left-0 right-0 z-20 mt-2 max-h-96 overflow-y-auto border border-shop-border bg-shop-card shadow-lg"
                                data-testid="shop-date-menu"
                            >
                                @foreach ($datesByWeek as $kw => $weekRows)
                                    <p class="sticky top-0 bg-shop-paper px-5 py-2 font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">KW {{ $kw }}</p>
                                    @foreach ($weekRows as $row)
                                        @php
                                            $iso = $row['date']->toDateString();
                                            $isSelected = $selectedDate && $selectedDate->toDateString() === $iso;
                                        @endphp
                                        <a
                                            href="{{ route('public.shop.index', ['date' => $iso]) }}"
                                            class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-shop-paper {{ $isSelected ? 'text-shop-primary' : 'text-shop-foreground' }}"
                                        >
                                            <span class="flex items-center gap-2">
                                                @if ($isSelected)
                                                    <span class="text-shop-primary" aria-hidden="true">&#9679;</span>
                                                @endif
                                                <span class="font-display text-xl">{{ $row['date']->locale(app()->getLocale())->isoFormat('dddd, D. MMMM') }}</span>
                                            </span>
                                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">
                                                {{ trans_choice('shop.date_product_count', $row['product_count'], ['count' => $row['product_count']]) }}
                                            </span>
                                        </a>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ── Products ──────────────────────────────────────── --}}
                <section class="border-b border-shop-border py-14">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p class="{{ $eyebrow }}">{{ __('shop.section_products') }}</p>
                            </div>
                            <p class="max-w-md text-sm leading-relaxed text-shop-muted">{{ __('shop.weekly_intro') }}</p>
                        </header>

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
                                        $imageUrl = $product->imageUrl();
                                        $badge = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
                                        $dateList = collect($row['available_dates'])
                                            ->map(fn ($d) => $d['date']->locale(app()->getLocale())->isoFormat('dd, D. MMM'))
                                            ->implode(' · ');
                                        $oldQty = (int) old('quantities.'.$product->id, 0);
                                    @endphp

                                    @if ($availableToday)
                                        <article class="group flex flex-col" data-testid="shop-product-card">
                                    @else
                                        <article class="group flex flex-col" data-testid="shop-product-card-unavailable">
                                    @endif
                                        <a href="{{ route('public.shop.products.show', $product) }}" class="relative block aspect-square overflow-hidden bg-shop-paper {{ $availableToday ? '' : 'grayscale' }}" target="_blank" rel="noopener">
                                            @if ($imageUrl)
                                                <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" loading="lazy" />
                                            @else
                                                <span class="flex h-full w-full items-center justify-center text-shop-muted" aria-hidden="true">
                                                    <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6h16v12H4V6z" /></svg>
                                                </span>
                                            @endif
                                            <span class="absolute left-3 top-3 bg-[oklch(96.5%_0.025_90_/_0.9)] px-2 py-1 font-mono text-[0.65rem] tracking-widest text-shop-primary">№ {{ $badge }}</span>
                                        </a>

                                        <h3 class="mt-4 font-display text-2xl leading-tight">
                                            <a href="{{ route('public.shop.products.show', $product) }}" class="underline-offset-4 hover:underline" target="_blank" rel="noopener">{{ $product->name }}</a>
                                        </h3>
                                        <div class="mt-2 h-px bg-shop-border"></div>

                                        @if ($product->producer)
                                            <a href="{{ route('public.shop.producers.show', $product->producer) }}" class="mt-3 font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline">
                                                {{ $product->producer->name }}
                                            </a>
                                        @endif
                                        <p class="mt-2 font-mono text-xs tabular-nums text-shop-muted">{{ $product->displayPrice() }}</p>

                                        <div class="mt-4">
                                            <p class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.quantity_label') }}</p>

                                            @if ($availableToday)
                                                <input type="hidden" id="shop-qty-{{ $product->id }}" name="quantities[{{ $product->id }}]" value="{{ $oldQty }}" :value="quantities[{{ $product->id }}] || 0" data-testid="shop-qty-stepper">
                                                <div class="mt-2 flex items-stretch border border-shop-border bg-shop-background">
                                                    <button
                                                        type="button"
                                                        @click="dec({{ $product->id }})"
                                                        class="w-10 py-3 font-mono text-sm text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                                        :disabled="(quantities[{{ $product->id }}] || 0) <= 0"
                                                        aria-label="−"
                                                    >−</button>
                                                    <div class="flex flex-1 items-center justify-center border-x border-shop-border py-3 font-mono text-sm tabular-nums" x-text="label({{ $product->id }})">{{ $oldQty > 0 ? $product->displayQuantity($oldQty) : __('shop.quantity_none') }}</div>
                                                    <button
                                                        type="button"
                                                        @click="inc({{ $product->id }})"
                                                        class="w-10 py-3 font-mono text-sm text-shop-foreground transition hover:text-shop-primary disabled:opacity-30"
                                                        :disabled="atCap({{ $product->id }})"
                                                        aria-label="+"
                                                    >+</button>
                                                </div>
                                                <p class="mt-2 font-mono text-xs tabular-nums text-shop-muted" x-show="(quantities[{{ $product->id }}] || 0) > 0" x-cloak>
                                                    <span x-text="money(lineTotal({{ $product->id }}))"></span>
                                                </p>
                                            @else
                                                <div class="mt-2 flex items-stretch border border-shop-border bg-shop-paper opacity-60">
                                                    <div class="w-10 py-3 text-center font-mono text-sm text-shop-muted">−</div>
                                                    <div class="flex flex-1 items-center justify-center border-x border-shop-border py-3 font-mono text-sm text-shop-muted">{{ __('shop.quantity_none') }}</div>
                                                    <div class="w-10 py-3 text-center font-mono text-sm text-shop-muted">+</div>
                                                </div>
                                                <p class="mt-2 text-xs leading-relaxed text-shop-muted" data-testid="shop-weekly-note">
                                                    <span class="font-mono uppercase tracking-widest text-shop-primary">{{ __('shop.weekly_unavailable_label') }}</span><br>
                                                    {{ __('shop.weekly_available_on', ['dates' => $dateList]) }}
                                                </p>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('items')" class="mt-4" />
                        @endif
                    </div>
                </section>

                {{-- ── Fulfillment ───────────────────────────────────── --}}
                <section class="border-b border-shop-border py-14">
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
                </section>

                {{-- ── Pickup location (pickup only) ─────────────────── --}}
                <section class="border-b border-shop-border py-14" x-show="mode === 'pickup'" x-cloak>
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
                </section>

                {{-- ── Payment (pickup only; delivery is always online) ── --}}
                <section class="border-b border-shop-border py-14" x-show="mode === 'pickup'" x-cloak>
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
                </section>

                {{-- ── Contact ───────────────────────────────────────── --}}
                <section class="border-b border-shop-border py-14">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8">
                            <p class="{{ $eyebrow }}">{{ __('shop.section_contact') }}</p>
                        </header>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('Name') }}</span>
                                <input type="text" name="name" value="{{ old('name') }}" required autocomplete="name" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('Email') }}</span>
                                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">
                                    {{ __('Phone') }}<span class="ml-2 normal-case tracking-normal text-shop-muted" x-cloak><span x-show="mode === 'delivery'">{{ __('required') }}</span><span x-show="mode !== 'delivery'">({{ __('optional') }})</span></span>
                                </span>
                                <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </label>
                        </div>
                    </div>
                </section>

                {{-- ── Delivery address (delivery only) ──────────────── --}}
                <section class="border-b border-shop-border py-14" x-show="mode === 'delivery'" x-cloak>
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <header class="mb-8">
                            <p class="{{ $eyebrow }}">{{ __('shop.section_address') }}</p>
                        </header>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <label class="block sm:col-span-2">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.delivery_street') }}</span>
                                <input type="text" name="delivery_street" value="{{ old('delivery_street') }}" autocomplete="street-address" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('delivery_street')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.delivery_postal_code') }}</span>
                                <input type="text" name="delivery_postal_code" value="{{ old('delivery_postal_code') }}" autocomplete="postal-code" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-2" />
                            </label>
                            <label class="block">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.delivery_city') }}</span>
                                <input type="text" name="delivery_city" value="{{ old('delivery_city') }}" autocomplete="address-level2" class="{{ $inputClass }}" />
                                <x-input-error :messages="$errors->get('delivery_city')" class="mt-2" />
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="font-mono text-[0.6rem] uppercase tracking-widest text-shop-muted">{{ __('shop.delivery_notes') }} <span class="normal-case tracking-normal">({{ __('optional') }})</span></span>
                                <textarea name="delivery_notes" rows="2" class="{{ $inputClass }}">{{ old('delivery_notes') }}</textarea>
                                <x-input-error :messages="$errors->get('delivery_notes')" class="mt-2" />
                            </label>
                        </div>
                    </div>
                </section>

                {{-- ── Summary + submit ──────────────────────────────── --}}
                <section class="py-14">
                    <div class="mx-auto max-w-[1100px] px-6 lg:px-12">
                        <p class="{{ $eyebrow }}">{{ __('shop.summary_heading') }}</p>
                        <div class="mt-6 border-y border-shop-border py-6">
                            <div class="flex items-baseline justify-between gap-4">
                                <span class="font-display text-2xl">{{ __('shop.summary_line_total') }}</span>
                                <span class="font-display text-4xl text-shop-primary tabular-nums" x-text="money(total)">{{ \App\Support\Money::format(0) }}</span>
                            </div>
                            <p class="mt-2 font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.summary_vat_inclusive', ['rate' => $vatRatePercent]) }}</p>
                        </div>

                        <label class="mt-10 flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-shop-muted">
                            <input type="checkbox" name="consent" value="1" @checked(old('consent')) required class="mt-1 h-4 w-4 accent-shop-primary">
                            <span>
                                {!! __('shop.consent_text', ['privacy' => '<a href="'.route('public.privacy').'" class="text-shop-foreground underline-offset-4 hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_privacy_link')).'</a>', 'terms' => '<a href="'.route('public.terms').'" class="text-shop-foreground underline-offset-4 hover:underline" target="_blank" rel="noopener">'.e(__('shop.consent_terms_link')).'</a>']) !!}
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
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-muted">{{ __('shop.cta_helper') }}</span>
                        </div>
                    </div>
                </section>
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
                    // Seed quantities from the rendered hidden inputs so a
                    // validation bounce (old() values) keeps the cart.
                    this.products.forEach((p) => {
                        const el = document.getElementById('shop-qty-' + p.id);
                        this.quantities[p.id] = el ? (parseFloat(el.value) || 0) : 0;
                    });
                    // Delivery is always paid online.
                    this.$watch('mode', (v) => { if (v === 'delivery') this.payment = 'online'; });
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
                    if (i < p.steps.length - 1) this.quantities[id] = p.steps[i + 1].v;
                },
                dec(id) {
                    const p = this.product(id);
                    if (!p) return;
                    const i = this.stepIndex(id);
                    if (i > 0) this.quantities[id] = p.steps[i - 1].v;
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
                get total() { return Math.round((this.itemsTotal + this.effectiveDeliveryFee) * 100) / 100; },
                money(v) { return v.toFixed(2).replace('.', ',') + ' €'; },
            };
        }
    </script>
</x-shop-layout>
