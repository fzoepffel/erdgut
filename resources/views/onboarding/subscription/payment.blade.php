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
    <script type="text/javascript" src="https://gateway.sumup.com/gateway/ecom/card/v2.0/sdk.js"></script>
    <script type="text/javascript">
        (function () {
            function showError() {
                var el = document.getElementById('sumup-card-error');
                if (el) { el.classList.remove('hidden'); }
            }
            if (typeof SumUpCard === 'undefined' || !SumUpCard || typeof SumUpCard.mount !== 'function') {
                showError();
                return;
            }
            try {
                SumUpCard.mount({
                    id: 'sumup-card',
                    checkoutId: @json($checkoutId),
                    locale: @json(str_replace('_', '-', app()->getLocale())),
                    onResponse: function (type) {
                        if (type === 'success' || type === 'sent') {
                            window.location.href = @json(route('app.payments.setup.complete'));
                        }
                    },
                });
            } catch (e) {
                showError();
            }
        })();
    </script>
@endsection
