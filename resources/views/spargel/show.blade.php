<x-spargel-layout :title="__('spargel.page_title')">
    @php
        $deliveryDateLabel = $nextDeliveryMonday->locale(app()->getLocale())->isoFormat('dddd, D. MMMM');
    @endphp

    {{-- Hero block: editorial heading on the brand-cream page bg.
         Hero video was removed — the source clip didn't render at the
         quality bar we want for the brand. Re-introduce later when
         we have a properly-shot harvest video. --}}
    <header class="mb-10 sm:mb-14">
        <p class="eyebrow text-chapter-produce">{{ __('spargel.eyebrow_trial_shop') }}</p>
        <h1 class="mt-3 max-w-2xl font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
            {{ __('spargel.heading') }}
        </h1>
        <p class="mt-4 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75 sm:text-lg">
            {{ __('spargel.intro') }}
        </p>
    </header>

    {{-- Delivery date banner: tells the customer up-front when the order
         will arrive (or be ready for pickup). Uses chapter-bread soft —
         warm earth tone that pairs with the produce-green cards below. --}}
    <div class="border border-chapter-bread/30 bg-chapter-bread/[0.08] p-5 text-sm leading-relaxed text-brand-ink">
        <p class="eyebrow text-chapter-bread">{{ __('spargel.delivery_window_eyebrow') }}</p>
        <p class="mt-1 font-display text-base text-brand-ink sm:text-lg">
            {{ __('spargel.delivery_window_main', ['date' => $deliveryDateLabel]) }}
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
            quantity: parseInt({{ json_encode((string) old('quantity_grams', '500')) }}, 10) || 500,
            mode: {{ json_encode(old('fulfillment_mode', 'pickup')) }},
            type: {{ json_encode(old('asparagus_type', 'green')) }},
            payment: {{ json_encode(old('payment_method', 'online')) }},
            pricePerKg: {{ (float) $pricePerKgEur }},
            deliveryFee: {{ (float) $deliveryFeeEur }},
            deliveryMin: {{ (int) $deliveryMinGrams }},
            confirming: false,
            get deliveryAllowed() { return this.quantity >= this.deliveryMin; },
            get effectiveMode() { return this.deliveryAllowed ? this.mode : 'pickup'; },
            get effectiveDeliveryFee() { return this.effectiveMode === 'delivery' ? this.deliveryFee : 0; },
            get effectivePayment() { return this.effectiveMode === 'delivery' ? 'online' : this.payment; },
            get lineTotal() { return Math.round((this.quantity / 1000) * this.pricePerKg * 100) / 100; },
            get total() { return Math.round((this.lineTotal + this.effectiveDeliveryFee) * 100) / 100; },
            money(v) { return v.toFixed(2).replace('.', ',') + ' €'; },
        }"
        x-init="
            if (!deliveryAllowed) mode = 'pickup';
            // When switching to delivery, force payment back to online —
            // pay-in-person only makes sense when the customer collects.
            $watch('mode', value => { if (value === 'delivery') payment = 'online'; });
            $watch('quantity', () => { if (!deliveryAllowed) mode = 'pickup'; });
        "
    >
        @csrf

        @php
            // Boxed-input class — same shape on every text input so the
            // form reads as one consistent surface.
            $inputClass = 'mt-2 block w-full border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0';
        @endphp

        {{-- Asparagus type — chapter-tiled cards à la homepage, with a
             real photo of each variety. Selected card flips to a
             saturated chapter colour with cream text. --}}
        <fieldset class="space-y-4">
            <legend class="eyebrow text-chapter-eggdairy">{{ __('spargel.section_product') }}</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @php
                    // Each sorte gets a chapter colour for its selected state.
                    // Tailwind needs the literal class names somewhere in
                    // source for JIT to pick them up — so the safelist below
                    // names every variant explicitly even though we build
                    // them dynamically in :class.
                    //
                    // safelist: border-chapter-produce bg-chapter-produce
                    //           border-chapter-produce/30 border-chapter-produce/60
                    //           text-chapter-produce
                    //           border-chapter-eggdairy bg-chapter-eggdairy
                    //           border-chapter-eggdairy/30 border-chapter-eggdairy/60
                    //           text-chapter-eggdairy
                    $sortePalette = [
                        'green' => ['image' => 'spargel-green.jpg', 'chapter' => 'produce'],
                        'white' => ['image' => 'spargel-white.jpg', 'chapter' => 'eggdairy'],
                    ];
                @endphp
                @foreach (['green', 'white'] as $type)
                    @php $p = $sortePalette[$type]; @endphp
                    <label
                        class="group relative flex cursor-pointer flex-col overflow-hidden border transition"
                        :class="type === @js($type)
                            ? @js('border-chapter-'.$p['chapter'].' bg-chapter-'.$p['chapter'].' text-brand-cream')
                            : @js('border-chapter-'.$p['chapter'].'/30 bg-white text-brand-ink hover:border-chapter-'.$p['chapter'].'/60')"
                    >
                        <input
                            type="radio"
                            name="asparagus_type"
                            value="{{ $type }}"
                            x-model="type"
                            class="sr-only"
                            @if (old('asparagus_type', 'green') === $type) checked @endif
                        >
                        <div class="aspect-[5/3] w-full overflow-hidden">
                            <img
                                src="{{ asset('images/spargel/'.$p['image']) }}"
                                alt=""
                                aria-hidden="true"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                                loading="lazy"
                            />
                        </div>
                        <div class="flex flex-col gap-2 p-5">
                            <p
                                class="eyebrow"
                                :class="type === @js($type) ? 'text-brand-cream/80' : @js('text-chapter-'.$p['chapter'])"
                            >
                                {{ $type === 'green' ? '01' : '02' }} &nbsp;·&nbsp; {{ __('spargel.section_product') }}
                            </p>
                            <p class="font-display text-2xl font-semibold leading-tight">
                                {{ __('spargel.type_'.$type) }}
                            </p>
                            <p
                                class="font-editorial text-sm italic leading-relaxed"
                                :class="type === @js($type) ? 'text-brand-cream/85' : 'text-brand-ink/70'"
                            >
                                {{ __('spargel.type_'.$type.'_hint') }}
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('asparagus_type')" class="mt-2" />
        </fieldset>

        {{-- Quantity dropdown. Replaced the free-form number input so
             customers can only pick valid 50 g multiples between
             QUANTITY_MIN_GRAMS and QUANTITY_MAX_GRAMS — the validator
             enforces the same range, but the dropdown removes the
             chance to fiddle with odd values in the first place. --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-chapter-eggdairy">{{ __('spargel.section_quantity') }}</legend>
            <div class="flex items-center gap-3">
                <select
                    id="spargel-quantity"
                    name="quantity_grams"
                    x-model.number="quantity"
                    required
                    class="border border-brand-ink/30 bg-white py-2 pl-3 pr-8 text-sm text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                >
                    @php $current = (int) old('quantity_grams', 500); @endphp
                    @foreach ($quantityOptions as $g)
                        <option value="{{ $g }}" @selected($current === $g)>
                            {{ $g }} {{ __('spargel.unit_grams_short') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <p class="text-xs text-brand-ink/60">
                {{ __('spargel.quantity_hint', ['price' => number_format($pricePerKgEur, 2, ',', '.'), 'step' => $quantityStepGrams]) }}
            </p>
            <p class="text-xs text-brand-ink/60">
                {{ __('spargel.portion_hint') }}
            </p>
            <x-input-error :messages="$errors->get('quantity_grams')" class="mt-2" />
        </fieldset>

        {{-- Fulfillment — chapter-bread for pickup (warm earth tone, "by
             hand"), chapter-fish for delivery (blue, "on the move"). --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-chapter-eggdairy">{{ __('spargel.section_fulfillment') }}</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label
                    class="flex cursor-pointer items-start gap-3 border p-5 transition"
                    :class="mode === 'pickup'
                        ? 'border-chapter-bread bg-chapter-bread text-brand-cream'
                        : 'border-chapter-bread/30 bg-white text-brand-ink hover:border-chapter-bread/60'"
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
                        <span
                            class="eyebrow"
                            :class="mode === 'pickup' ? 'text-brand-cream/80' : 'text-chapter-bread'"
                        >{{ __('spargel.fulfillment_pickup_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.fulfillment_pickup') }}</span>
                        <span
                            class="text-xs leading-relaxed"
                            :class="mode === 'pickup' ? 'text-brand-cream/85' : 'text-brand-ink/65'"
                        >{{ __('spargel.fulfillment_pickup_hint') }}</span>
                    </span>
                </label>
                <label
                    class="flex items-start gap-3 border p-5 transition"
                    :class="!deliveryAllowed
                        ? 'cursor-not-allowed border-brand-ink/15 bg-brand-ink/[0.03] text-brand-ink/45'
                        : (effectiveMode === 'delivery'
                            ? 'cursor-pointer border-chapter-fish bg-chapter-fish text-brand-cream'
                            : 'cursor-pointer border-chapter-fish/30 bg-white text-brand-ink hover:border-chapter-fish/60')"
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
                            :class="!deliveryAllowed ? 'text-brand-ink/45' : (effectiveMode === 'delivery' ? 'text-brand-cream/80' : 'text-chapter-fish')"
                        >{{ __('spargel.fulfillment_delivery_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.fulfillment_delivery') }}</span>
                        <span
                            class="text-xs leading-relaxed"
                            x-text="!deliveryAllowed
                                ? {{ json_encode(__('spargel.fulfillment_delivery_blocked', ['min' => $deliveryMinGrams])) }}
                                : {{ json_encode(__('spargel.fulfillment_delivery_hint', ['fee' => number_format($deliveryFeeEur, 2, ',', '.')])) }}"
                            :class="!deliveryAllowed
                                ? 'text-brand-ink/55'
                                : (effectiveMode === 'delivery' ? 'text-brand-cream/85' : 'text-brand-ink/65')"
                        ></span>
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-2" />
        </fieldset>

        {{-- Payment method — only meaningful for pickup. We auto-flip to
             'online' when delivery is the effective mode. --}}
        <fieldset class="space-y-3" x-show="effectiveMode === 'pickup'" x-cloak>
            <legend class="eyebrow text-chapter-eggdairy">{{ __('spargel.section_payment') }}</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label
                    class="flex cursor-pointer items-start gap-3 border p-5 transition"
                    :class="payment === 'online'
                        ? 'border-chapter-meat bg-chapter-meat text-brand-cream'
                        : 'border-chapter-meat/30 bg-white text-brand-ink hover:border-chapter-meat/60'"
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
                        <span
                            class="eyebrow"
                            :class="payment === 'online' ? 'text-brand-cream/80' : 'text-chapter-meat'"
                        >{{ __('spargel.payment_online_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.payment_online') }}</span>
                        <span
                            class="text-xs leading-relaxed"
                            :class="payment === 'online' ? 'text-brand-cream/85' : 'text-brand-ink/65'"
                        >{{ __('spargel.payment_online_hint') }}</span>
                    </span>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-3 border p-5 transition"
                    :class="payment === 'in_person'
                        ? 'border-chapter-meat bg-chapter-meat text-brand-cream'
                        : 'border-chapter-meat/30 bg-white text-brand-ink hover:border-chapter-meat/60'"
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
                        <span
                            class="eyebrow"
                            :class="payment === 'in_person' ? 'text-brand-cream/80' : 'text-chapter-meat'"
                        >{{ __('spargel.payment_in_person_eyebrow') }}</span>
                        <span class="font-display text-lg font-semibold">{{ __('spargel.payment_in_person') }}</span>
                        <span
                            class="text-xs leading-relaxed"
                            :class="payment === 'in_person' ? 'text-brand-cream/85' : 'text-brand-ink/65'"
                        >{{ __('spargel.payment_in_person_hint') }}</span>
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
        </fieldset>

        {{-- Personal data --}}
        <fieldset class="space-y-4">
            <legend class="eyebrow text-chapter-eggdairy">{{ __('spargel.section_contact') }}</legend>

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
            <legend class="eyebrow text-chapter-fish">{{ __('spargel.section_address') }}</legend>
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

        {{-- Order summary --}}
        <div class="border border-brand-ink/15 bg-brand-paper p-6 text-sm text-brand-ink/85">
            <p class="eyebrow text-chapter-meat">{{ __('spargel.summary_heading') }}</p>
            <dl class="mt-4 space-y-1.5">
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('spargel.summary_line_subtotal') }}</dt>
                    <dd class="tabular-nums" x-text="money(lineTotal)"></dd>
                </div>
                <div class="flex items-center justify-between gap-3" x-show="effectiveMode === 'delivery'" x-cloak>
                    <dt>{{ __('spargel.summary_line_delivery') }}</dt>
                    <dd class="tabular-nums" x-text="money(effectiveDeliveryFee)"></dd>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-brand-ink/10 pt-3 font-semibold text-brand-ink">
                    <dt class="font-display text-base">{{ __('spargel.summary_line_total') }}</dt>
                    <dd class="font-display text-2xl tabular-nums text-chapter-meat" x-text="money(total)"></dd>
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
                    <div class="flex items-center justify-between gap-3">
                        <dt>{{ __('spargel.summary_line_subtotal') }}</dt>
                        <dd class="tabular-nums" x-text="money(lineTotal)"></dd>
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

    {{-- Bottom story strip: short editorial paragraph + the field
         photograph as a silent companion. The video lives in the hero;
         this section is the still, slower counterpoint. --}}
    <section class="mt-16 -mx-4 overflow-hidden bg-brand-moss text-brand-cream sm:-mx-6">
        <div class="mx-auto grid max-w-3xl gap-8 px-4 py-12 sm:grid-cols-[1fr_minmax(0,240px)] sm:items-center sm:px-6 sm:py-16">
            <div>
                <p class="eyebrow text-brand-cream/75">{{ __('spargel.story_eyebrow') }}</p>
                <p class="mt-3 font-display text-2xl leading-tight sm:text-3xl">{{ __('spargel.story_heading') }}</p>
                <p class="mt-4 font-editorial text-sm italic leading-relaxed text-brand-cream/85 sm:text-base">
                    {{ __('spargel.story_body') }}
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
