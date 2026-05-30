@extends('layouts.onboarding', ['step' => 'address', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    @php($zoneHint = app(\App\Support\DeliveryZoneResolver::class)->defaultZonePostalCodeHint())

    <form method="POST" action="{{ route('app.onboarding.step.address.store') }}" class="space-y-10">
        @csrf

        @if ($zoneHint !== null)
            {{-- Berlin-only delivery — surface the zone before the user types
                 an unsupported PLZ. Server validation enforces the same range. --}}
            <p class="border-l-2 border-chapter-produce bg-brand-paper px-4 py-3 text-sm leading-relaxed text-brand-ink/70" data-testid="address-delivery-zone-hint">
                {{ __('We currently deliver only in Berlin (PLZ :hint).', ['hint' => $zoneHint]) }}
            </p>
        @endif

        <div class="space-y-8">
            <div>
                <x-input-label for="recipient_name" :value="__('Recipient name')" required />
                <x-text-input id="recipient_name" name="recipient_name" type="text" required
                              :value="old('recipient_name', $address?->recipient_name ?? auth()->user()?->name)"
                              autocomplete="name" class="mt-2" />
                <x-input-error id="recipient_name-error" :messages="$errors->get('recipient_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="street_line_1" :value="__('Street and number')" required />
                <x-text-input id="street_line_1" name="street_line_1" type="text" required
                              :value="old('street_line_1', $address?->street_line_1)"
                              autocomplete="address-line1" class="mt-2" />
                <x-input-error id="street_line_1-error" :messages="$errors->get('street_line_1')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="street_line_2" :value="__('Address line 2')" optional />
                <x-text-input id="street_line_2" name="street_line_2" type="text"
                              :value="old('street_line_2', $address?->street_line_2)"
                              autocomplete="address-line2" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <x-input-label for="postal_code" :value="__('Postal code')" required />
                    <x-text-input id="postal_code" name="postal_code" type="text" required
                                  :value="old('postal_code', $address?->postal_code)"
                                  autocomplete="postal-code" class="mt-2" />
                    <x-input-error id="postal_code-error" :messages="$errors->get('postal_code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="city" :value="__('City')" required />
                    <x-text-input id="city" name="city" type="text" required
                                  :value="old('city', $address?->city)"
                                  autocomplete="address-level2" class="mt-2" />
                    <x-input-error id="city-error" :messages="$errors->get('city')" class="mt-2" />
                </div>
            </div>

            <div>
                {{-- Erdgut delivers only to Germany today; the country is fixed.
                     Styled as a read-only notice (no false affordance); the
                     hidden input still ships country_code so the contract holds. --}}
                <p class="eyebrow text-brand-ink/60">{{ __('Country') }}</p>
                <p class="mt-2 inline-flex items-center gap-2 text-sm text-brand-ink/85" data-testid="address-country-static">
                    <span aria-hidden="true">🇩🇪</span>
                    <span>{{ __('We currently only deliver in Germany.') }}</span>
                </p>
                <input type="hidden" name="country_code" value="DE">
                <x-input-error id="country_code-error" :messages="$errors->get('country_code')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-6">
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
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-brand-ink/10 pt-8">
            <span class="text-sm text-brand-ink/30" aria-disabled="true">← {{ __('onboarding.cta.back') }}</span>
            <x-primary-button>{{ __('onboarding.cta.to_box') }}</x-primary-button>
        </div>
    </form>
@endsection
