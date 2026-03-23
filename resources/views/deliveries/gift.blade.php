<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Box verschenken') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="font-medium">{{ __('Lieferung vom :date als Geschenk an eine andere Adresse senden.', ['date' => $delivery->scheduled_for_date->translatedFormat('d. F Y')]) }}</p>

                    <form method="POST" action="{{ route('app.deliveries.gift.store', $delivery) }}" class="mt-6 space-y-4">
                        @csrf

                        <div>
                            <x-input-label for="recipient_name" :value="__('Name des Empfängers')" />
                            <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-1 block w-full" :value="old('recipient_name')" required autofocus />
                            <x-input-error class="mt-1" :messages="$errors->get('recipient_name')" />
                        </div>

                        <div>
                            <x-input-label for="street_line_1" :value="__('Straße & Hausnummer')" />
                            <x-text-input id="street_line_1" name="street_line_1" type="text" class="mt-1 block w-full" :value="old('street_line_1')" required />
                            <x-input-error class="mt-1" :messages="$errors->get('street_line_1')" />
                        </div>

                        <div>
                            <x-input-label for="street_line_2" :value="__('Adresszusatz (optional)')" />
                            <x-text-input id="street_line_2" name="street_line_2" type="text" class="mt-1 block w-full" :value="old('street_line_2')" />
                            <x-input-error class="mt-1" :messages="$errors->get('street_line_2')" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="postal_code" :value="__('PLZ')" />
                                <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code')" required />
                                <x-input-error class="mt-1" :messages="$errors->get('postal_code')" />
                            </div>
                            <div>
                                <x-input-label for="city" :value="__('Stadt')" />
                                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city')" required />
                                <x-input-error class="mt-1" :messages="$errors->get('city')" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="country_code" :value="__('Land')" />
                            <x-text-input id="country_code" name="country_code" type="text" class="mt-1 block w-full" :value="old('country_code', 'DE')" required maxlength="2" placeholder="DE" />
                            <x-input-error class="mt-1" :messages="$errors->get('country_code')" />
                        </div>

                        <div>
                            <x-input-label for="phone" :value="__('Telefon (optional)')" />
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
                            <x-input-error class="mt-1" :messages="$errors->get('phone')" />
                        </div>

                        <div>
                            <x-input-label for="delivery_instructions" :value="__('Lieferhinweise (optional)')" />
                            <textarea id="delivery_instructions" name="delivery_instructions" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('delivery_instructions') }}</textarea>
                            <x-input-error class="mt-1" :messages="$errors->get('delivery_instructions')" />
                        </div>

                        <div>
                            <x-input-label for="target_date" :value="__('Lieferdatum')" />
                            <x-text-input id="target_date" name="target_date" type="date" class="mt-1 block w-full" :value="old('target_date', $suggestedTargetDate ?? $delivery->scheduled_for_date->format('Y-m-d'))" required />
                            <x-input-error class="mt-1" :messages="$errors->get('target_date')" />
                        </div>

                        <div class="flex gap-3 pt-4">
                            <x-primary-button>{{ __('Als Geschenk senden') }}</x-primary-button>
                            <a href="{{ route('app.deliveries.index', ['focus' => ($delivery->is_weekly_override ? $delivery->overridesDelivery?->id : null) ?? $delivery->id]) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                {{ __('Abbrechen') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
