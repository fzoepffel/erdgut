<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('Gift') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Gift this box') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="border border-brand-ink/15 bg-brand-cream/40 p-6 sm:p-10">
                <p class="font-display text-xl text-brand-ink">{{ __('Send the delivery scheduled for :date as a gift to another address.', ['date' => $delivery->scheduled_for_date->translatedFormat('d. F Y')]) }}</p>
                <p class="mt-3 text-sm leading-relaxed text-brand-ink/75">{{ __('Your subscription billing continues as usual. This week is skipped on your account; the gift is sent on the date you choose.') }}</p>

                <form method="POST" action="{{ route('app.deliveries.gift.store', $delivery) }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="recipient_name" :value="__('Recipient name')" />
                        <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-2 block w-full" :value="old('recipient_name')" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('recipient_name')" />
                    </div>

                    <div>
                        <x-input-label for="street_line_1" :value="__('Street & house number')" />
                        <x-text-input id="street_line_1" name="street_line_1" type="text" class="mt-2 block w-full" :value="old('street_line_1')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('street_line_1')" />
                    </div>

                    <div>
                        <x-input-label for="street_line_2" :value="__('Address line 2 (optional)')" />
                        <x-text-input id="street_line_2" name="street_line_2" type="text" class="mt-2 block w-full" :value="old('street_line_2')" />
                        <x-input-error class="mt-2" :messages="$errors->get('street_line_2')" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="postal_code" :value="__('Postal code')" />
                            <x-text-input id="postal_code" name="postal_code" type="text" class="mt-2 block w-full" :value="old('postal_code')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                        </div>
                        <div>
                            <x-input-label for="city" :value="__('City')" />
                            <x-text-input id="city" name="city" type="text" class="mt-2 block w-full" :value="old('city')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('city')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="country_code" :value="__('Country')" />
                        <x-text-input id="country_code" name="country_code" type="text" class="mt-2 block w-full" :value="old('country_code', 'DE')" required maxlength="2" placeholder="DE" />
                        <x-input-error class="mt-2" :messages="$errors->get('country_code')" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Phone (optional)')" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-2 block w-full" :value="old('phone')" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>

                    <div>
                        <x-input-label for="delivery_instructions" :value="__('Delivery notes (optional)')" />
                        <textarea id="delivery_instructions" name="delivery_instructions" rows="2" class="mt-2 block w-full border border-brand-ink/30 bg-brand-cream/60 px-4 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0">{{ old('delivery_instructions') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('delivery_instructions')" />
                    </div>

                    <div>
                        <x-input-label for="target_date" :value="__('Gift delivery date')" />
                        <x-text-input id="target_date" name="target_date" type="date" class="mt-2 block w-full" :value="old('target_date', $suggestedTargetDate ?? $delivery->scheduled_for_date->format('Y-m-d'))" required />
                        <x-input-error class="mt-2" :messages="$errors->get('target_date')" />
                    </div>

                    <div class="flex flex-wrap gap-3 border-t border-brand-ink/15 pt-6">
                        <x-primary-button>{{ __('Send as gift') }}</x-primary-button>
                        <a href="{{ route('app.deliveries.index', ['focus' => ($delivery->is_weekly_override ? $delivery->overridesDelivery?->id : null) ?? $delivery->id]) }}" class="btn-ghost">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
