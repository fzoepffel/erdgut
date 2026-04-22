@extends('layouts.onboarding', ['step' => 'trial_delivery', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('When would you like us to come by?') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">
        {{ __('We schedule trial deliveries manually. We will confirm your exact date within :days days.', ['days' => $promiseDays]) }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.trial_delivery.store') }}" class="mt-6 space-y-5">
        @csrf
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
            <label for="preferred_delivery_window" class="block text-sm font-medium">{{ __('Preferred week or date') }}</label>
            <input id="preferred_delivery_window" name="preferred_delivery_window" type="text"
                   placeholder="{{ __('e.g. next week, after 6 May') }}"
                   value="{{ old('preferred_delivery_window', $data['preferred_delivery_window'] ?? '') }}"
                   class="mt-1 w-full border border-brand-ink/30 p-2">
        </div>

        <div>
            <label for="special_instructions" class="block text-sm font-medium">{{ __('Anything else we should know?') }}</label>
            <textarea id="special_instructions" name="special_instructions" rows="3" class="mt-1 w-full border border-brand-ink/30 p-2">{{ old('special_instructions', $data['special_instructions'] ?? '') }}</textarea>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="bg-chapter-produce text-white px-5 py-2 font-medium hover:bg-chapter-produce/90">{{ __('Continue to payment') }}</button>
        </div>
    </form>
@endsection
