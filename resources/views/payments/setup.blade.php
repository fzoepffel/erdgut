@php
    $intent = $intent ?? 'setup';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-fish">{{ __('Billing') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                @if ($intent === 'update')
                    {{ __('Update payment method') }}
                @else
                    {{ __('Set up payment method') }}
                @endif
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
            <div class="border border-chapter-fish/40 bg-chapter-fish/5 px-4 py-3 sm:px-5">
                <p class="eyebrow text-chapter-fish">
                    @if ($intent === 'update')
                        {{ __('Step 1: Confirm update') }}
                    @else
                        {{ __('Step 1: Add a payment method') }}
                    @endif
                </p>
                <p class="mt-2 text-sm text-brand-ink/80">
                    @if ($intent === 'update')
                        {{ __('You will replace the card used for subscription charges. Your new details are processed securely; we only store a payment token from our provider, not your full card number.') }}
                    @else
                        {{ __('Add a debit or credit card to activate billing. Your details are processed securely by SumUp; we only store a payment token, not your full card number.') }}
                    @endif
                </p>
            </div>

            <div class="border border-brand-ink/15">
                <div class="border-b border-brand-ink/10 px-4 py-5 sm:px-6">
                    <p class="eyebrow text-chapter-fish">{{ __('Card') }}</p>
                    <h3 id="payment-widget-heading" class="mt-2 font-display text-2xl text-brand-ink">
                        {{ __('Payment details') }}
                    </h3>
                    <p class="mt-2 text-sm text-brand-ink/70">
                        {{ __('Enter your card in the form below, then submit. You will return here or to your payment overview when setup is complete.') }}
                    </p>
                </div>
                <div class="p-4 sm:p-6">
                    <x-payment.sumup-widget
                        :checkout-id="$checkoutId"
                        :return-url="route('app.payments.setup.complete')"
                        :email="$subscription->user->email"
                        intent="setup_recurring"
                        :append-checkout-param="true"
                        :append-intent-param="$intent === 'update' ? 'update' : null"
                    />

                    <p class="mt-4 text-xs text-brand-ink/60">
                        {{ __('Payments are processed by SumUp, a licensed payment provider. Erdgut never sees your full card number.') }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('app.payments.history') }}" class="btn-ghost text-xs">
                            {{ __('Back to payment overview') }}
                        </a>
                        <a href="{{ route('app.subscriptions.show') }}" class="btn-solid text-xs">
                            {{ __('Subscription overview') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
