<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('Preferences') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Delivery preferences') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'delivery-preferences-updated')
                <p class="mb-8 border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm text-brand-ink">
                    {{ __('Delivery preferences saved.') }}
                </p>
            @endif

            <div class="border border-brand-ink/15 bg-brand-cream/40 p-6 sm:p-10">
                <form method="post" action="{{ route('app.deliveries.preferences.update') }}" class="space-y-8">
                    @csrf
                    @method('patch')

                    <fieldset class="space-y-5">
                        <legend class="eyebrow text-chapter-produce">{{ __('Address') }}</legend>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-input-label for="recipient_name" :value="__('Recipient name')" />
                                <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-2 block w-full" :value="old('recipient_name', $address?->recipient_name ?? auth()->user()->name)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('recipient_name')" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-input-label for="street_line_1" :value="__('Street line 1')" />
                                <x-text-input id="street_line_1" name="street_line_1" type="text" class="mt-2 block w-full" :value="old('street_line_1', $address?->street_line_1)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('street_line_1')" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-input-label for="street_line_2" :value="__('Street line 2')" />
                                <x-text-input id="street_line_2" name="street_line_2" type="text" class="mt-2 block w-full" :value="old('street_line_2', $address?->street_line_2)" />
                                <x-input-error class="mt-2" :messages="$errors->get('street_line_2')" />
                            </div>

                            <div>
                                <x-input-label for="postal_code" :value="__('Postal code')" />
                                <x-text-input id="postal_code" name="postal_code" type="text" class="mt-2 block w-full" :value="old('postal_code', $address?->postal_code)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                            </div>

                            <div>
                                <x-input-label for="city" :value="__('City')" />
                                <x-text-input id="city" name="city" type="text" class="mt-2 block w-full" :value="old('city', $address?->city)" required />
                                <x-input-error class="mt-2" :messages="$errors->get('city')" />
                            </div>

                            <div>
                                <x-input-label for="country_code" :value="__('Country code')" />
                                <x-text-input id="country_code" name="country_code" type="text" class="mt-2 block w-full" :value="old('country_code', $address?->country_code ?? 'DE')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('country_code')" />
                            </div>

                            <div>
                                <x-input-label for="phone" :value="__('Phone')" />
                                <x-text-input id="phone" name="phone" type="text" class="mt-2 block w-full" :value="old('phone', $deliveryPreference?->phone ?? $address?->phone ?? auth()->user()->phone)" />
                                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                            </div>

                            <div>
                                <x-input-label for="building_code" :value="__('Building code')" />
                                <x-text-input id="building_code" name="building_code" type="text" class="mt-2 block w-full" :value="old('building_code', $address?->building_code)" />
                                <x-input-error class="mt-2" :messages="$errors->get('building_code')" />
                            </div>

                            <div>
                                <x-input-label for="floor" :value="__('Floor')" />
                                <x-text-input id="floor" name="floor" type="text" class="mt-2 block w-full" :value="old('floor', $address?->floor)" />
                                <x-input-error class="mt-2" :messages="$errors->get('floor')" />
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-5 border-t border-brand-ink/15 pt-8">
                        <legend class="eyebrow text-chapter-produce">{{ __('Scheduling') }}</legend>

                        <div>
                            <x-input-label for="preferred_timeslot" :value="__('Preferred timeslot')" />
                            <x-text-input id="preferred_timeslot" name="preferred_timeslot" type="text" class="mt-2 block w-full" :value="old('preferred_timeslot', $deliveryPreference?->preferred_timeslot)" :placeholder="__('e.g. Monday 18:00-20:00')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('preferred_timeslot')" />
                        </div>

                        <div>
                            <x-input-label for="instructions" :value="__('Delivery instructions')" />
                            <textarea
                                id="instructions"
                                name="instructions"
                                rows="3"
                                class="mt-2 block w-full border border-brand-ink/30 bg-brand-cream/60 px-4 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0"
                            >{{ old('instructions', $deliveryPreference?->instructions ?? $address?->delivery_instructions) }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('instructions')" />
                        </div>
                    </fieldset>

                    <div class="flex items-center gap-4 border-t border-brand-ink/15 pt-6">
                        <x-primary-button>{{ __('Save preferences') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
