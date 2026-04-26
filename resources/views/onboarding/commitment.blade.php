@extends('layouts.onboarding', ['step' => 'commitment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('How do you want to start?') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Try one box, or sign up for a recurring delivery — you can always switch.') }}
    </p>

    <form method="POST" action="{{ route('app.onboarding.step.commitment.store') }}" class="mt-8 grid gap-4 md:grid-cols-2">
        @csrf
        @if($trialEnabled)
            <button type="submit" name="intent" value="trial"
                    class="group flex cursor-pointer flex-col gap-3 border border-brand-ink/20 p-6 text-left transition hover:border-chapter-produce hover:bg-chapter-produce/5 focus:outline-none focus:ring-2 focus:ring-chapter-produce focus:ring-offset-2">
                <span class="eyebrow text-chapter-produce">{{ __('Try it once') }}</span>
                <span class="block font-display text-3xl font-semibold text-brand-ink">{{ number_format($trialPrice, 0) }} EUR</span>
                <span class="text-sm leading-relaxed text-brand-ink/70">{{ __('One delivery, no subscription, no auto-renewal.') }}</span>
                <span class="mt-auto text-xs font-semibold uppercase tracking-eyebrow text-chapter-produce">{{ __('Pick trial') }} <span aria-hidden="true">&rarr;</span></span>
            </button>
        @endif
        <button type="submit" name="intent" value="subscription"
                class="group flex cursor-pointer flex-col gap-3 border border-brand-ink/20 p-6 text-left transition hover:border-chapter-produce hover:bg-chapter-produce/5 focus:outline-none focus:ring-2 focus:ring-chapter-produce focus:ring-offset-2">
            <span class="eyebrow text-chapter-produce">{{ __('Regular delivery') }}</span>
            <span class="block font-display text-3xl font-semibold text-brand-ink">{{ __('Subscribe') }}</span>
            <span class="text-sm leading-relaxed text-brand-ink/70">{{ __('Weekly or bi-weekly. First box EUR :first, then from EUR :recurring per delivery, cancel anytime.', ['first' => number_format((float) config('box.trial_price_ttc', 35), 0), 'recurring' => number_format((float) config('box.included_value_by_size.small', 20), 0)]) }}</span>
            <span class="mt-auto text-xs font-semibold uppercase tracking-eyebrow text-chapter-produce">{{ __('Pick subscription') }} <span aria-hidden="true">&rarr;</span></span>
        </button>
        <div class="md:col-span-2">
            <x-input-error :messages="$errors->get('intent')" />
        </div>
        <div class="md:col-span-2">
            <a href="{{ route('app.onboarding.step.preferences') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('Back to :step', ['step' => __('Preferences')]) }}
            </a>
        </div>
    </form>
@endsection
