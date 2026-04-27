@extends('layouts.onboarding', ['step' => 'address', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-producers">{{ __('Your address') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Where should we deliver?') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Primary delivery address and gate / floor details so we can find you.') }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.address.store') }}" class="mt-8 space-y-6">
        @csrf
        <div>
            <x-input-label for="recipient_name" :value="__('Recipient name')" required />
            <x-text-input id="recipient_name" name="recipient_name" type="text" required
                          :value="old('recipient_name', $address?->recipient_name ?? auth()->user()?->name)"
                          autocomplete="name" class="mt-2" />
            <x-input-error :messages="$errors->get('recipient_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="street_line_1" :value="__('Street and number')" required />
            <x-text-input id="street_line_1" name="street_line_1" type="text" required
                          :value="old('street_line_1', $address?->street_line_1)"
                          autocomplete="address-line1" class="mt-2" />
            <x-input-error :messages="$errors->get('street_line_1')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="street_line_2" :value="__('Address line 2')" optional />
            <x-text-input id="street_line_2" name="street_line_2" type="text"
                          :value="old('street_line_2', $address?->street_line_2)"
                          autocomplete="address-line2" class="mt-2" />
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-1">
                <x-input-label for="postal_code" :value="__('Postal code')" required />
                <x-text-input id="postal_code" name="postal_code" type="text" required
                              :value="old('postal_code', $address?->postal_code)"
                              autocomplete="postal-code" class="mt-2" />
                <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
            </div>
            <div class="col-span-2">
                <x-input-label for="city" :value="__('City')" required />
                <x-text-input id="city" name="city" type="text" required
                              :value="old('city', $address?->city)"
                              autocomplete="address-level2" class="mt-2" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>
        <div>
            {{-- Erdgut delivers only to Germany today; the country is fixed.
                 Display as a static value with a hidden input so the form
                 still submits country_code; switch to a <select> if/when
                 the delivery zone expands. --}}
            <p class="eyebrow text-brand-ink/80">{{ __('Country') }}</p>
            <p class="mt-2 border-b border-brand-ink/30 pb-2 text-brand-ink">
                <span aria-hidden="true">🇩🇪</span> {{ __('Germany') }}
            </p>
            <input type="hidden" name="country_code" value="DE">
            <x-input-error :messages="$errors->get('country_code')" class="mt-2" />
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="building_code" :value="__('Building / gate code')" optional />
                <x-text-input id="building_code" name="building_code" type="text"
                              :value="old('building_code', $address?->building_code)" class="mt-2" />
            </div>
            <div>
                <x-input-label for="floor" :value="__('Floor / unit')" optional />
                <x-text-input id="floor" name="floor" type="text"
                              :value="old('floor', $address?->floor)" class="mt-2" />
            </div>
        </div>
        <div>
            <x-input-label for="phone" :value="__('Phone (for delivery notifications)')" optional />
            <x-text-input id="phone" name="phone" type="tel"
                          :value="old('phone', $address?->phone ?? auth()->user()?->phone)"
                          autocomplete="tel" class="mt-2" />
        </div>
        <div>
            <x-input-label for="delivery_instructions" :value="__('Delivery notes')" optional />
            <textarea id="delivery_instructions" name="delivery_instructions" rows="3"
                      class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:ring-0">{{ old('delivery_instructions', $address?->delivery_instructions) }}</textarea>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-4 pt-4">
            <a href="{{ route('app.onboarding.step.account') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('Back to :step', ['step' => __('Account')]) }}
            </a>
            <x-primary-button>{{ __('Continue to :step', ['step' => __('Preferences')]) }}</x-primary-button>
        </div>
    </form>
@endsection
