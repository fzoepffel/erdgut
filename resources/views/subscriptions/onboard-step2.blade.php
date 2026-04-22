@php
    $sumupLocale = match (config('app.locale')) {
        'de' => 'de-DE',
        default => 'en-GB',
    };
    $widgetErrorMap = [
        'DECLINED' => __('Payment was declined. Please check your card details and try again.'),
        'INVALID_CARD' => __('Payment was declined. Please check your card details and try again.'),
        'FRAUD' => __('Payment was declined. Please check your card details and try again.'),
    ];
@endphp
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
                    <script
                        type="text/javascript"
                        src="https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js"
                    ></script>

                    <div
                        x-data="{ widgetError: '' }"
                        x-init="
                            const widgetErrorMap = {{ json_encode($widgetErrorMap) }};
                            const genericFail = {{ json_encode(__('Payment setup failed. Please try again.')) }};
                            const genericDecline = {{ json_encode(__('Payment was declined. Please check your card details and try again.')) }};
                            const mandateInvalid = {{ json_encode(__('The card authorization could not be completed. Refresh the page and try again, or use another card.')) }};
                            if (!window.SumUpCard) {
                                widgetError = {{ json_encode(__('Unable to load payment widget. Please refresh and try again.')) }};
                            } else {
                                try {
                                    SumUpCard.mount({
                                        id: 'sumup-card',
                                        checkoutId: {{ json_encode($checkoutId) }},
                                        email: {{ json_encode($subscription->user->email) }},
                                        locale: {{ json_encode($sumupLocale) }},
                                        showSaveCardOption: false,
                                        onLoad: function() {
                                            const poll = setInterval(function() {
                                                const cb = document.querySelector('input[type=checkbox]');
                                                if (cb && !cb.checked) {
                                                    cb.click();
                                                    clearInterval(poll);
                                                } else if (cb && cb.checked) {
                                                    clearInterval(poll);
                                                }
                                            }, 100);
                                            setTimeout(function() { clearInterval(poll); }, 5000);
                                        },
                                        onResponse: function(type, body) {
                                            if (type === 'success') {
                                                const checkoutId = body?.id ?? {{ json_encode($checkoutId) }};
                                                const target = new URL('{{ route('app.subscriptions.onboard.complete') }}', window.location.origin);
                                                target.searchParams.set('checkout', checkoutId);
                                                window.location.href = target.toString();
                                                return;
                                            }

                                            if (type === 'error' || type === 'fail') {
                                                const sumupMsg = body?.message || body?.detail || '';
                                                if (typeof sumupMsg === 'string' && sumupMsg.includes('form.mandate.errors')) {
                                                    widgetError = mandateInvalid;
                                                    return;
                                                }
                                                const code = body?.error_code || body?.errorCode || body?.code;
                                                const mapped = code && widgetErrorMap[code] ? widgetErrorMap[code] : null;
                                                if (mapped) {
                                                    widgetError = mapped;
                                                    return;
                                                }
                                                const raw = body?.message;
                                                if (typeof raw === 'string' && raw.length > 0 && raw.length < 120 && /^[\\w\\s.,'\\-]+$/.test(raw)) {
                                                    widgetError = raw;
                                                } else {
                                                    widgetError = genericFail;
                                                }
                                            }
                                        }
                                    });
                                } catch (e) {
                                    widgetError = e.message || {{ json_encode(__('Payment setup failed. Please try again.')) }};
                                }
                            }
                        "
                    >
                        <div
                            id="sumup-card"
                            class="min-h-[12rem] border border-dashed border-brand-ink/25 p-6"
                            role="region"
                            aria-labelledby="payment-widget-heading"
                        >
                            <p class="text-sm text-brand-ink/60">
                                {{ __('Loading payment widget...') }}
                            </p>
                        </div>

                        <p
                            x-show="widgetError"
                            x-cloak
                            x-text="widgetError"
                            class="mt-3 text-sm text-chapter-meat"
                            role="alert"
                            aria-live="polite"
                        ></p>
                    </div>

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
