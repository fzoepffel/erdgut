@extends('layouts.onboarding', ['step' => 'subscription_payment', 'intent' => 'subscription', 'draft' => $draft])

@section('content')
    <p class="eyebrow text-chapter-meat">{{ __('Your card') }}</p>
    <h1 class="mt-2 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">{{ __('Add your card') }}</h1>
    <p class="mt-3 font-editorial text-base italic leading-relaxed text-brand-ink/70">
        {{ __('SumUp securely stores a token so we can charge each weekly delivery. We never store the full card number.') }}
    </p>

    <dl class="mt-6 border border-brand-ink/15 bg-brand-ink/[0.02] p-5 text-sm text-brand-ink/80">
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Plan') }}</dt>
            <dd class="text-brand-ink">{{ ucfirst($subscription->plan->value) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 border-b border-brand-ink/10 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('Box size') }}</dt>
            <dd class="text-brand-ink">{{ __(ucfirst($subscription->box_size)) }}</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-2">
            <dt class="eyebrow text-brand-ink/60">{{ __('First box') }}</dt>
            <dd class="font-display text-lg text-brand-ink">EUR {{ number_format((float) config('box.trial_price_ttc', 35), 2) }}</dd>
        </div>
    </dl>

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
