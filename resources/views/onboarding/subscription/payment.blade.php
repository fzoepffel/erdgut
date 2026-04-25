@extends('layouts.onboarding', ['step' => 'subscription_payment', 'intent' => 'subscription', 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-meat">{{ __('Last step — your card') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Add your card') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('SumUp securely stores a token so we can charge each weekly delivery. We never store the full card number.') }}
    </p>

    <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Plan') }}</dt>
            <dd class="text-brand-ink">{{ ucfirst($subscription->plan->value) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
            <dd class="text-brand-ink">{{ __(ucfirst($subscription->box_size)) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('First box') }}</dt>
            <dd class="font-display text-lg text-brand-ink">EUR {{ number_format((float) config('box.trial_price_ttc', 35), 2) }}</dd>
        </div>
    </dl>

    <div class="mt-8">
        <x-onboarding.payment-expectations intent="subscription" />

        <h2 id="payment-widget-heading" class="sr-only">{{ __('Card payment form') }}</h2>
        <x-payment.sumup-widget
            :checkout-id="$checkoutId"
            :return-url="route('app.payments.setup.complete')"
            intent="setup_recurring"
        />

        <x-payment.security-badge />
    </div>
@endsection
