@extends('layouts.onboarding', ['step' => 'subscription_payment', 'intent' => 'subscription', 'draft' => $draft])

@section('content')
    @php
        // Every charge is the same number — size-base + eggs. The calculator
        // owns these numbers; if they change here they change everywhere.
        $eggsAddOn = (bool) data_get($subscription->preferences, 'eggs_dairy_add_on', false);
        $eggsAddOnEur = (float) config('box.eggs_dairy_add_on_eur', 4.5);
        $perDelivery = $subscription->expectedChargeAmount();
    @endphp

    <div class="space-y-12">
        {{-- Line items --}}
        <dl class="border-t border-brand-ink/15 text-sm text-brand-ink/80">
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-3">
                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/50">{{ __('Plan') }}</dt>
                <dd class="text-brand-ink">{{ $subscription->plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-3">
                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/50">{{ __('Box size') }}</dt>
                <dd class="text-brand-ink">{{ __(ucfirst($subscription->box_size)) }}</dd>
            </div>
            @if ($eggsAddOn)
                <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-3">
                    <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/50">{{ __('Eggs add-on') }}</dt>
                    <dd class="text-brand-ink">+ {{ __('EUR :amount / delivery', ['amount' => \App\Support\Money::format($eggsAddOnEur)]) }}</dd>
                </div>
            @endif
            <div class="flex items-baseline justify-between gap-4 py-4">
                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/50">{{ __('Total per delivery') }}</dt>
                <dd class="font-display text-3xl text-brand-ink">EUR @money($perDelivery)</dd>
            </div>
        </dl>

        <x-onboarding.payment-expectations intent="subscription" />

        {{-- SumUp card-on-file widget. Keeps the existing wiring: the widget
             renders the card form, posts the token to SumUp, and returns to
             the setup-complete route which finalises the subscription. --}}
        <div>
            <h2 id="payment-widget-heading" class="eyebrow text-chapter-produce">{{ __('Payment method') }}</h2>
            <div class="mt-4 bg-brand-paper p-6 lg:p-8">
                <x-payment.sumup-widget
                    :checkout-id="$checkoutId"
                    :return-url="route('app.payments.setup.complete')"
                    intent="setup_recurring"
                    :require-consent="true"
                />
                <x-payment.security-badge />
            </div>
        </div>

        {{-- Final review using the same summary component the sidebar shows. --}}
        <section class="border-t border-brand-ink/10 pt-8" data-testid="payment-final-review">
            <h2 class="font-display text-2xl text-brand-ink">{{ __('Review before you pay') }}</h2>
            <p class="mt-2 text-sm text-brand-ink/60">{{ __('Everything you have told us so far. Tap any block to change it before adding your card.') }}</p>
            <div class="mt-4 max-w-md">
                <x-onboarding.summary :draft="$draft" :user="auth()->user()" />
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-brand-ink/10 pt-8">
            <a href="{{ route('app.onboarding.step.subscription_schedule') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('onboarding.cta.back') }}
            </a>
        </div>
    </div>
@endsection
