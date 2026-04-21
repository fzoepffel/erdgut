@extends('layouts.onboarding', ['step' => 'commitment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('How do you want to start?') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">{{ __('Try one box, or sign up for a recurring delivery — you can always switch.') }}</p>

    <form method="POST" action="{{ route('app.onboarding.step.commitment.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
        @csrf
        @if($trialEnabled)
            <label class="border border-brand-ink/30 p-5 flex flex-col gap-3 cursor-pointer has-[:checked]:border-category-produce has-[:checked]:bg-category-produce/5">
                <input type="radio" name="intent" value="trial" {{ $draft->intent === 'trial' ? 'checked' : '' }} class="sr-only">
                <span class="eyebrow text-category-produce">{{ __('Try it once') }}</span>
                <span class="block font-display text-2xl font-semibold">EUR {{ number_format($trialPrice, 0) }}</span>
                <span class="text-sm text-brand-ink/70">{{ __('One delivery, no subscription, no auto-renewal.') }}</span>
                <span class="mt-auto text-sm font-medium text-category-produce">{{ __('Pick trial') }} →</span>
            </label>
        @endif
        <label class="border border-brand-ink/30 p-5 flex flex-col gap-3 cursor-pointer has-[:checked]:border-category-produce has-[:checked]:bg-category-produce/5">
            <input type="radio" name="intent" value="subscription" {{ $draft->intent === 'subscription' ? 'checked' : '' }} class="sr-only">
            <span class="eyebrow text-category-produce">{{ __('Regular delivery') }}</span>
            <span class="block font-display text-2xl font-semibold">{{ __('Subscribe') }}</span>
            <span class="text-sm text-brand-ink/70">{{ __('Weekly or bi-weekly. First box EUR 35, cancel anytime.') }}</span>
            <span class="mt-auto text-sm font-medium text-category-produce">{{ __('Pick subscription') }} →</span>
        </label>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="bg-category-produce text-white px-5 py-2 font-medium hover:bg-category-produce/90">{{ __('Continue') }}</button>
        </div>
        @error('intent')<p class="md:col-span-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </form>
@endsection
