@extends('layouts.onboarding', ['step' => 'commitment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-produce">{{ __('Choose your start') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('How do you want to start?') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('Try one box, or sign up for a recurring delivery — you can always switch.') }}
    </p>

    {{-- Chapter-coloured commitment cards mirror the box-size pattern from
         the preferences step: title is the big display element, price is
         secondary at the bottom. eggdairy (orange) for the one-off trial,
         produce (green) for the recurring subscription. --}}
    <form method="POST" action="{{ route('app.onboarding.step.commitment.store') }}" class="mt-8 grid gap-4 md:grid-cols-2">
        @csrf
        @if($trialEnabled)
            <button type="submit" name="intent" value="trial"
                    class="group flex h-full cursor-pointer flex-col p-6 text-left text-brand-cream transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-20px_rgba(44,36,28,0.35)] focus:outline-none focus:ring-2 focus:ring-brand-ink focus:ring-offset-2 focus:ring-offset-brand-cream bg-chapter-eggdairy">
                <span aria-hidden="true" class="font-display text-3xl font-semibold leading-none text-brand-cream/55">01</span>
                <span class="mt-6 font-display text-4xl font-semibold leading-tight sm:text-5xl">{{ __('Trial box') }}</span>
                <span class="mt-3 font-editorial text-sm italic leading-relaxed text-brand-cream/85">
                    {{ __('One delivery, no subscription, no auto-renewal.') }}
                </span>
                <span class="mt-auto pt-6">
                    <span class="block font-display text-3xl font-semibold tabular-nums">@money($trialPrice, 0) €</span>
                    <span class="mt-1 block text-xs uppercase tracking-eyebrow text-brand-cream/75">{{ __('One-off') }}</span>
                </span>
                <span class="mt-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-cream/90 transition-[gap] duration-200 group-hover:gap-3 group-hover:text-brand-cream">
                    {{ __('Pick trial') }} <span aria-hidden="true">&rarr;</span>
                </span>
            </button>
        @endif
        <button type="submit" name="intent" value="subscription"
                class="group flex h-full cursor-pointer flex-col p-6 text-left text-brand-cream transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-20px_rgba(44,36,28,0.35)] focus:outline-none focus:ring-2 focus:ring-brand-ink focus:ring-offset-2 focus:ring-offset-brand-cream bg-chapter-produce">
            <span aria-hidden="true" class="font-display text-3xl font-semibold leading-none text-brand-cream/55">{{ $trialEnabled ? '02' : '01' }}</span>
            <span class="mt-6 font-display text-4xl font-semibold leading-tight sm:text-5xl">{{ __('Subscription') }}</span>
            <span class="mt-3 font-editorial text-sm italic leading-relaxed text-brand-cream/85">
                {{ __('Weekly or bi-weekly. Size :size:eggs, cancel anytime.', [
                    'size' => __(ucfirst($subscriptionSize)),
                    'eggs' => $subscriptionEggsAddOn ? ' + '.__('Add eggs to every box') : '',
                ]) }}
            </span>
            <span class="mt-auto pt-6">
                <span class="block font-display text-3xl font-semibold tabular-nums">@money($subscriptionPerDelivery) €</span>
                <span class="mt-1 block text-xs uppercase tracking-eyebrow text-brand-cream/75">{{ __('Per delivery') }}</span>
            </span>
            <span class="mt-4 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-cream/90 transition-[gap] duration-200 group-hover:gap-3 group-hover:text-brand-cream">
                {{ __('Pick subscription') }} <span aria-hidden="true">&rarr;</span>
            </span>
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
