<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-produce">{{ __('Subscription') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Edit subscription') }}
            </h1>
            <p class="mt-3 max-w-2xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ __('Adjust the cadence, shape it to your week.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="border border-brand-ink/15 p-6 sm:p-8">
                <p class="text-base text-brand-ink/80">
                    {{ __('Adjust your delivery rhythm, box size, preferences, and allergies.') }}
                </p>

                <form method="post" action="{{ route('app.subscriptions.update') }}" class="mt-6 space-y-10">
                    @csrf
                    @method('patch')

                    <fieldset>
                        <legend class="eyebrow text-brand-ink">{{ __('Fulfillment') }}</legend>
                        <p class="mt-2 text-sm text-brand-ink/70">{{ __('Switch between home delivery and pickup at any time.') }}</p>
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach (\App\Enums\FulfillmentMode::cases() as $modeOption)
                                <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                                    <input
                                        type="radio"
                                        name="fulfillment_mode"
                                        value="{{ $modeOption->value }}"
                                        class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
                                        @checked(old('fulfillment_mode', $subscription->fulfillment_mode?->value ?? \App\Enums\FulfillmentMode::Delivery->value) === $modeOption->value)
                                        required
                                    />
                                    <span>
                                        <span class="block font-display text-base text-brand-ink">
                                            {{ $modeOption === \App\Enums\FulfillmentMode::Delivery ? __('Home delivery') : __('Pickup') }}
                                        </span>
                                        <span class="mt-1 block text-sm text-brand-ink/70">
                                            {{ $modeOption === \App\Enums\FulfillmentMode::Delivery
                                                ? __('We bring the box to your address within your neighbourhood window.')
                                                : __('Collect at the weekly partner market — no delivery fee.') }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error class="mt-2" :messages="$errors->get('fulfillment_mode')" />
                    </fieldset>

                    <fieldset>
                        <legend class="eyebrow text-brand-ink">{{ __('Cadence') }}</legend>
                        <p class="mt-2 text-sm text-brand-ink/70">{{ __('Changing the rhythm while your subscription is active updates your next charge date from today forward.') }}</p>
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach (\App\Enums\SubscriptionPlan::cases() as $planOption)
                                <label class="flex cursor-pointer items-center justify-center border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                                    <input
                                        type="radio"
                                        name="plan"
                                        value="{{ $planOption->value }}"
                                        class="sr-only"
                                        @checked(old('plan', $subscription->plan->value) === $planOption->value)
                                        required
                                    />
                                    <span class="font-display text-base font-semibold text-brand-ink">
                                        {{ $planOption === \App\Enums\SubscriptionPlan::Weekly ? __('Every week') : __('Every two weeks') }}
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
                                <option value="{{ $size }}" @selected(old('box_size', $subscription->box_size) === $size)>
                                    {{ __(ucfirst($size)) }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('box_size')" />
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="eyebrow text-brand-ink">{{ __('Extras') }}</legend>

                        {{-- veg_only and meat_add_on are forced product policy for now
                             (all boxes are vegetable + fruit only). Hidden inputs keep
                             the existing flags + composition logic intact. --}}
                        <input type="hidden" name="preferences[veg_only]" value="1">
                        <input type="hidden" name="preferences[meat_add_on]" value="0">

                        <label class="flex items-start gap-3">
                            <input type="hidden" name="preferences[eggs_dairy_add_on]" value="0">
                            <input
                                type="checkbox"
                                name="preferences[eggs_dairy_add_on]"
                                value="1"
                                @checked((bool) old('preferences.eggs_dairy_add_on', data_get($subscription->preferences, 'eggs_dairy_add_on', false)))
                                class="mt-1 border-brand-ink/30 text-chapter-eggdairy focus:ring-chapter-eggdairy"
                            />
                            <span>
                                <span class="block text-sm text-brand-ink">{{ __('Add eggs to every box') }}</span>
                                <span class="mt-1 block text-xs text-brand-ink/60">
                                    {{ __('Six fresh free-range eggs from regional farms. + EUR :amount per delivery.', ['amount' => \App\Support\Money::format(config('box.eggs_dairy_add_on_eur'))]) }}
                                </span>
                            </span>
                        </label>

                        <x-input-error class="mt-2" :messages="$errors->get('preferences.eggs_dairy_add_on')" />
                    </fieldset>

                    @php
                        $zone = app(\App\Support\DeliveryZoneResolver::class);
                        $zoneKey = $zone->resolveZoneKey(auth()->id());
                        $allowedWeekdays = $zone->allowedWeekdays($zoneKey);
                        $allowedTimeslots = $zone->allowedTimeslots($zoneKey);
                        $weekdayLabels = [
                            0 => __('Sunday'),
                            1 => __('Monday'),
                            2 => __('Tuesday'),
                            3 => __('Wednesday'),
                            4 => __('Thursday'),
                            5 => __('Friday'),
                            6 => __('Saturday'),
                        ];
                    @endphp

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="delivery_weekday" :value="__('Preferred weekday')" />
                            <select
                                id="delivery_weekday"
                                name="delivery_weekday"
                                class="mt-2 block w-full border border-brand-ink/30 bg-brand-cream px-4 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                            >
                                <option value="">{{ __('No preference') }}</option>
                                @foreach ($allowedWeekdays as $dayValue)
                                    <option value="{{ $dayValue }}" @selected((string) old('delivery_weekday', $subscription->delivery_weekday) === (string) $dayValue)>
                                        {{ $weekdayLabels[$dayValue] ?? $dayValue }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('delivery_weekday')" />
                        </div>
                        <div>
                            <x-input-label for="preferred_timeslot" :value="__('Preferred timeslot')" />
                            <select
                                id="preferred_timeslot"
                                name="preferred_timeslot"
                                class="mt-2 block w-full border border-brand-ink/30 bg-brand-cream px-4 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                            >
                                <option value="">{{ __('No preference') }}</option>
                                @foreach ($allowedTimeslots as $slot)
                                    <option value="{{ $slot }}" @selected(old('preferred_timeslot', $subscription->preferred_timeslot?->value) === $slot)>{{ $slot }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('preferred_timeslot')" />
                        </div>
                    </div>

                    @php
                        $oldAllergies = old('allergies');
                        $selectedAllergens = is_array($oldAllergies) ? $oldAllergies : ($subscription->allergies ?? []);
                        $allergiesNoteValue = old('allergies_note', $subscription->allergies_note ?? '');
                    @endphp
                    @include('subscriptions._allergies_field')

                    @php
                        $effectiveFrom = now()->copy()->addMonth()->startOfWeek()->format('d.m.Y');
                    @endphp
                    <div class="border-l-2 border-brand-ink/30 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink/80">
                        <p class="font-medium text-brand-ink">{{ __('Takes effect from the week of :date', ['date' => $effectiveFrom]) }}</p>
                        <p class="mt-1 font-editorial italic">
                            {{ __('Orders and changes need a month\'s lead time — we\'re still improvising our logistics. For urgent changes, write to us.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                        <a href="{{ route('app.subscriptions.show') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                            {{ __('Cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
