<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create subscription') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6 rounded-lg border border-emerald-100 bg-emerald-50/80 px-4 py-3">
                <p class="text-sm font-semibold text-emerald-900">{{ __('Step 1 of 2: Box and preferences') }}</p>
                <p class="mt-1 text-sm text-emerald-800">{{ __('After creating your subscription, you will set up payment on the next page (step 2).') }}</p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100">
                <div class="p-6 text-gray-900">
                    <p class="mb-6 text-sm text-gray-600">
                        {{ __('Choose your box size and preferences to get started.') }}
                    </p>

                    <form method="post" action="{{ route('app.subscriptions.store') }}" class="space-y-8">
                        @csrf

                        <fieldset>
                            <legend class="text-sm font-medium text-gray-900">{{ __('Delivery rhythm') }}</legend>
                            <p class="mt-1 text-xs text-gray-500">{{ __('How often we bill and plan your box cycle.') }}</p>
                            <div class="mt-3 space-y-3">
                                @foreach (\App\Enums\SubscriptionPlan::cases() as $planOption)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-gray-200 p-4 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50">
                                        <input
                                            type="radio"
                                            name="plan"
                                            value="{{ $planOption->value }}"
                                            class="mt-1 border-gray-300 text-emerald-600 focus:ring-emerald-600"
                                            @checked(old('plan', $subscription->plan->value) === $planOption->value)
                                            required
                                        />
                                        <span>
                                            <span class="block text-sm font-medium text-gray-900">
                                                {{ $planOption === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery') }}
                                            </span>
                                            <span class="mt-0.5 block text-xs text-gray-600">
                                                {{ $planOption === \App\Enums\SubscriptionPlan::Weekly
                                                    ? __('Charged and delivered every week.')
                                                    : __('Charged and delivered every two weeks.') }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('plan')" />
                        </fieldset>

                        <div>
                            <x-input-label for="box_size" :value="__('Box size')" />
                            <select
                                id="box_size"
                                name="box_size"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                required
                            >
                                @foreach (['small', 'medium', 'large'] as $size)
                                    <option value="{{ $size }}" @selected(old('box_size', $subscription->box_size) === $size)>
                                        {{ __(ucfirst($size)) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('box_size')" />
                        </div>

                        <fieldset class="space-y-3">
                            <legend class="text-sm font-medium text-gray-900">{{ __('Preferences') }}</legend>

                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="preferences[veg_only]"
                                    value="1"
                                    @checked((bool) old('preferences.veg_only', data_get($subscription->preferences, 'veg_only', false)))
                                    class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600"
                                />
                                <span class="text-sm text-gray-700">{{ __('Veg option only') }}</span>
                            </label>

                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="preferences[meat_add_on]"
                                    value="1"
                                    @checked((bool) old('preferences.meat_add_on', data_get($subscription->preferences, 'meat_add_on', false)))
                                    class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600"
                                />
                                <span class="text-sm text-gray-700">{{ __('Meat add-on') }}</span>
                            </label>

                            <label class="inline-flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    name="preferences[eggs_dairy_add_on]"
                                    value="1"
                                    @checked((bool) old('preferences.eggs_dairy_add_on', data_get($subscription->preferences, 'eggs_dairy_add_on', false)))
                                    class="mt-1 rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600"
                                />
                                <span>
                                    <span class="text-sm text-gray-700">{{ __('Eggs or dairy add-on') }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">
                                        {{ __('Eggs or dairy add-on hint', ['amount' => number_format((float) config('box.eggs_dairy_add_on_eur'), 2, ',', '.')]) }}
                                    </span>
                                </span>
                            </label>

                            <x-input-error class="mt-2" :messages="$errors->get('preferences.veg_only')" />
                            <x-input-error class="mt-2" :messages="$errors->get('preferences.meat_add_on')" />
                            <x-input-error class="mt-2" :messages="$errors->get('preferences.eggs_dairy_add_on')" />
                        </fieldset>

                        @php
                            $oldAllergies = old('allergies');
                            $allergiesValue = is_array($oldAllergies) ? implode(', ', $oldAllergies) : ($allergiesInput ?? '');
                        @endphp
                        <div>
                            <x-input-label for="allergies" :value="__('Allergies (comma separated)')" />
                            <x-text-input
                                id="allergies"
                                name="allergies"
                                type="text"
                                class="mt-1 block w-full"
                                :value="$allergiesValue"
                                placeholder="{{ __('e.g. nuts, gluten') }}"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('allergies')" />
                            <x-input-error class="mt-2" :messages="$errors->get('allergies.*')" />
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <x-primary-button>{{ __('Create subscription') }}</x-primary-button>
                            <a href="{{ route('app.subscriptions.show') }}" class="text-sm text-gray-600 hover:text-gray-900">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
