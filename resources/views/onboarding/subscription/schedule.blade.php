@extends('layouts.onboarding', ['step' => 'subscription_schedule', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Pick your delivery rhythm') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('You can pause, skip, or cancel from your dashboard at any time.') }}
    </p>

    <form
        method="POST"
        action="{{ route('app.onboarding.step.subscription_schedule.store') }}"
        class="mt-8 space-y-8"
        x-data="{ fulfillment: '{{ $data['fulfillment_mode'] ?? \App\Enums\FulfillmentMode::Delivery->value }}' }"
    >
        @csrf
        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Cadence') }}</legend>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach($plans as $plan)
                    <label class="flex cursor-pointer items-center justify-center border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="plan" value="{{ $plan->value }}" {{ ($data['plan'] ?? 'weekly') === $plan->value ? 'checked' : '' }} class="sr-only">
                        <span class="font-display text-base font-semibold text-brand-ink">
                            {{ $plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('plan')" class="mt-3" />
        </fieldset>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Fulfillment') }}</legend>
            <p class="mt-2 text-sm text-brand-ink/70">{{ __('We deliver to your door or you pick up at our partner stall.') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach($fulfillmentModes as $mode)
                    <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="fulfillment_mode" value="{{ $mode->value }}" x-model="fulfillment" class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce">
                        <span>
                            <span class="block font-display text-base text-brand-ink">
                                {{ $mode === \App\Enums\FulfillmentMode::Delivery ? __('Home delivery') : __('Pickup') }}
                            </span>
                            <span class="mt-1 block text-sm text-brand-ink/70">
                                {{ $mode === \App\Enums\FulfillmentMode::Delivery
                                    ? __('We bring the box to your address within your neighbourhood window.')
                                    : __('Collect at the weekly partner market — no delivery fee.') }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-3" />
        </fieldset>

        @php
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

        <div class="grid gap-6 sm:grid-cols-2" x-show="fulfillment === '{{ \App\Enums\FulfillmentMode::Delivery->value }}'" x-cloak>
            <div>
                <x-input-label for="delivery_weekday" :value="__('Preferred weekday')" />
                <select id="delivery_weekday" name="delivery_weekday"
                        class="mt-2 block w-full border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0">
                    {{-- W2-11: no 'No preference' placeholder. The controller
                         pre-selects the first valid weekday so a click-through
                         customer submits a valid value. --}}
                    @foreach($allowedWeekdays as $dayValue)
                        <option value="{{ $dayValue }}" {{ (string) ($data['delivery_weekday'] ?? '') === (string) $dayValue ? 'selected' : '' }}>
                            {{ $weekdayLabels[$dayValue] ?? $dayValue }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('delivery_weekday')" class="mt-2" />
                <p class="mt-2 text-xs text-brand-ink/55">{{ __('Pick any day within your zone window. Admin may adjust an individual delivery if needed.') }}</p>
            </div>

            <div>
                <x-input-label for="preferred_timeslot" :value="__('Preferred timeslot')" />
                <select id="preferred_timeslot" name="preferred_timeslot"
                        class="mt-2 block w-full border border-brand-ink/30 bg-white px-3 py-2.5 text-base text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0">
                    @foreach($timeslots as $slot)
                        <option value="{{ $slot }}" {{ ($data['preferred_timeslot'] ?? '') === $slot ? 'selected' : '' }}>
                            {{ $slot }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('preferred_timeslot')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="delivery_instructions" :value="__('Delivery instructions')" />
            <textarea id="delivery_instructions" name="delivery_instructions" rows="3"
                      class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:ring-0">{{ old('delivery_instructions', $data['delivery_instructions'] ?? '') }}</textarea>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-4">
            <a href="{{ route('app.onboarding.step.commitment') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('Back to :step', ['step' => __('Commitment')]) }}
            </a>
            <x-primary-button>{{ __('Continue to :step', ['step' => __('Payment')]) }}</x-primary-button>
        </div>
    </form>
@endsection
