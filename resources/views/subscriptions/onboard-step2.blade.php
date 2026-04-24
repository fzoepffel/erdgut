<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-fish">{{ __('Step 2 of 2') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Set up payment method') }}
            </h1>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-3xl space-y-8 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'subscription-created')
                <p class="border border-chapter-produce/40 bg-chapter-produce/5 px-4 py-3 text-sm text-brand-ink">
                    {{ __('Subscription created. Set up your payment method to activate billing.') }}
                </p>
            @endif

            <div class="border border-chapter-fish/40 bg-chapter-fish/5 px-4 py-3 sm:px-5">
                <p class="eyebrow text-chapter-fish">
                    {{ __('Step 2 of 2: Payment') }}
                </p>
                <p class="mt-2 text-sm text-brand-ink/80">
                    {{ __('Add a debit or credit card to activate billing. Your details are processed securely by SumUp; we only store a payment token, not your full card number.') }}
                </p>
            </div>

            <div class="border border-brand-ink/15">
                <div class="border-b border-brand-ink/10 px-4 py-5 sm:px-6">
                    <p class="eyebrow text-chapter-fish">{{ __('Card') }}</p>
                    <h3 id="payment-widget-heading" class="mt-2 font-display text-2xl text-brand-ink">
                        {{ __('Payment details') }}
                    </h3>
                    <p class="mt-2 text-sm text-brand-ink/70">
                        {{ __('Enter your card in the form below, then submit. You will return here when setup is complete.') }}
                    </p>
                </div>
                <div class="p-4 sm:p-6">
                    <x-payment.sumup-widget
                        :checkout-id="$checkoutId"
                        :return-url="route('app.subscriptions.onboard.complete')"
                        :email="$subscription->user->email"
                        intent="setup_recurring"
                        :append-checkout-param="true"
                    />

                    <p class="mt-4 text-xs text-brand-ink/60">
                        {{ __('Payments are processed by SumUp, a licensed payment provider. Erdgut never sees your full card number.') }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('app.dashboard') }}" class="btn-ghost text-xs">
                            {{ __('Back to dashboard') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
