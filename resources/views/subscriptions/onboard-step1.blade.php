<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('Step 1 of 2') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Create subscription') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
            <div class="border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3">
                <p class="eyebrow text-chapter-produce">{{ __('Step 1 of 2: Box and preferences') }}</p>
                <p class="mt-2 text-sm text-brand-ink/80">{{ __('After creating your subscription, you will set up payment on the next page (step 2).') }}</p>
            </div>

            <div class="border border-brand-ink/15 p-6 sm:p-8">
                <p class="text-base text-brand-ink/80">
                    {{ __('Choose your box size and preferences to get started.') }}
                </p>

                <form method="post" action="{{ route('app.subscriptions.onboard.store') }}" class="mt-6 space-y-10">
                    @csrf

                    <fieldset>
                        <legend class="eyebrow text-brand-ink">{{ __('Delivery rhythm') }}</legend>
                        <p class="mt-2 text-sm text-brand-ink/70">{{ __('How often we bill and plan your box cycle.') }}</p>
                        <div class="mt-4 space-y-3">
                            @foreach (\App\Enums\SubscriptionPlan::cases() as $planOption)
                                <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                                    <input
                                        type="radio"
                                        name="plan"
                                        value="{{ $planOption->value }}"
                                        class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
                                        @checked(old('plan', $subscription->plan->value) === $planOption->value)
                                        required
                                    />
                                    <span>
                                        <span class="block font-display text-base text-brand-ink">
                                            {{ $planOption === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery') }}
                                        </span>
                                        <span class="mt-1 block text-sm text-brand-ink/70">
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
                            class="mt-2 block w-full border border-brand-ink/30 bg-brand-cream px-4 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                            required
                        >
                            @foreach (['small', 'medium', 'large'] as $size)
                                @php
                                    $included = config('box.included_value_by_size.'.$size);
                                @endphp
                                <option value="{{ $size }}" @selected(old('box_size', $subscription->box_size) === $size)>
                                    {{ __(ucfirst($size)) }}
                                    @if ($included !== null)
                                        — {{ __('Included value approx. EUR :amount', ['amount' => number_format((float) $included, 0, ',', '.')]) }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('box_size')" />
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="eyebrow text-brand-ink">{{ __('Preferences') }}</legend>

                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                name="preferences[veg_only]"
                                value="1"
                                @checked((bool) old('preferences.veg_only', data_get($subscription->preferences, 'veg_only', false)))
                                class="border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
                            />
                            <span class="text-sm text-brand-ink">{{ __('Veg option only') }}</span>
                        </label>

                        <label class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                name="preferences[meat_add_on]"
                                value="1"
                                @checked((bool) old('preferences.meat_add_on', data_get($subscription->preferences, 'meat_add_on', false)))
                                class="border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
                            />
                            <span class="text-sm text-brand-ink">{{ __('Meat add-on') }}</span>
                        </label>

                        <label class="flex items-start gap-3">
                            <input
                                type="checkbox"
                                name="preferences[eggs_dairy_add_on]"
                                value="1"
                                @checked((bool) old('preferences.eggs_dairy_add_on', data_get($subscription->preferences, 'eggs_dairy_add_on', false)))
                                class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
                            />
                            <span>
                                <span class="block text-sm text-brand-ink">{{ __('Eggs or dairy add-on') }}</span>
                                <span class="mt-1 block text-xs text-brand-ink/60">
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
                            class="mt-2 block w-full"
                            :value="$allergiesValue"
                            placeholder="{{ __('e.g. nuts, gluten') }}"
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('allergies')" />
                        <x-input-error class="mt-2" :messages="$errors->get('allergies.*')" />
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <x-primary-button>{{ __('Create subscription') }}</x-primary-button>
                        <a href="{{ route('app.dashboard') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
