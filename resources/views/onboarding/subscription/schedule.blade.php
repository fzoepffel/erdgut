@extends('layouts.onboarding', ['step' => 'subscription_schedule', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-fish">{{ __('Schedule') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Pick your delivery rhythm') }}</h1>
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
                    <label class="flex cursor-pointer items-center justify-center gap-3 border border-brand-ink/20 p-5 transition hover:border-chapter-produce/60 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/[0.06] has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="plan" value="{{ $plan->value }}" {{ ($data['plan'] ?? 'weekly') === $plan->value ? 'checked' : '' }} class="sr-only">
                        <span class="text-center">
                            <span class="block font-display text-lg font-semibold text-brand-ink">
                                {{ $plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}
                            </span>
                            <span class="mt-1 block font-editorial text-xs italic text-brand-ink/65">
                                {{ $plan->value === 'weekly' ? __('A box every Monday rhythm.') : __('Every other Monday — half the volume, same care.') }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('plan')" class="mt-3" />
        </fieldset>

        @php
            // Distinct chapter colours per fulfillment mode so the choice
            // reads as two different things, not "two of the same thing."
            $modeChapter = [
                \App\Enums\FulfillmentMode::Delivery->value => [
                    'border' => 'has-[:checked]:border-chapter-fish has-[:checked]:bg-chapter-fish/[0.06] has-[:checked]:ring-chapter-fish hover:border-chapter-fish/60',
                    'eyebrow' => 'text-chapter-fish',
                    'chapter' => 'fish',
                ],
                \App\Enums\FulfillmentMode::Pickup->value => [
                    'border' => 'has-[:checked]:border-chapter-bread has-[:checked]:bg-chapter-bread/[0.06] has-[:checked]:ring-chapter-bread hover:border-chapter-bread/60',
                    'eyebrow' => 'text-chapter-bread',
                    'chapter' => 'bread',
                ],
            ];
        @endphp
        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Fulfillment') }}</legend>
            <p class="mt-2 text-sm text-brand-ink/70">{{ __('We deliver to your door or you pick up at our partner stall.') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach($fulfillmentModes as $mode)
                    <label class="flex cursor-pointer items-start gap-3 border border-brand-ink/20 p-5 transition has-[:checked]:ring-1 {{ $modeChapter[$mode->value]['border'] }}">
                        <input type="radio" name="fulfillment_mode" value="{{ $mode->value }}" x-model="fulfillment" class="mt-1 border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce">
                        <span class="flex flex-col gap-1">
                            <span class="eyebrow {{ $modeChapter[$mode->value]['eyebrow'] }}">
                                {{ $mode === \App\Enums\FulfillmentMode::Delivery ? __('To you') : __('From the market') }}
                            </span>
                            <span class="font-display text-base text-brand-ink">
                                {{ $mode === \App\Enums\FulfillmentMode::Delivery ? __('Home delivery') : __('Pickup') }}
                            </span>
                            <span class="text-sm leading-relaxed text-brand-ink/70">
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

        {{-- Pickup-mode-only pickup location panel — replaces the
             delivery_instructions textarea (which is meaningless when there is
             no delivery). Shows the mock pickup location from config/box.php
             until the real partner location is finalized. --}}
        @php
            $pickup = config('box.pickup_location');
        @endphp
        <div x-show="fulfillment === '{{ \App\Enums\FulfillmentMode::Pickup->value }}'" x-cloak class="border border-chapter-bread/30 bg-chapter-bread/5 p-5">
            <p class="eyebrow text-chapter-bread">{{ __('Pickup location') }}</p>
            <p class="mt-2 font-display text-base text-brand-ink">{{ $pickup['name'] }}</p>
            <p class="text-sm text-brand-ink/75">{{ $pickup['street'] }} · {{ $pickup['postal_code'] }} {{ $pickup['city'] }}</p>
            <p class="mt-1 text-sm text-brand-ink/75">{{ $pickup['window'] }}</p>
            <p class="mt-3 text-xs italic text-brand-ink/60">{{ __('More pickup locations coming soon.') }}</p>
        </div>

        <div x-show="fulfillment === '{{ \App\Enums\FulfillmentMode::Delivery->value }}'" x-cloak>
            <x-input-label for="delivery_instructions" :value="__('Delivery instructions')" />
            <textarea id="delivery_instructions" name="delivery_instructions" rows="3"
                      class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:ring-0">{{ old('delivery_instructions', $data['delivery_instructions'] ?? '') }}</textarea>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-4">
            @php
                // When intent=subscription, Commitment was skipped on the way
                // here (W2-04 / CommitmentStepSkippableTest), so a back link
                // to Commitment lands on a step that bounces forward — feels
                // like nothing happens. Send the customer back to the actual
                // previous step they were on (Preferences) instead.
                $cameViaCommitment = ($draft->intent ?? null) === \App\Services\Onboarding\OnboardingDraftManager::INTENT_UNDECIDED;
                $backRoute = $cameViaCommitment
                    ? route('app.onboarding.step.commitment')
                    : route('app.onboarding.step.preferences');
                $backLabel = $cameViaCommitment ? __('Commitment') : __('Preferences');
            @endphp
            <a href="{{ $backRoute }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('Back to :step', ['step' => $backLabel]) }}
            </a>
            <x-primary-button>{{ __('Continue to :step', ['step' => __('Payment')]) }}</x-primary-button>
        </div>
    </form>
@endsection
