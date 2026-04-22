@extends('layouts.onboarding', ['step' => 'subscription_schedule', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('Pick your delivery rhythm') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">{{ __('You can pause, skip, or cancel from your dashboard at any time.') }}</p>

    <form method="POST" action="{{ route('app.onboarding.step.subscription_schedule.store') }}" class="mt-6 space-y-6">
        @csrf
        <fieldset>
            <legend class="text-sm font-semibold">{{ __('Cadence') }}</legend>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($plans as $plan)
                    <label class="border border-brand-ink/30 p-4 flex flex-col gap-1 cursor-pointer has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                        <input type="radio" name="plan" value="{{ $plan->value }}" {{ ($data['plan'] ?? 'weekly') === $plan->value ? 'checked' : '' }} class="sr-only">
                        <span class="font-medium">{{ ucfirst($plan->value) }}</span>
                        <span class="text-xs text-brand-ink/70">
                            {{ $plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('plan')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </fieldset>

        <fieldset>
            <legend class="text-sm font-semibold">{{ __('Preferred timeslot') }}</legend>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($timeslots as $slot)
                    <label class="border border-brand-ink/30 p-3 flex items-center gap-2 cursor-pointer has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5">
                        <input type="radio" name="preferred_timeslot" value="{{ $slot }}" {{ ($data['preferred_timeslot'] ?? '') === $slot ? 'checked' : '' }}>
                        <span class="text-sm font-medium">{{ $slot }}</span>
                    </label>
                @endforeach
            </div>
            @error('preferred_timeslot')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <label for="delivery_instructions" class="block text-sm font-medium">{{ __('Delivery instructions') }}</label>
            <textarea id="delivery_instructions" name="delivery_instructions" rows="3" class="mt-1 w-full border border-brand-ink/30 p-2">{{ old('delivery_instructions', $data['delivery_instructions'] ?? '') }}</textarea>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-chapter-produce text-white px-5 py-2 font-medium hover:bg-chapter-produce/90">{{ __('Continue to payment') }}</button>
        </div>
    </form>
@endsection
