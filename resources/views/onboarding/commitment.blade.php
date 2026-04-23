@extends('layouts.onboarding', ['step' => 'commitment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-produce">{{ __('Your commitment') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('How do you want to start?') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Try one box, or sign up for a recurring delivery — you can always switch.') }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.commitment.store') }}" class="mt-8 grid gap-4 md:grid-cols-2">
        @csrf
        @if($trialEnabled)
            <label class="flex cursor-pointer flex-col gap-3 border border-brand-ink/20 p-6 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
                <input type="radio" name="intent" value="trial" {{ $draft->intent === 'trial' ? 'checked' : '' }} class="sr-only">
                <span class="eyebrow text-chapter-produce">{{ __('Try it once') }}</span>
                <span class="block font-display text-3xl font-semibold text-brand-ink">{{ number_format($trialPrice, 0) }} EUR</span>
                <span class="text-sm leading-relaxed text-brand-ink/70">{{ __('One delivery, no subscription, no auto-renewal.') }}</span>
                <span class="mt-auto text-xs font-semibold uppercase tracking-eyebrow text-chapter-produce">{{ __('Pick trial') }} <span aria-hidden="true">&rarr;</span></span>
            </label>
        @endif
        <label class="flex cursor-pointer flex-col gap-3 border border-brand-ink/20 p-6 transition hover:border-brand-ink/40 has-[:checked]:border-chapter-produce has-[:checked]:bg-chapter-produce/5 has-[:checked]:ring-1 has-[:checked]:ring-chapter-produce">
            <input type="radio" name="intent" value="subscription" {{ $draft->intent === 'subscription' ? 'checked' : '' }} class="sr-only">
            <span class="eyebrow text-chapter-produce">{{ __('Regular delivery') }}</span>
            <span class="block font-display text-3xl font-semibold text-brand-ink">{{ __('Subscribe') }}</span>
            <span class="text-sm leading-relaxed text-brand-ink/70">{{ __('Weekly or bi-weekly. First box EUR 35, cancel anytime.') }}</span>
            <span class="mt-auto text-xs font-semibold uppercase tracking-eyebrow text-chapter-produce">{{ __('Pick subscription') }} <span aria-hidden="true">&rarr;</span></span>
        </label>
        <div class="md:col-span-2">
            <x-input-error :messages="$errors->get('intent')" />
        </div>
        <div class="flex justify-end pt-2 md:col-span-2">
            <x-primary-button>{{ __('Continue') }}</x-primary-button>
        </div>
    </form>
@endsection
