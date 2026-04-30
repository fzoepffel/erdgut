<x-spargel-layout :title="__('spargel.page_title')">
    <p class="eyebrow text-chapter-produce">{{ __('spargel.eyebrow_trial_shop') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
        {{ __('spargel.heading') }}
    </h1>
    <p class="mt-3 max-w-xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
        {{ __('spargel.intro') }}
    </p>

    <form
        method="POST"
        action="{{ route('public.spargel.store') }}"
        class="mt-8 space-y-8"
        data-testid="spargel-form"
        x-data="{
            quantity: parseFloat({{ json_encode((string) old('quantity_kg', '1.00')) }}) || 1,
            mode: {{ json_encode(old('fulfillment_mode', 'pickup')) }},
            type: {{ json_encode(old('asparagus_type', 'green')) }},
            pricePerKg: {{ (float) $pricePerKgEur }},
            deliveryFee: {{ (float) $deliveryFeeEur }},
            deliveryMin: {{ (float) $deliveryMinKg }},
            get deliveryAllowed() { return this.quantity >= this.deliveryMin; },
            get effectiveMode() { return this.deliveryAllowed ? this.mode : 'pickup'; },
            get effectiveDeliveryFee() { return this.effectiveMode === 'delivery' ? this.deliveryFee : 0; },
            get lineTotal() { return Math.round(this.quantity * this.pricePerKg * 100) / 100; },
            get total() { return Math.round((this.lineTotal + this.effectiveDeliveryFee) * 100) / 100; },
            money(v) { return v.toFixed(2).replace('.', ',') + ' €'; },
        }"
        x-init="if (!deliveryAllowed) mode = 'pickup'"
    >
        @csrf

        {{-- Personal data --}}
        <fieldset class="space-y-4">
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_contact') }}</legend>

            <div>
                <x-input-label for="spargel-name" :value="__('Name')" />
                <x-text-input
                    id="spargel-name"
                    name="name"
                    type="text"
                    :value="old('name')"
                    required
                    autocomplete="name"
                    class="mt-2 block w-full"
                />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="spargel-email" :value="__('Email')" />
                    <x-text-input
                        id="spargel-email"
                        name="email"
                        type="email"
                        :value="old('email')"
                        required
                        autocomplete="email"
                        class="mt-2 block w-full"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="spargel-phone" :value="__('Phone')" optional />
                    <x-text-input
                        id="spargel-phone"
                        name="phone"
                        type="tel"
                        :value="old('phone')"
                        autocomplete="tel"
                        class="mt-2 block w-full"
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
                    <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
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

        {{-- Quantity --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_quantity') }}</legend>
            <div>
                <x-input-label for="spargel-quantity" :value="__('spargel.quantity_kg')" />
                <input
                    id="spargel-quantity"
                    name="quantity_kg"
                    type="number"
                    step="0.25"
                    min="0.25"
                    max="20"
                    x-model.number="quantity"
                    value="{{ old('quantity_kg', '1.00') }}"
                    required
                    class="mt-2 block w-full max-w-xs border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                />
                <p class="mt-2 text-xs text-brand-ink/60">{{ __('spargel.quantity_hint', ['price' => number_format($pricePerKgEur, 2, ',', '.')]) }}</p>
                <x-input-error :messages="$errors->get('quantity_kg')" class="mt-2" />
            </div>
        </fieldset>

        {{-- Fulfillment --}}
        <fieldset class="space-y-3">
            <legend class="eyebrow text-brand-ink/80">{{ __('spargel.section_fulfillment') }}</legend>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
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
                    class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-4 transition has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce"
                    :class="!deliveryAllowed ? 'cursor-not-allowed opacity-50' : 'hover:border-brand-ink/40'"
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
                        <span class="font-display text-base font-semibold text-brand-ink">{{ __('spargel.fulfillment_delivery') }}</span>
                        <span class="text-xs leading-relaxed text-brand-ink/65" x-text="deliveryAllowed ? {{ json_encode(__('spargel.fulfillment_delivery_hint', ['fee' => number_format($deliveryFeeEur, 2, ',', '.')])) }} : {{ json_encode(__('spargel.fulfillment_delivery_blocked', ['min' => number_format($deliveryMinKg, 2, ',', '.')])) }}"></span>
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
                <x-text-input
                    id="spargel-street"
                    name="delivery_street"
                    type="text"
                    :value="old('delivery_street')"
                    autocomplete="street-address"
                    class="mt-2 block w-full"
                />
                <x-input-error :messages="$errors->get('delivery_street')" class="mt-2" />
            </div>
            <div class="grid gap-4 sm:grid-cols-[7rem_minmax(0,1fr)]">
                <div>
                    <x-input-label for="spargel-postal" :value="__('spargel.delivery_postal_code')" />
                    <x-text-input
                        id="spargel-postal"
                        name="delivery_postal_code"
                        type="text"
                        :value="old('delivery_postal_code')"
                        autocomplete="postal-code"
                        class="mt-2 block w-full"
                    />
                    <x-input-error :messages="$errors->get('delivery_postal_code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="spargel-city" :value="__('spargel.delivery_city')" />
                    <x-text-input
                        id="spargel-city"
                        name="delivery_city"
                        type="text"
                        :value="old('delivery_city')"
                        autocomplete="address-level2"
                        class="mt-2 block w-full"
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
                    class="mt-2 block w-full border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
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
        </div>

        {{-- Consent --}}
        <label class="flex cursor-pointer items-start gap-3 text-sm text-brand-ink/85">
            <input
                type="checkbox"
                name="consent"
                value="1"
                @if (old('consent')) checked @endif
                required
                class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
            >
            <span>{{ __('spargel.consent_text') }}</span>
        </label>
        <x-input-error :messages="$errors->get('consent')" class="-mt-4" />

        <div class="pt-2">
            <x-primary-button class="w-full justify-center sm:w-auto">
                {{ __('spargel.cta_continue_to_payment') }}
            </x-primary-button>
        </div>
    </form>
</x-spargel-layout>
