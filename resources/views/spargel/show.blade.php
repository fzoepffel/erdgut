<x-spargel-layout :title="__('spargel.page_title')">
    @php
        // Two possible delivery dates — the banner switches between them
        // based on the variety mix the customer picks (green has a stricter
        // Thursday cutoff; white may use Sunday). Pre-format both as
        // localized labels so we don't have to do any date math in JS.
        $whiteOnlyDateLabel = $whiteOnlyMonday->locale(app()->getLocale())->isoFormat('dddd, D. MMMM');
        $greenInCartDateLabel = $greenInCartMonday->locale(app()->getLocale())->isoFormat('dddd, D. MMMM');
        $whiteWindowMain = __('spargel.delivery_window_main', ['date' => $whiteOnlyDateLabel]);
        $greenWindowMain = __('spargel.delivery_window_main', ['date' => $greenInCartDateLabel]);
        // True when today is past the green cutoff: a mixed cart shifts
        // from the closer Monday to a week later. Used to surface an
        // inline notice under the variety selection so the customer
        // sees the consequence while picking quantities.
        $cartShiftsForGreen = $whiteOnlyMonday->toDateString() !== $greenInCartMonday->toDateString();
        $mixedShiftNotice = __('spargel.mixed_shift_notice', [
            'earlier' => $whiteOnlyDateLabel,
            'later' => $greenInCartDateLabel,
        ]);
    @endphp

    {{-- Hero block: editorial heading on the brand-cream page bg. --}}
    <header class="mb-8 sm:mb-10">
        <p class="eyebrow text-chapter-produce">{{ __('spargel.eyebrow_trial_shop') }}</p>
        <h1 class="mt-3 max-w-2xl font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
            {{ __('spargel.heading') }}
        </h1>
        <p class="mt-4 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75 sm:text-lg">
            {{ __('spargel.intro') }}
        </p>
    </header>

    {{-- Trust strip: three small inline reassurances directly under the hero.
         Restraint over chapter-colour: a single produce-green tone for the
         icons keeps the strip feeling like one calm row, not a paint-chip
         catalogue. --}}
    <ul class="mb-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-brand-ink/85 sm:mb-12" data-testid="spargel-trust-strip">
        @foreach (['bio', 'fresh', 'farm'] as $badge)
            <li class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-chapter-produce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ __('spargel.trust_'.$badge) }}</span>
            </li>
        @endforeach
    </ul>

    {{-- Producer intro, surfaced before the order form so the customer
         knows who grew it and how before deciding on quantity. The full
         editorial story stays at the bottom; we link there from here so
         the customer can dive deeper without scrolling blindly. --}}
    <section
        class="mb-10 overflow-hidden border border-brand-ink/15 bg-brand-paper sm:mb-14"
        aria-labelledby="spargel-farm-facts-heading"
        data-testid="spargel-farm-facts"
    >
        <div class="grid gap-0 sm:grid-cols-[1fr_minmax(0,260px)] sm:items-stretch">
            <div class="p-6 sm:p-8">
                <p class="eyebrow text-chapter-produce">{{ __('spargel.farm_facts_eyebrow') }}</p>
                <h2 id="spargel-farm-facts-heading" class="mt-2 font-display text-2xl text-brand-ink sm:text-3xl">
                    {{ __('spargel.farm_facts_heading') }}
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/80">
                    {{ __('spargel.farm_facts_intro') }}
                </p>
                <ul class="mt-5 space-y-2 text-sm text-brand-ink/85">
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="mt-2 h-1 w-3 shrink-0 bg-chapter-produce/70"></span>
                        <span>{{ __('spargel.farm_facts_bullet_farmer') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="mt-2 h-1 w-3 shrink-0 bg-chapter-produce/70"></span>
                        <span>{{ __('spargel.farm_facts_bullet_location') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="mt-2 h-1 w-3 shrink-0 bg-chapter-produce/70"></span>
                        <span>{{ __('spargel.farm_facts_bullet_field') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="mt-2 h-1 w-3 shrink-0 bg-chapter-produce/70"></span>
                        <span>{{ __('spargel.farm_facts_bullet_method') }}</span>
                    </li>
                    <li class="flex gap-3">
                        <span aria-hidden="true" class="mt-2 h-1 w-3 shrink-0 bg-chapter-produce/70"></span>
                        <span>{{ __('spargel.farm_facts_bullet_freshness') }}</span>
                    </li>
                </ul>
                <a
                    href="#spargel-story"
                    class="mt-5 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-chapter-produce underline-offset-4 hover:underline"
                    data-testid="spargel-farm-facts-jump"
                >
                    {{ __('spargel.farm_facts_jump_to_story') }}
                    <span aria-hidden="true">↓</span>
                </a>
            </div>
            <div class="overflow-hidden">
                <img
                    src="{{ asset('images/spargel/farmer-max.jpg') }}"
                    alt="{{ __('spargel.farm_facts_photo_alt') }}"
                    class="h-56 w-full object-cover sm:h-full"
                    loading="lazy"
                />
            </div>
        </div>
    </section>

    {{-- Delivery date banner. Neutral paper background — this is
         informational, not a decision card; chapter colour would
         compete with the form below. --}}
    <div
        class="border border-brand-ink/15 bg-brand-paper p-5 text-sm leading-relaxed text-brand-ink"
        x-data="{
            green: parseInt({{ json_encode((string) old('quantity_green_grams', '0')) }}, 10) || 0,
            white: parseInt({{ json_encode((string) old('quantity_white_grams', '500')) }}, 10) || 0,
            get whiteOnly() { return this.green === 0 && this.white > 0; },
            get windowMain() {
                return this.whiteOnly
                    ? {{ json_encode($whiteWindowMain) }}
                    : {{ json_encode($greenWindowMain) }};
            },
        }"
        x-init="
            $watch('green', () => {});
            $watch('white', () => {});
            // Mirror the quantity selects in the form below so the banner
            // updates as the customer changes them.
            document.addEventListener('input', (ev) => {
                if (ev.target?.name === 'quantity_green_grams') {
                    green = parseInt(ev.target.value, 10) || 0;
                }
                if (ev.target?.name === 'quantity_white_grams') {
                    white = parseInt(ev.target.value, 10) || 0;
                }
            });
        "
    >
        <p class="eyebrow text-chapter-produce">{{ __('spargel.delivery_window_eyebrow') }}</p>
        <p class="mt-1 font-display text-base text-brand-ink sm:text-lg" x-text="windowMain">
            {{ $greenWindowMain }}
        </p>
        <p class="mt-1 text-xs text-brand-ink/65">{{ __('spargel.delivery_window_rule') }}</p>
    </div>

    <form
        id="spargel-order-form"
        method="POST"
        action="{{ route('public.spargel.store') }}"
        class="mt-10 space-y-10"
        data-testid="spargel-form"
        x-data="{
            green: parseInt({{ json_encode((string) old('quantity_green_grams', '0')) }}, 10) || 0,
            white: parseInt({{ json_encode((string) old('quantity_white_grams', '500')) }}, 10) || 0,
            mode: {{ json_encode(old('fulfillment_mode', 'pickup')) }},
            payment: {{ json_encode(old('payment_method', 'online')) }},
            pickupLocation: {{ json_encode(old('pickup_location', '')) }},
            pricePerKg: {{ (float) $pricePerKgEur }},
            deliveryFee: {{ (float) $deliveryFeeEur }},
            deliveryMin: {{ (int) $deliveryMinGrams }},
            confirming: false,
            get totalGrams() { return (this.green || 0) + (this.white || 0); },
            get deliveryAllowed() { return this.totalGrams >= this.deliveryMin; },
            get effectiveMode() { return this.deliveryAllowed ? this.mode : 'pickup'; },
            get effectiveDeliveryFee() { return this.effectiveMode === 'delivery' ? this.deliveryFee : 0; },
            get effectivePayment() { return this.effectiveMode === 'delivery' ? 'online' : this.payment; },
            get lineTotal() { return Math.round((this.totalGrams / 1000) * this.pricePerKg * 100) / 100; },
            get total() { return Math.round((this.lineTotal + this.effectiveDeliveryFee) * 100) / 100; },
            money(v) { return v.toFixed(2).replace('.', ',') + ' €'; },
        }"
        x-init="
            if (!deliveryAllowed) mode = 'pickup';
            // When switching to delivery, force payment back to online —
            // pay-in-person only makes sense when the customer collects.
            $watch('mode', value => { if (value === 'delivery') payment = 'online'; });
            $watch('green', () => { if (!deliveryAllowed) mode = 'pickup'; });
            $watch('white', () => { if (!deliveryAllowed) mode = 'pickup'; });
        "
    >
        @csrf

        @php
            // Boxed-input class — same shape on every text input so the
            // form reads as one consistent surface.
            $inputClass = 'mt-2 block w-full border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0';
        @endphp

        {{-- Sorte + Menge: each variety gets its own card. Single accent
             colour (chapter-produce) for the active state — the image
             at the top differentiates white vs green, the active border
             tells you what's in the cart. No full-colour-block flooding
             so the page stays calm as later sections also light up. --}}
        @php
            // White first — Max has more white available right now, so we
            // present it as the headline variety. Green follows.
            $varieties = [
                ['key' => 'white', 'image' => 'spargel-white.jpg', 'index' => '01', 'state' => 'white'],
                ['key' => 'green', 'image' => 'spargel-green.jpg', 'index' => '02', 'state' => 'green'],
            ];
            $oldGreen = (int) old('quantity_green_grams', 0);
            $oldWhite = (int) old('quantity_white_grams', 500);
        @endphp
        <fieldset class="space-y-4">
            <legend class="eyebrow text-chapter-produce">{{ __('spargel.section_product') }}</legend>
            <p class="text-sm text-brand-ink/70">{{ __('spargel.section_product_hint') }}</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($varieties as $v)
                    @php
                        $stateVar = $v['state'];
                        $field = 'quantity_'.$v['key'].'_grams';
                        $current = $v['key'] === 'green' ? $oldGreen : $oldWhite;
                    @endphp
                    <div
                        class="group relative flex flex-col overflow-hidden border bg-white transition"
                        :class="{{ $stateVar }} > 0
                            ? 'border-chapter-produce ring-1 ring-chapter-produce'
                            : 'border-brand-ink/15 hover:border-brand-ink/40'"
                    >
                        <div class="aspect-[5/3] w-full overflow-hidden">
                            <img
                                src="{{ asset('images/spargel/'.$v['image']) }}"
                                alt=""
                                aria-hidden="true"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                        <div class="flex flex-col gap-2 p-5">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="eyebrow text-brand-ink/55">{{ __('spargel.type_'.$v['key']) }}</p>
                                <span aria-hidden="true" class="font-display text-sm font-semibold text-brand-ink/45">{{ $v['index'] }}</span>
                            </div>
                            <p class="font-display text-2xl font-semibold leading-tight text-brand-ink">
                                {{ __('spargel.type_'.$v['key']) }}
                            </p>
                            <p class="font-editorial text-sm italic leading-relaxed text-brand-ink/70">
                                {{ __('spargel.type_'.$v['key'].'_hint') }}
                            </p>

                            <div class="mt-4 flex items-center gap-2">
                                <label
                                    for="spargel-{{ $v['key'] }}-grams"
                                    class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink/65"
                                >{{ __('spargel.quantity_label') }}</label>
                                <select
                                    id="spargel-{{ $v['key'] }}-grams"
                                    name="{{ $field }}"
                                    x-model.number="{{ $stateVar }}"
                                    class="border border-brand-ink/30 bg-white py-2 pl-3 pr-8 text-sm text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                                    data-testid="spargel-{{ $v['key'] }}-grams"
                                >
                                    @foreach ($quantityOptions as $g)
                                        <option value="{{ $g }}" @selected($current === $g)>
                                            {{ $g === 0 ? '0 '.__('spargel.unit_grams_short').' — '.__('spargel.quantity_none') : $g.' '.__('spargel.unit_grams_short') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($cartShiftsForGreen)
                {{-- Inline shift notice: only relevant Fri–Sun, when today
                     is past the green cutoff. Tells the customer that a
                     mixed cart pushes the whole order to the Monday a
                     week later, so they can decide consciously between
                     "white-only this week" and "both, next week". --}}
                <div
                    x-show="green > 0 && white > 0"
                    x-cloak
                    class="border border-chapter-meat/40 bg-chapter-meat/[0.05] p-4 text-sm leading-relaxed text-brand-ink"
                    role="status"
                    data-testid="spargel-mixed-shift-notice"
                >
                    <p class="eyebrow text-chapter-meat">{{ __('spargel.mixed_shift_eyebrow') }}</p>
                    <p class="mt-1">{{ $mixedShiftNotice }}</p>
                </div>
            @endif

            <p class="text-xs text-brand-ink/60">
                {{ __('spargel.quantity_hint', ['price' => number_format($pricePerKgEur, 2, ',', '.'), 'step' => $quantityStepGrams]) }}
            </p>
            <p class="text-xs text-brand-ink/60">
                {{ __('spargel.portion_hint') }}
            </p>
            <x-input-error :messages="$errors->get('quantity_green_grams')" class="mt-2" />
            <x-input-error :messages="$errors->get('quantity_white_grams')" class="mt-2" />
        </fieldset>

        {{-- Fulfillment, pickup-location, payment all use the same single-
             accent pattern as the variety cards above: white card with
             a chapter-produce ring when selected, neutral border otherwise.
             Disabled state for delivery-when-too-light stays muted-grey. --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-chapter-produce">{{ __('spargel.section_fulfillment') }}</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label
                    class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                    :class="mode === 'pickup'
                        ? 'border-chapter-produce ring-1 ring-chapter-produce'
                        : 'border-brand-ink/15 hover:border-brand-ink/40'"
                >
                    <input
                        type="radio"
                        name="fulfillment_mode"
                        value="pickup"
                        x-model="mode"
                        class="sr-only"
                        @if (old('fulfillment_mode', 'pickup') === 'pickup') checked @endif
                    >
                    <span class="flex flex-col gap-1">
                        <span class="eyebrow text-brand-ink/55">{{ __('spargel.fulfillment_pickup_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.fulfillment_pickup') }}</span>
                        <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('spargel.fulfillment_pickup_hint') }}</span>
                    </span>
                </label>
                <label
                    class="flex items-start gap-3 border bg-white p-5 transition"
                    :class="!deliveryAllowed
                        ? 'cursor-not-allowed border-brand-ink/15 bg-brand-ink/[0.03] text-brand-ink/45'
                        : (effectiveMode === 'delivery'
                            ? 'cursor-pointer border-chapter-produce ring-1 ring-chapter-produce text-brand-ink'
                            : 'cursor-pointer border-brand-ink/15 text-brand-ink hover:border-brand-ink/40')"
                >
                    <input
                        type="radio"
                        name="fulfillment_mode"
                        value="delivery"
                        x-model="mode"
                        :disabled="!deliveryAllowed"
                        class="sr-only"
                        @if (old('fulfillment_mode') === 'delivery') checked @endif
                    >
                    <span class="flex flex-col gap-1">
                        <span
                            class="eyebrow"
                            :class="!deliveryAllowed ? 'text-brand-ink/45' : 'text-brand-ink/55'"
                        >{{ __('spargel.fulfillment_delivery_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.fulfillment_delivery') }}</span>
                        <span
                            class="text-xs leading-relaxed"
                            x-text="!deliveryAllowed
                                ? {{ json_encode(__('spargel.fulfillment_delivery_blocked', ['min' => $deliveryMinGrams])) }}
                                : {{ json_encode(__('spargel.fulfillment_delivery_hint', ['fee' => number_format($deliveryFeeEur, 2, ',', '.')])) }}"
                            :class="!deliveryAllowed ? 'text-brand-ink/55' : 'text-brand-ink/65'"
                        ></span>
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-2" />
        </fieldset>

        {{-- Pickup location chooser — shown only when fulfillment mode
             is pickup. Each card shows the address only; the host name
             appears later (confirmation page + email) so the form
             doesn't surface contact info before the customer commits. --}}
        <fieldset class="space-y-3" x-show="effectiveMode === 'pickup'" x-cloak>
            <legend class="eyebrow text-chapter-produce">{{ __('spargel.pickup_location_section') }}</legend>
            <p class="text-sm text-brand-ink/70">{{ __('spargel.pickup_location_hint') }}</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($pickupLocations as $key => $loc)
                    <label
                        class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                        :class="pickupLocation === @js((string) $key)
                            ? 'border-chapter-produce ring-1 ring-chapter-produce'
                            : 'border-brand-ink/15 hover:border-brand-ink/40'"
                    >
                        <input
                            type="radio"
                            name="pickup_location"
                            value="{{ $key }}"
                            x-model="pickupLocation"
                            class="sr-only"
                            @if (old('pickup_location') === $key) checked @endif
                        >
                        <span class="flex flex-col gap-1">
                            <span class="eyebrow text-brand-ink/55">{{ __('spargel.pickup_location_eyebrow', ['index' => $loop->iteration]) }}</span>
                            <span class="font-display text-base font-semibold leading-snug">{{ $loc['street'] }}</span>
                            <span class="text-xs leading-relaxed text-brand-ink/65">{{ $loc['postal_code'] }} {{ $loc['city'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('pickup_location')" class="mt-2" />
        </fieldset>

        {{-- Payment method — only meaningful for pickup. We auto-flip to
             'online' when delivery is the effective mode. --}}
        <fieldset class="space-y-3" x-show="effectiveMode === 'pickup'" x-cloak>
            <legend class="eyebrow text-chapter-produce">{{ __('spargel.section_payment') }}</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label
                    class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                    :class="payment === 'online'
                        ? 'border-chapter-produce ring-1 ring-chapter-produce'
                        : 'border-brand-ink/15 hover:border-brand-ink/40'"
                >
                    <input
                        type="radio"
                        name="payment_method"
                        value="online"
                        x-model="payment"
                        class="sr-only"
                        @if (old('payment_method', 'online') === 'online') checked @endif
                    >
                    <span class="flex flex-col gap-1">
                        <span class="eyebrow text-brand-ink/55">{{ __('spargel.payment_online_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.payment_online') }}</span>
                        <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('spargel.payment_online_hint') }}</span>
                    </span>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-3 border bg-white p-5 text-brand-ink transition"
                    :class="payment === 'in_person'
                        ? 'border-chapter-produce ring-1 ring-chapter-produce'
                        : 'border-brand-ink/15 hover:border-brand-ink/40'"
                >
                    <input
                        type="radio"
                        name="payment_method"
                        value="in_person"
                        x-model="payment"
                        class="sr-only"
                        @if (old('payment_method') === 'in_person') checked @endif
                    >
                    <span class="flex flex-col gap-1">
                        <span class="eyebrow text-brand-ink/55">{{ __('spargel.payment_in_person_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.payment_in_person') }}</span>
                        <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('spargel.payment_in_person_hint') }}</span>
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
        </fieldset>

        {{-- Personal data --}}
        <fieldset class="space-y-4">
            <legend class="eyebrow text-chapter-produce">{{ __('spargel.section_contact') }}</legend>

            <div>
                <x-input-label for="spargel-name" :value="__('Name')" />
                <input
                    id="spargel-name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    required
                    autocomplete="name"
                    class="{{ $inputClass }}"
                />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="spargel-email" :value="__('Email')" />
                    <input
                        id="spargel-email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        class="{{ $inputClass }}"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="spargel-phone">
                        {{ __('Phone') }}
                        <span class="ms-1 text-xs font-normal text-brand-ink/55" x-cloak>
                            <span x-show="effectiveMode === 'delivery'">{{ __('required') }}</span>
                            <span x-show="effectiveMode !== 'delivery'">({{ __('optional') }})</span>
                        </span>
                    </x-input-label>
                    <input
                        id="spargel-phone"
                        type="tel"
                        name="phone"
                        value="{{ old('phone') }}"
                        autocomplete="tel"
                        class="{{ $inputClass }}"
                    />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>
            </div>
        </fieldset>

        {{-- Delivery address — only when mode == delivery --}}
        <fieldset class="space-y-4" x-show="effectiveMode === 'delivery'" x-cloak>
            <legend class="eyebrow text-chapter-produce">{{ __('spargel.section_address') }}</legend>
            <div>
                <x-input-label for="spargel-street" :value="__('spargel.delivery_street')" />
                <input
                    id="spargel-street"
                    type="text"
                    name="delivery_street"
                    value="{{ old('delivery_street') }}"
                    autocomplete="street-address"
                    class="{{ $inputClass }}"
                />
                <x-input-error :messages="$errors->get('delivery_street')" class="mt-2" />
            </div>
            <div class="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                <div>
                    <x-input-label for="spargel-postal" :value="__('spargel.delivery_postal_code')" />
                    <input
                        id="spargel-postal"
                        type="text"
                        name="delivery_postal_code"
                        value="{{ old('delivery_postal_code') }}"
                        autocomplete="postal-code"
                        class="{{ $inputClass }}"
                    />
                    <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="spargel-city" :value="__('spargel.delivery_city')" />
                    <input
                        id="spargel-city"
                        type="text"
                        name="delivery_city"
                        value="{{ old('delivery_city') }}"
                        autocomplete="address-level2"
                        class="{{ $inputClass }}"
                    />
                    <x-input-error :messages="$errors->get('delivery_city')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="spargel-notes" :value="__('spargel.delivery_notes')" optional />
                <textarea
                    id="spargel-notes"
                    name="delivery_notes"
                    rows="2"
                    class="{{ $inputClass }}"
                >{{ old('delivery_notes') }}</textarea>
                <x-input-error :messages="$errors->get('delivery_notes')" class="mt-2" />
            </div>
        </fieldset>

        {{-- Order summary — one line per variety, then optional delivery fee. --}}
        <div class="border border-brand-ink/15 bg-brand-paper p-6 text-sm text-brand-ink/85">
            <p class="eyebrow text-chapter-produce">{{ __('spargel.summary_heading') }}</p>
            <dl class="mt-4 space-y-1.5">
                <div class="flex items-center justify-between gap-3" x-show="white > 0" x-cloak>
                    <dt>
                        <span x-text="white + ' g'"></span>
                        <span class="text-brand-ink/60">·</span>
                        {{ __('spargel.type_white') }}
                    </dt>
                    <dd class="tabular-nums" x-text="money(Math.round((white / 1000) * pricePerKg * 100) / 100)"></dd>
                </div>
                <div class="flex items-center justify-between gap-3" x-show="green > 0" x-cloak>
                    <dt>
                        <span x-text="green + ' g'"></span>
                        <span class="text-brand-ink/60">·</span>
                        {{ __('spargel.type_green') }}
                    </dt>
                    <dd class="tabular-nums" x-text="money(Math.round((green / 1000) * pricePerKg * 100) / 100)"></dd>
                </div>
                <div class="flex items-center justify-between gap-3" x-show="effectiveMode === 'delivery'" x-cloak>
                    <dt>{{ __('spargel.summary_line_delivery') }}</dt>
                    <dd class="tabular-nums" x-text="money(effectiveDeliveryFee)"></dd>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-brand-ink/10 pt-3 font-semibold text-brand-ink">
                    <dt class="font-display text-base">{{ __('spargel.summary_line_total') }}</dt>
                    <dd class="font-display text-2xl tabular-nums text-brand-ink" x-text="money(total)"></dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-brand-ink/60">
                {{ __('spargel.summary_vat_inclusive', ['rate' => $vatRatePercent]) }}
            </p>
        </div>

        {{-- Consent --}}
        <label class="flex cursor-pointer items-start gap-3 text-sm leading-relaxed text-brand-ink/85">
            <input
                type="checkbox"
                name="consent"
                value="1"
                @if (old('consent')) checked @endif
                required
                class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
            >
            <span>
                {!! __('spargel.consent_text', ['privacy' => '<a href="'.route('public.privacy').'" class="underline-offset-4 hover:text-brand-ink hover:underline" target="_blank" rel="noopener">'.e(__('spargel.consent_privacy_link')).'</a>']) !!}
            </span>
        </label>
        <x-input-error :messages="$errors->get('consent')" class="-mt-4" />

        {{-- Submit button. Online orders go through SumUp on submit; in-person
             orders bypass payment entirely and only need a single user-side
             confirmation step (the modal below) before we record the order. --}}
        <div class="pt-2">
            <button
                type="submit"
                x-show="effectivePayment !== 'in_person'"
                x-cloak
                class="btn-solid w-full justify-center disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                data-testid="spargel-submit-online"
            >
                {{ __('spargel.cta_continue_to_payment') }}
            </button>
            <button
                type="button"
                x-show="effectivePayment === 'in_person'"
                x-cloak
                @click="confirming = true"
                class="btn-solid w-full justify-center disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                data-testid="spargel-submit-in-person"
            >
                {{ __('spargel.cta_confirm_order') }}
            </button>
        </div>

        {{-- In-person confirmation modal — kept inside the <form> so the
             modal's "Submit" button can stay a plain type="submit" and
             share the form's Alpine x-data scope (confirming, total,
             money). A deliberate second click to make "you'll pay X €
             at pickup" explicit before we commit the order. --}}
        <div
            x-show="confirming"
            x-cloak
            x-on:keydown.escape.window="confirming = false"
            class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
            role="dialog"
            aria-modal="true"
            aria-labelledby="spargel-confirm-heading"
            data-testid="spargel-confirm-modal"
        >
            <div
                class="fixed inset-0 bg-brand-ink/55"
                @click="confirming = false"
            ></div>
            <div
                x-show="confirming"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="relative mx-4 mb-4 w-full max-w-lg border border-brand-ink/20 bg-brand-cream p-6 shadow-xl sm:mb-0 sm:p-8"
            >
                <p class="eyebrow text-chapter-meat">{{ __('spargel.confirm_modal_eyebrow') }}</p>
                <h2 id="spargel-confirm-heading" class="mt-2 font-display text-2xl font-semibold text-brand-ink sm:text-3xl">
                    {{ __('spargel.confirm_modal_heading') }}
                </h2>
                <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/75">
                    {{ __('spargel.confirm_modal_intro') }}
                </p>

                <dl class="mt-5 space-y-1 border border-brand-ink/15 bg-brand-paper p-4 text-sm text-brand-ink/85">
                    <div class="flex items-center justify-between gap-3" x-show="white > 0" x-cloak>
                        <dt>
                            <span x-text="white + ' g'"></span>
                            <span class="text-brand-ink/60">·</span>
                            {{ __('spargel.type_white') }}
                        </dt>
                        <dd class="tabular-nums" x-text="money(Math.round((white / 1000) * pricePerKg * 100) / 100)"></dd>
                    </div>
                    <div class="flex items-center justify-between gap-3" x-show="green > 0" x-cloak>
                        <dt>
                            <span x-text="green + ' g'"></span>
                            <span class="text-brand-ink/60">·</span>
                            {{ __('spargel.type_green') }}
                        </dt>
                        <dd class="tabular-nums" x-text="money(Math.round((green / 1000) * pricePerKg * 100) / 100)"></dd>
                    </div>
                    <div class="mt-1 flex items-center justify-between gap-3 border-t border-brand-ink/10 pt-2 font-semibold text-brand-ink">
                        <dt class="font-display">{{ __('spargel.summary_line_total') }}</dt>
                        <dd class="font-display text-lg tabular-nums text-chapter-meat" x-text="money(total)"></dd>
                    </div>
                </dl>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        @click="confirming = false"
                        class="btn-ghost w-full justify-center sm:w-auto"
                        data-testid="spargel-confirm-cancel"
                    >
                        {{ __('spargel.confirm_modal_cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="btn-solid w-full justify-center sm:w-auto"
                        data-testid="spargel-confirm-submit"
                    >
                        {{ __('spargel.confirm_modal_submit') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Bottom story strip: longer editorial copy about Max in the
         Spreewald and the way Kalkwitzer asparagus is grown. The field
         image still sits as a silent companion on the side. The id is
         the jump-link target from the farm-facts panel above. --}}
    <section
        id="spargel-story"
        class="mt-16 scroll-mt-8 -mx-4 overflow-hidden bg-brand-moss text-brand-cream sm:-mx-6"
    >
        <div class="mx-auto grid max-w-4xl gap-10 px-4 py-12 sm:grid-cols-[1fr_minmax(0,260px)] sm:items-start sm:px-6 sm:py-16">
            <div class="space-y-4">
                <p class="eyebrow text-brand-cream/75">{{ __('spargel.story_eyebrow') }}</p>
                <p class="font-display text-2xl leading-tight sm:text-3xl">{{ __('spargel.story_heading') }}</p>
                <p class="font-editorial text-sm italic leading-relaxed text-brand-cream/85 sm:text-base">
                    {{ __('spargel.story_p1') }}
                </p>
                <p class="font-editorial text-sm italic leading-relaxed text-brand-cream/85 sm:text-base">
                    {{ __('spargel.story_p2') }}
                </p>
                <p class="font-editorial text-sm italic leading-relaxed text-brand-cream/85 sm:text-base">
                    {{ __('spargel.story_p3') }}
                </p>
            </div>
            <div class="overflow-hidden">
                <img
                    src="{{ asset('images/spargel/spargel-field.jpg') }}"
                    alt=""
                    aria-hidden="true"
                    class="aspect-[3/4] h-full w-full object-cover"
                    loading="lazy"
                />
            </div>
        </div>
    </section>
</x-spargel-layout>
