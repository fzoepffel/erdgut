@props([
    'checkoutId',
    'returnUrl',
    'email' => null,
    'intent' => 'one_time', // one_time | setup_recurring
    'appendCheckoutParam' => false, // if true, append ?checkout=<id>[&intent=update] to returnUrl
    'appendIntentParam' => null, // extra ?intent= value (e.g. 'update')
    'timeoutSeconds' => 30,
])
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
    $autoEnableSaveCard = $intent === 'setup_recurring';
    $showSaveCardOption = $intent === 'setup_recurring' ? 'false' : 'true';
@endphp

<div
    x-data="{
        state: 'loading',
        widgetError: '',
        timeoutId: null,
        init() {
            const widgetErrorMap = {{ json_encode($widgetErrorMap) }};
            const genericFail = {{ json_encode(__('Payment setup failed. Please try again.')) }};
            const mandateInvalid = {{ json_encode(__('The card authorization could not be completed. Refresh the page and try again, or use another card.')) }};
            const loadFailed = {{ json_encode(__('Unable to load payment widget. Please refresh and try again.')) }};
            const self = this;

            this.timeoutId = setTimeout(function() {
                if (self.state === 'loading') {
                    self.state = 'timeout';
                    self.widgetError = loadFailed;
                }
            }, {{ (int) $timeoutSeconds }} * 1000);

            if (!window.SumUpCard) {
                clearTimeout(this.timeoutId);
                this.state = 'error';
                this.widgetError = loadFailed;
                return;
            }

            try {
                SumUpCard.mount({
                    id: 'sumup-card',
                    checkoutId: {{ json_encode($checkoutId) }},
                    @if ($email) email: {{ json_encode($email) }}, @endif
                    locale: {{ json_encode($sumupLocale) }},
                    showSaveCardOption: {!! $showSaveCardOption !!},
                    onLoad: function() {
                        clearTimeout(self.timeoutId);
                        self.state = 'ready';
                        @if ($autoEnableSaveCard)
                        const poll = setInterval(function() {
                            const cb = document.querySelector('input[type=checkbox]');
                            if (cb && !cb.checked) { cb.click(); clearInterval(poll); }
                            else if (cb && cb.checked) { clearInterval(poll); }
                        }, 100);
                        setTimeout(function() { clearInterval(poll); }, 5000);
                        @endif
                    },
                    onResponse: function(type, body) {
                        try { console.log('[SumUp widget]', type, body); } catch (_) {}
                        if (type === 'success') {
                            @if ($appendCheckoutParam)
                            const checkoutId = body?.id ?? {{ json_encode($checkoutId) }};
                            const target = new URL({{ json_encode($returnUrl) }}, window.location.origin);
                            target.searchParams.set('checkout', checkoutId);
                            @if ($appendIntentParam)
                            target.searchParams.set('intent', {{ json_encode($appendIntentParam) }});
                            @endif
                            window.location.href = target.toString();
                            @else
                            window.location.href = {{ json_encode($returnUrl) }};
                            @endif
                            return;
                        }
                        if (type === 'error' || type === 'fail') {
                            const sumupMsg = body?.message || body?.detail || '';
                            if (typeof sumupMsg === 'string' && sumupMsg.includes('form.mandate.errors')) {
                                self.widgetError = mandateInvalid;
                                return;
                            }
                            const code = body?.error_code || body?.errorCode || body?.code;
                            const mapped = code && widgetErrorMap[code] ? widgetErrorMap[code] : null;
                            if (mapped) { self.widgetError = mapped; return; }
                            const raw = body?.message;
                            if (typeof raw === 'string' && raw.length > 0 && raw.length < 120 && /^[\w\s.,'\-]+$/.test(raw)) {
                                self.widgetError = raw;
                            } else {
                                self.widgetError = genericFail;
                            }
                        }
                    }
                });
            } catch (e) {
                clearTimeout(this.timeoutId);
                this.state = 'error';
                this.widgetError = e.message || loadFailed;
            }
        },
        retry() {
            window.location.reload();
        }
    }"
    x-init="init()"
>
    <script type="text/javascript" src="https://gateway.sumup.com/gateway/ecom/card/v2/sdk.js"></script>

    <div class="relative">
        <div
            id="sumup-card"
            class="min-h-[12rem] border border-brand-ink/15 bg-white p-4"
            role="region"
            aria-labelledby="payment-widget-heading"
            aria-busy="true"
            x-bind:aria-busy="state === 'loading' ? 'true' : 'false'"
        >
            <div x-show="state === 'loading'" class="space-y-3" aria-live="polite">
                <p class="text-sm text-brand-ink/60">{{ __('Loading payment widget...') }}</p>
                <div class="h-4 w-3/4 animate-pulse bg-brand-ink/10"></div>
                <div class="h-4 w-1/2 animate-pulse bg-brand-ink/10"></div>
                <div class="h-10 w-full animate-pulse bg-brand-ink/10"></div>
            </div>
        </div>

        <div
            x-show="state === 'timeout' || state === 'error'"
            x-cloak
            class="mt-3 border border-chapter-meat/40 bg-chapter-meat/5 p-3 text-sm text-brand-ink"
            role="alert"
            aria-live="polite"
        >
            <p x-text="widgetError"></p>
            <button
                type="button"
                x-on:click="retry()"
                class="mt-3 btn-solid text-xs"
            >
                {{ __('Try again') }}
            </button>
        </div>

        <p
            x-show="state === 'ready' && widgetError"
            x-cloak
            x-text="widgetError"
            class="mt-3 text-sm text-chapter-meat"
            role="alert"
            aria-live="polite"
        ></p>
    </div>
</div>
