@extends('layouts.onboarding', ['step' => 'trial_payment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <dl class="border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Order #') }}</dt>
            <dd class="font-mono text-brand-ink">{{ $order->id }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
            <dd class="text-brand-ink">{{ __(ucfirst($order->box_size)) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Amount') }}</dt>
            <dd class="font-display text-lg text-brand-ink">EUR @money($order->amount)</dd>
        </div>
    </dl>

    @if ($checkoutId)
        <div class="mt-8">
            <x-onboarding.payment-expectations intent="trial" />

            <h2 id="payment-widget-heading" class="sr-only">{{ __('Card payment form') }}</h2>
            <x-payment.sumup-widget
                :checkout-id="$checkoutId"
                :return-url="$returnUrl"
                intent="one_time"
                :require-consent="true"
            />

            <x-payment.security-badge />

            <div class="mt-6">
                <a href="{{ route('app.onboarding.step.trial_delivery') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                    ← {{ __('onboarding.cta.back') }}
                </a>
            </div>
        </div>
    @else
        <div class="mt-6 border border-chapter-meat/40 bg-chapter-meat/5 p-5 text-sm text-brand-ink" role="alert">
            <p class="eyebrow text-chapter-meat">{{ __('Payment could not be started') }}</p>
            <p class="mt-2 leading-relaxed">
                {{ __('We could not reach the payment provider just now. Please try again in a moment — no charge has been made.') }}
            </p>
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-4 text-sm">
            <a
                href="{{ route('app.onboarding.step.trial_payment') }}"
                class="btn-solid"
                data-testid="trial-payment-retry"
            >
                {{ __('Try again') }}
            </a>
            <a href="{{ route('app.onboarding.step.commitment') }}" class="text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                {{ __('Back') }}
            </a>
        </div>
    @endif
@endsection
