@extends('layouts.onboarding', ['step' => 'subscription_payment', 'intent' => 'subscription', 'draft' => $draft])

@section('content')
    <h1 class="font-display text-3xl font-semibold">{{ __('Add your card') }}</h1>
    <p class="mt-2 text-sm text-brand-ink/70">
        {{ __('SumUp securely stores a token so we can charge each weekly delivery. We never store the full card number.') }}
    </p>

    <div class="mt-4 border border-brand-ink/15 bg-white/60 p-4 text-sm space-y-1">
        <div class="flex justify-between"><span>{{ __('Plan') }}</span><span>{{ ucfirst($subscription->plan->value) }}</span></div>
        <div class="flex justify-between"><span>{{ __('Box size') }}</span><span>{{ ucfirst($subscription->box_size) }}</span></div>
        <div class="flex justify-between"><span>{{ __('First box') }}</span><span>EUR {{ number_format((float) config('box.trial_price_ttc', 35), 2) }}</span></div>
    </div>

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
                        try { console.log('[SumUp widget]', type, body); } catch (_) {}
                        if (type === 'success') {
                            window.location.href = @json(route('app.payments.setup.complete'));
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
@endsection
