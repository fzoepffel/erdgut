@extends('layouts.onboarding', ['step' => 'trial_payment', 'intent' => $draft->intent, 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-produce">{{ __('Your payment') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Secure your trial box') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('One-time charge of EUR :amount. No subscription, no stored card.', ['amount' => number_format((float) $order->amount, 2)]) }}
    </p>

    <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
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
            <dd class="font-display text-lg text-brand-ink">EUR {{ number_format((float) $order->amount, 2) }}</dd>
        </div>
    </dl>

    @if ($checkoutId)
        <div id="sumup-card" class="mt-6 min-h-[320px] border border-brand-ink/15 bg-white p-4"></div>
        <p id="sumup-card-error"
           class="mt-3 hidden border border-chapter-meat/40 bg-chapter-meat/5 p-3 text-sm text-brand-ink"
           role="alert"
        >
            {{ __('Unable to load payment widget. Please refresh and try again.') }}
        </p>
        <p id="sumup-card-payment-error"
           class="mt-3 hidden border border-chapter-meat/40 bg-chapter-meat/5 p-3 text-sm text-brand-ink"
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
