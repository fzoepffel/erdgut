@extends('layouts.onboarding', ['step' => 'subscription_schedule', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-fish">{{ __('Your schedule') }}</p>
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
                    <label class="flex cursor-pointer flex-col gap-2 border border-brand-ink/20 p-5 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="plan" value="{{ $plan->value }}" {{ ($data['plan'] ?? 'weekly') === $plan->value ? 'checked' : '' }} class="sr-only">
                        <span class="font-display text-lg font-semibold text-brand-ink">{{ ucfirst($plan->value) }}</span>
                        <span class="text-xs leading-relaxed text-brand-ink/70">
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

        <fieldset x-show="fulfillment === '{{ \App\Enums\FulfillmentMode::Delivery->value }}'" x-cloak>
            <legend class="eyebrow text-brand-ink/80">{{ __('Preferred timeslot') }}</legend>
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach($timeslots as $slot)
                    <label class="flex cursor-pointer items-center justify-center border border-brand-ink/20 p-4 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                        <input type="radio" name="preferred_timeslot" value="{{ $slot }}" {{ ($data['preferred_timeslot'] ?? '') === $slot ? 'checked' : '' }} class="sr-only">
                        <span class="text-sm font-medium text-brand-ink">{{ $slot }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('preferred_timeslot')" class="mt-3" />
        </fieldset>

        <div>
            <x-input-label for="delivery_instructions" :value="__('Delivery instructions')" />
            <textarea id="delivery_instructions" name="delivery_instructions" rows="3"
                      class="mt-2 w-full rounded-none border-0 border-b border-brand-ink/30 bg-transparent px-0 py-2 text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:ring-0">{{ old('delivery_instructions', $data['delivery_instructions'] ?? '') }}</textarea>
        </div>

        <div class="flex justify-end pt-4">
            <x-primary-button>{{ __('Continue to payment') }}</x-primary-button>
        </div>
    </form>
@endsection
