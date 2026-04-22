@extends('layouts.onboarding', ['step' => 'trial_payment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('Secure your trial box') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">
        {{ __('One-time charge of EUR :amount. No subscription, no stored card.', ['amount' => number_format((float) $order->amount, 2)]) }}
    </p>

    <div class="mt-4 border border-brand-ink/15 bg-white/60 p-4 text-sm space-y-1">
        <div class="flex justify-between"><span>{{ __('Order #') }}</span><span class="font-mono">{{ $order->id }}</span></div>
        <div class="flex justify-between"><span>{{ __('Box size') }}</span><span>{{ __(ucfirst($order->box_size)) }}</span></div>
        <div class="flex justify-between"><span>{{ __('Amount') }}</span><span>EUR {{ number_format((float) $order->amount, 2) }}</span></div>
    </div>

    @if ($checkoutId)
        <div id="sumup-card" class="mt-6 border border-brand-ink/15 p-4 bg-white min-h-[320px]"></div>
        <p id="sumup-card-error"
           class="mt-3 hidden border border-red-200 bg-red-50 p-3 text-sm text-red-700"
           role="alert"
        >
            {{ __('Unable to load payment widget. Please refresh and try again.') }}
        </p>
        <p id="sumup-card-payment-error"
           class="mt-3 hidden border border-red-200 bg-red-50 p-3 text-sm text-red-700"
           role="alert"
        >
            {{ __('Payment did not go through. Please check your card details and try again — no charge has been made.') }}
        </p>
        <script type="text/javascript" src="https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js"></script>
        <script type="text/javascript">
            (function () {
                function showLoadError() {
                    var el = document.getElementById('sumup-card-error');
                    if (el) { el.classList.remove('hidden'); }
                }
                function showPaymentError() {
                    var el = document.getElementById('sumup-card-payment-error');
                    if (el) { el.classList.remove('hidden'); }
                }
                if (typeof SumUpCard === 'undefined' || !SumUpCard || typeof SumUpCard.mount !== 'function') {
                    showLoadError();
                    return;
                }
                try {
                    SumUpCard.mount({
                        id: 'sumup-card',
                        checkoutId: @json($checkoutId),
                        locale: @json(str_replace('_', '-', app()->getLocale())),
                        onResponse: function (type, body) {
                            // Log every widget callback for diagnostics; SumUp docs define:
                            // sent | auth-screen | success | error | fail | invalid
                            try { console.log('[SumUp widget]', type, body); } catch (_) {}
                            if (type === 'success') {
                                // Server will verify with SumUp before marking paid.
                                window.location.href = @json($returnUrl);
                                return;
                            }
                            if (type === 'error' || type === 'fail') {
                                showPaymentError();
                            }
                        },
                    });
                } catch (e) {
                    showLoadError();
                }
            })();
        </script>
    @else
        <div class="mt-6 border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
            <p class="font-semibold">{{ __('Payment could not be started') }}</p>
            <p class="mt-1">
                {{ __('We could not reach the payment provider just now. Please try again in a moment — no charge has been made.') }}
            </p>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-4 text-sm">
            <a
                href="{{ route('app.onboarding.step.trial_payment') }}"
                class="inline-flex items-center justify-center border border-brand-ink bg-brand-ink px-5 py-2 font-semibold uppercase tracking-eyebrow text-white transition hover:bg-brand-ink/90"
                data-testid="trial-payment-retry"
            >
                {{ __('Try again') }}
            </a>
            <a href="{{ route('app.onboarding.step.commitment') }}" class="underline">
                {{ __('Back') }}
            </a>
        </div>
    @endif
@endsection
