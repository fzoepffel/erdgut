<x-spargel-layout :title="__('spargel.page_title')">
    @php
        $deliveryDateLabel = $nextDeliveryMonday->locale(app()->getLocale())->isoFormat('dddd, D. MMMM');
    @endphp

    <p class="eyebrow text-chapter-produce">{{ __('spargel.eyebrow_trial_shop') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
        {{ __('spargel.heading') }}
    </h1>
    <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
        {{ __('spargel.intro') }}
    </p>

    {{-- Delivery date banner: tells the customer up-front when the order
         will arrive (or be ready for pickup). Driven by the Thursday
         cutoff in SpargelOrderService::nextDeliveryMonday(). --}}
    <div class="mt-6 max-w-xl border border-chapter-produce/30 bg-chapter-produce/5 p-4 text-sm leading-relaxed text-brand-ink">
        <p class="eyebrow text-chapter-produce">{{ __('spargel.delivery_window_eyebrow') }}</p>
        <p class="mt-1 font-display text-base text-brand-ink">
            {{ __('spargel.delivery_window_main', ['date' => $deliveryDateLabel]) }}
        </p>
        <p class="mt-1 text-xs text-brand-ink/65">{{ __('spargel.delivery_window_rule') }}</p>
    </div>

    <form
        method="POST"
        action="{{ route('public.spargel.store') }}"
        class="mt-8 space-y-8"
        data-testid="spargel-form"
        x-data="{
            quantity: parseInt({{ json_encode((string) old('quantity_grams', '500')) }}, 10) || 500,
            mode: {{ json_encode(old('fulfillment_mode', 'pickup')) }},
            type: {{ json_encode(old('asparagus_type', 'green')) }},
            pricePerKg: {{ (float) $pricePerKgEur }},
            deliveryFee: {{ (float) $deliveryFeeEur }},
            deliveryMin: {{ (int) $deliveryMinGrams }},
            get deliveryAllowed() { return this.quantity >= this.deliveryMin; },
            get effectiveMode() { return this.deliveryAllowed ? this.mode : 'pickup'; },
            get effectiveDeliveryFee() { return this.effectiveMode === 'delivery' ? this.deliveryFee : 0; },
            get lineTotal() { return Math.round((this.quantity / 1000) * this.pricePerKg * 100) / 100; },
            get total() { return Math.round((this.lineTotal + this.effectiveDeliveryFee) * 100) / 100; },
            money(v) { return v.toFixed(2).replace('.', ',') + ' €'; },
        }"
        x-init="if (!deliveryAllowed) mode = 'pickup'"
    >
        @csrf

        @php
            // Boxed-input class — same look as the existing onboarding
            // quantity input. Reused for every text input on this page so
            // the form reads as one consistent surface (the editorial
            // underline-only style was too quiet for a public trial-shop).
            $inputClass = 'mt-2 block w-full border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0';
        @endphp

        {{-- Personal data --}}
        <fieldset class="space-y-4">
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_contact') }}</legend>

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
                    {{-- Phone is required when delivery is chosen so we
                         can reach the customer at the door — surface that
                         dynamically in the label so the customer knows
                         why we're asking. --}}
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

        {{-- Asparagus type --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_product') }}</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach (['green', 'white'] as $type)
                    <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 bg-white p-4 transition hover:border-chapter-produce/50 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/10 has-[:checked]:ring-2 has-[:checked]:ring-chapter-produce/40">
                        <input
                            type="radio"
                            name="asparagus_type"
                            value="{{ $type }}"
                            x-model="type"
                            class="mt-1 sr-only"
                            @if (old('asparagus_type', 'green') === $type) checked @endif
                        >
                        <span class="flex flex-col gap-1">
                            <span class="font-display text-base font-semibold text-brand-ink">
                                {{ __('spargel.type_'.$type) }}
                            </span>
                            <span class="text-xs leading-relaxed text-brand-ink/65">
                                {{ __('spargel.type_'.$type.'_hint') }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('asparagus_type')" class="mt-2" />
        </fieldset>

        {{-- Quantity (in grams). The fieldset legend doubles as the input
             label so we don't say MENGE / MENGE IN GRAMM twice. --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_quantity') }}</legend>
            <div class="flex items-center gap-3">
                <input
                    id="spargel-quantity"
                    name="quantity_grams"
                    type="number"
                    step="50"
                    min="100"
                    max="20000"
                    x-model.number="quantity"
                    value="{{ old('quantity_grams', '500') }}"
                    required
                    class="block w-32 border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                />
                <span class="font-display text-base text-brand-ink/85">{{ __('spargel.unit_grams') }}</span>
            </div>
            <p class="text-xs text-brand-ink/60">
                {{ __('spargel.quantity_hint', ['price' => number_format($pricePerKgEur, 2, ',', '.')]) }}
            </p>
            <p class="text-xs text-brand-ink/60">
                {{ __('spargel.portion_hint') }}
            </p>
            <x-input-error :messages="$errors->get('quantity_grams')" class="mt-2" />
        </fieldset>

        {{-- Fulfillment --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_fulfillment') }}</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 bg-white p-4 transition hover:border-chapter-produce/50 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/10 has-[:checked]:ring-2 has-[:checked]:ring-chapter-produce/40">
                    <input
                        type="radio"
                        name="fulfillment_mode"
                        value="pickup"
                        x-model="mode"
                        class="mt-1 sr-only"
                        @if (old('fulfillment_mode', 'pickup') === 'pickup') checked @endif
                    >
                    <span class="flex flex-col gap-1">
                        <span class="font-display text-base font-semibold text-brand-ink">{{ __('spargel.fulfillment_pickup') }}</span>
                        <span class="text-xs leading-relaxed text-brand-ink/65">{{ __('spargel.fulfillment_pickup_hint') }}</span>
                    </span>
                </label>
                <label
                    class="flex items-start gap-3 border bg-white p-4 transition has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/10 has-[:checked]:ring-2 has-[:checked]:ring-chapter-produce/40"
                    :class="deliveryAllowed
                        ? 'cursor-pointer border-brand-ink/20 hover:border-chapter-produce/50'
                        : 'cursor-not-allowed border-brand-ink/15 bg-brand-ink/[0.03] opacity-60'"
                >
                    <input
                        type="radio"
                        name="fulfillment_mode"
                        value="delivery"
                        x-model="mode"
                        :disabled="!deliveryAllowed"
                        class="mt-1 sr-only"
                        @if (old('fulfillment_mode') === 'delivery') checked @endif
                    >
                    <span class="flex flex-col gap-1">
                        <span class="font-display text-base font-semibold" :class="deliveryAllowed ? 'text-brand-ink' : 'text-brand-ink/55'">
                            {{ __('spargel.fulfillment_delivery') }}
                        </span>
                        <span
                            class="text-xs leading-relaxed text-brand-ink/65"
                            x-text="deliveryAllowed
                                ? {{ json_encode(__('spargel.fulfillment_delivery_hint', ['fee' => number_format($deliveryFeeEur, 2, ',', '.')])) }}
                                : {{ json_encode(__('spargel.fulfillment_delivery_blocked', ['min' => $deliveryMinGrams])) }}"
                        ></span>
                    </span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-2" />
        </fieldset>

        {{-- Delivery address — only when mode == delivery --}}
        <fieldset class="space-y-4" x-show="effectiveMode === 'delivery'" x-cloak>
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_address') }}</legend>
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
        <div class="border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/85">
            <p class="eyebrow text-brand-ink/60">{{ __('spargel.summary_heading') }}</p>
            <dl class="mt-3 space-y-1">
                <div class="flex items-center justify-between gap-3">
                    <dt>{{ __('spargel.summary_line_subtotal') }}</dt>
                    <dd class="tabular-nums" x-text="money(lineTotal)"></dd>
                </div>
                <div class="flex items-center justify-between gap-3" x-show="effectiveMode === 'delivery'" x-cloak>
                    <dt>{{ __('spargel.summary_line_delivery') }}</dt>
                    <dd class="tabular-nums" x-text="money(effectiveDeliveryFee)"></dd>
                </div>
                <div class="mt-2 flex items-center justify-between gap-3 border-t border-brand-ink/10 pt-2 font-semibold text-brand-ink">
                    <dt>{{ __('spargel.summary_line_total') }}</dt>
                    <dd class="font-display text-lg tabular-nums" x-text="money(total)"></dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-brand-ink/60">
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

        <div class="pt-2">
            <x-primary-button class="w-full justify-center sm:w-auto">
                {{ __('spargel.cta_continue_to_payment') }}
            </x-primary-button>
        </div>
    </form>
</x-spargel-layout>
