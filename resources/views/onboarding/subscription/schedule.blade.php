@extends('layouts.onboarding', ['step' => 'subscription_schedule', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
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

    <form
        method="POST"
        action="{{ route('app.onboarding.step.subscription_schedule.store') }}"
        class="space-y-12"
        x-data="{ fulfillment: '{{ $data['fulfillment_mode'] ?? \App\Enums\FulfillmentMode::Delivery->value }}' }"
    >
        @csrf

        {{-- Be honest about what we actually deliver: the picker exposes
             whatever the zone config allows; today that's Wed 12–16 only. --}}
        <p class="border-l-2 border-chapter-produce bg-brand-paper px-4 py-3 text-sm leading-relaxed text-brand-ink/70" data-testid="schedule-zone-hint">
            {{ __('We currently deliver only Wednesdays 12–16 in Berlin. The schedule below reflects that — once we expand, more days and timeslots will appear.') }}
        </p>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Cadence') }}</legend>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($plans as $plan)
                    <label class="group relative flex cursor-pointer flex-col border border-brand-ink/15 bg-brand-cream p-6 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-brand-paper">
                        <input type="radio" name="plan" value="{{ $plan->value }}" {{ ($data['plan'] ?? 'weekly') === $plan->value ? 'checked' : '' }} class="sr-only">
                        <span class="font-display text-xl text-brand-ink">
                            {{ $plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}
                        </span>
                        <span class="mt-2 font-editorial text-sm italic leading-snug text-brand-ink/60">
                            {{ $plan->value === 'weekly' ? __('A box every week.') : __('Every other week — half the volume, same care.') }}
                        </span>
                        <span class="mt-4 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-produce opacity-0 transition group-has-[:checked]:opacity-100">
                            · {{ __('onboarding.chosen') }}
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('plan')" class="mt-3" />
        </fieldset>

        <fieldset>
            <legend class="eyebrow text-brand-ink/80">{{ __('Fulfillment') }}</legend>
            <p class="mt-2 text-sm text-brand-ink/60">{{ __('We deliver to your door or you pick up at our partner stall.') }}</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach($fulfillmentModes as $mode)
                    <label class="group relative flex cursor-pointer flex-col border border-brand-ink/15 bg-brand-cream p-6 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-brand-paper">
                        <input type="radio" name="fulfillment_mode" value="{{ $mode->value }}" x-model="fulfillment" class="sr-only">
                        <span class="font-display text-xl text-brand-ink">
                            {{ $mode === \App\Enums\FulfillmentMode::Delivery ? __('Home delivery') : __('Pickup') }}
                        </span>
                        <span class="mt-2 font-editorial text-sm italic leading-snug text-brand-ink/60">
                            {{ $mode === \App\Enums\FulfillmentMode::Delivery
                                ? __('We bring the box to your address within your neighbourhood window.')
                                : __('Collect at the weekly partner market — no delivery fee.') }}
                        </span>
                        <span class="mt-4 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-produce opacity-0 transition group-has-[:checked]:opacity-100">
                            · {{ __('onboarding.chosen') }}
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('fulfillment_mode')" class="mt-3" />
        </fieldset>

        <div class="grid gap-8 sm:grid-cols-2" x-show="fulfillment === '{{ \App\Enums\FulfillmentMode::Delivery->value }}'" x-cloak>
            <div>
                <x-input-label for="delivery_weekday" :value="__('Preferred weekday')" />
                <select id="delivery_weekday" name="delivery_weekday"
                        class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink focus:border-brand-ink focus:outline-none focus:ring-0">
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
                        class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink focus:border-brand-ink focus:outline-none focus:ring-0">
                    @foreach($timeslots as $slot)
                        <option value="{{ $slot }}" {{ ($data['preferred_timeslot'] ?? '') === $slot ? 'selected' : '' }}>
                            {{ $slot }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('preferred_timeslot')" class="mt-2" />
            </div>
        </div>

        @php
            $pickup = config('box.pickup_location');
        @endphp
        <div x-show="fulfillment === '{{ \App\Enums\FulfillmentMode::Pickup->value }}'" x-cloak class="border-l-2 border-chapter-produce bg-brand-paper p-5">
            <p class="eyebrow text-chapter-produce">{{ __('Pickup location') }}</p>
            <p class="mt-2 font-display text-base text-brand-ink">{{ $pickup['name'] }}</p>
            <p class="text-sm text-brand-ink/75">{{ $pickup['street'] }} · {{ $pickup['postal_code'] }} {{ $pickup['city'] }}</p>
            <p class="mt-1 text-sm text-brand-ink/75">{{ $pickup['window'] }}</p>
            <p class="mt-3 text-xs italic text-brand-ink/60">{{ __('More pickup locations coming soon.') }}</p>
        </div>

        @php
            // Subscribers skipped Commitment on the way here, so a back link to
            // Commitment would bounce forward. Send them to the real previous
            // step (Preferences) instead.
            $cameViaCommitment = ($draft->intent ?? null) === \App\Services\Onboarding\OnboardingDraftManager::INTENT_UNDECIDED;
            $backRoute = $cameViaCommitment
                ? route('app.onboarding.step.commitment')
                : route('app.onboarding.step.preferences');
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-brand-ink/10 pt-8">
            <a href="{{ $backRoute }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('onboarding.cta.back') }}
            </a>
            <x-primary-button>{{ __('onboarding.cta.to_payment') }}</x-primary-button>
        </div>
    </form>
@endsection
