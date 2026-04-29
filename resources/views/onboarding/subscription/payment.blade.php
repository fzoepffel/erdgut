@extends('layouts.onboarding', ['step' => 'subscription_payment', 'intent' => 'subscription', 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-meat">{{ __('Last step — your card') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Add your card') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('SumUp securely stores a token so we can charge each weekly delivery. We never store the full card number.') }}
    </p>

    @php
        // W2-16 item 3: every charge is the same number — size-base + eggs.
        // No more "first box" vs "following deliveries" split. The
        // calculator owns these numbers; if they change here they change
        // everywhere (preferences cards, dashboard panel, SumUp checkout).
        $eggsAddOn = (bool) data_get($subscription->preferences, 'eggs_dairy_add_on', false);
        $eggsAddOnEur = (float) config('box.eggs_dairy_add_on_eur', 4.5);
        $perDelivery = $subscription->expectedChargeAmount();
    @endphp

    <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Plan') }}</dt>
            <dd class="text-brand-ink">{{ $subscription->plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
            <dd class="text-brand-ink">{{ __(ucfirst($subscription->box_size)) }}</dd>
        </div>
        @if ($eggsAddOn)
            <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
                <dt class="eyebrow text-brand-ink/60">{{ __('Eggs add-on') }}</dt>
                <dd class="text-brand-ink">+ {{ __('EUR :amount / delivery', ['amount' => \App\Support\Money::format($eggsAddOnEur)]) }}</dd>
            </div>
        @endif
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Total per delivery') }}</dt>
            <dd class="font-display text-lg text-brand-ink">EUR @money($perDelivery)</dd>
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

        {{-- W2-22 follow-up: a final review card at the bottom of the
             payment step using the same summary component the right rail
             shows. Lets the customer scan everything one last time before
             handing over their card without scrolling back through the
             wizard. Inline mode (not sticky) so it sits in document flow
             below the SumUp widget. --}}
        <section class="mt-10 border-t border-brand-ink/10 pt-8" data-testid="payment-final-review">
            <h2 class="font-display text-xl text-brand-ink">{{ __('Review before you pay') }}</h2>
            <p class="mt-2 text-sm text-brand-ink/70">{{ __('Everything you have told us so far. Tap any block to change it before adding your card.') }}</p>
            <div class="mt-4 max-w-md">
                <x-onboarding.summary :draft="$draft" :user="auth()->user()" />
            </div>
        </section>

        <div class="mt-6">
            <a href="{{ route('app.onboarding.step.subscription_schedule') }}" class="text-sm text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                ← {{ __('Back to :step', ['step' => __('Schedule')]) }}
            </a>
        </div>
    </div>
@endsection
