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
        stallTimeoutId: null,
        mountedAt: 0,
        diagnosticsUrl: {{ json_encode(route('public.payment-diagnostics.store')) }},
        diagnosticsCheckoutId: {{ json_encode($checkoutId) }},
        diagnosticsIntent: {{ json_encode($intent) }},
        diagnosticsSnapshot: null,
        // Lightweight UA → 'mobile/desktop + os' summary for quick triage.
        // Full UA goes into meta. Keep this branchless and safe; if any
        // browser API is missing we just record null fields.
        captureSnapshot() {
            const ua = (navigator && navigator.userAgent) || '';
            const isMobile = /Mobile|Android|iPhone|iPad/.test(ua);
            const os = /Android/.test(ua) ? 'Android'
                : /iPhone|iPad|iOS/.test(ua) ? 'iOS'
                : /Windows/.test(ua) ? 'Windows'
                : /Mac OS X|Macintosh/.test(ua) ? 'macOS'
                : /Linux/.test(ua) ? 'Linux'
                : 'unknown';
            const summary = (isMobile ? 'mobile' : 'desktop') + ' / ' + os;
            let storage = { local: false, session: false };
            try { localStorage.setItem('__pd', '1'); localStorage.removeItem('__pd'); storage.local = true; } catch (_) {}
            try { sessionStorage.setItem('__pd', '1'); sessionStorage.removeItem('__pd'); storage.session = true; } catch (_) {}
            const conn = navigator && navigator.connection ? navigator.connection : null;
            return {
                summary: summary.slice(0, 160),
                meta: {
                    ua: ua.slice(0, 320),
                    viewport: window.innerWidth + 'x' + window.innerHeight,
                    devicePixelRatio: window.devicePixelRatio || null,
                    locale: (navigator && navigator.language) || null,
                    cookieEnabled: !!(navigator && navigator.cookieEnabled),
                    localStorage: storage.local,
                    sessionStorage: storage.session,
                    networkType: conn ? (conn.effectiveType || null) : null,
                    saveData: conn ? !!conn.saveData : null,
                    referrer: document.referrer ? document.referrer.slice(0, 200) : null,
                    visibility: document.visibilityState || null,
                },
            };
        },
        beacon(phase, extraMeta) {
            try {
                const snap = this.diagnosticsSnapshot || this.captureSnapshot();
                const meta = Object.assign({}, snap.meta, { state: this.state }, extraMeta || {});
                const payload = {
                    phase: phase,
                    checkout_id: this.diagnosticsCheckoutId,
                    intent: this.diagnosticsIntent,
                    ms_since_mount: this.mountedAt ? (Date.now() - this.mountedAt) : null,
                    ua_summary: snap.summary,
                    meta: meta,
                };
                const body = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                if (navigator && navigator.sendBeacon) {
                    navigator.sendBeacon(this.diagnosticsUrl, body);
                } else {
                    fetch(this.diagnosticsUrl, { method: 'POST', body: body, keepalive: true });
                }
            } catch (_) { /* telemetry must never break checkout */ }
        },
        armStallDetector() {
            if (this.stallTimeoutId) clearTimeout(this.stallTimeoutId);
            // Sentinel value while armed; nulled when fired so the UI can
            // distinguish 'still waiting (<25s)' from 'past the threshold'.
            this.stallTimeoutId = setTimeout(() => {
                if (this.state === 'processing') this.beacon('stalled', { reason: 'no_response_25s' });
                this.stallTimeoutId = null;
            }, 25000);
        },
        init() {
            const widgetErrorMap = {{ json_encode($widgetErrorMap) }};
            const genericFail = {{ json_encode(__('Payment setup failed. Please try again.')) }};
            const mandateInvalid = {{ json_encode(__('The card authorization could not be completed. Refresh the page and try again, or use another card.')) }};
            const loadFailed = {{ json_encode(__('Unable to load payment widget. Please refresh and try again.')) }};
            const self = this;

            this.mountedAt = Date.now();
            this.diagnosticsSnapshot = this.captureSnapshot();
            this.beacon('mounted');

            this.timeoutId = setTimeout(function() {
                if (self.state === 'loading') {
                    self.state = 'timeout';
                    self.widgetError = loadFailed;
                    self.beacon('load_timeout');
                }
            }, {{ (int) $timeoutSeconds }} * 1000);

            if (!window.SumUpCard) {
                clearTimeout(this.timeoutId);
                self.state = 'error';
                self.widgetError = loadFailed;
                self.beacon('sdk_failed', { reason: 'SumUpCard_undefined' });
                return;
            }
            self.beacon('sdk_loaded');

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
                        self.beacon('widget_ready');
                        @if ($autoEnableSaveCard)
                        const poll = setInterval(function() {
                            const cb = document.querySelector('input[type=checkbox]');
                            if (cb && !cb.checked) { cb.click(); clearInterval(poll); }
                            else if (cb && cb.checked) { clearInterval(poll); }
                        }, 100);
                        setTimeout(function() { clearInterval(poll); }, 5000);
                        @endif
                        // Intercept the widget's submit button so we can flip to the
                        // 'processing' overlay before the 3-D Secure round-trip.
                        const host = document.getElementById('sumup-card');
                        if (host) {
                            host.addEventListener('click', function(ev) {
                                const btn = ev.target.closest('button[type=submit], button[data-name=\'pay_button\']');
                                if (btn) {
                                    self.state = 'processing';
                                    self.beacon('submit_clicked');
                                    self.armStallDetector();
                                }
                            }, true);
                        }
                    },
                    onResponse: function(type, body) {
                        try { console.log('[SumUp widget]', type, body); } catch (_) {}
                        if (self.stallTimeoutId) { clearTimeout(self.stallTimeoutId); self.stallTimeoutId = null; }
                        if (type === 'success') {
                            self.state = 'processing';
                            self.beacon('response_success', { body_id: body?.id ?? null, body_status: body?.status ?? null });
                            @if ($appendCheckoutParam)
                            const checkoutId = body?.id ?? {{ json_encode($checkoutId) }};
                            const target = new URL({{ json_encode($returnUrl) }}, window.location.origin);
                            target.searchParams.set('checkout', checkoutId);
                            @if ($appendIntentParam)
                            target.searchParams.set('intent', {{ json_encode($appendIntentParam) }});
                            @endif
                            self.beacon('redirect_starting', { to_host: target.host });
                            window.location.href = target.toString();
                            @else
                            self.beacon('redirect_starting', { to_host: (new URL({{ json_encode($returnUrl) }}, window.location.origin)).host });
                            window.location.href = {{ json_encode($returnUrl) }};
                            @endif
                            return;
                        }
                        if (type === 'error' || type === 'fail') {
                            self.state = 'ready';
                            const code = body?.error_code || body?.errorCode || body?.code || null;
                            self.beacon(type === 'fail' ? 'response_fail' : 'response_error', {
                                error_code: code,
                                message: typeof body?.message === 'string' ? body.message.slice(0, 200) : null,
                            });
                            const sumupMsg = body?.message || body?.detail || '';
                            if (typeof sumupMsg === 'string' && sumupMsg.includes('form.mandate.errors')) {
                                self.widgetError = mandateInvalid;
                                return;
                            }
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
                self.state = 'error';
                self.widgetError = e.message || loadFailed;
                self.beacon('mount_threw', { message: typeof e?.message === 'string' ? e.message.slice(0, 200) : null });
            }
        },
        reportProblem() {
            this.beacon('user_reported', { state_when_reported: this.state, error_when_reported: this.widgetError || null });
            // Visible feedback so the user knows their report was sent.
            this.widgetError = {{ json_encode(__('Thanks — we logged this so we can investigate. Please try a different card or device.')) }};
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
            <div x-show="state === 'loading'" class="space-y-4" aria-live="polite">
                <p class="text-sm text-brand-ink/70">{{ __('Preparing the secure card form…') }}</p>
                <div class="space-y-2">
                    <div class="h-3 w-32 animate-pulse bg-brand-ink/10"></div>
                    <div class="h-10 w-full animate-pulse bg-brand-ink/10"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <div class="h-3 w-20 animate-pulse bg-brand-ink/10"></div>
                        <div class="h-10 animate-pulse bg-brand-ink/10"></div>
                    </div>
                    <div class="space-y-2">
                        <div class="h-3 w-16 animate-pulse bg-brand-ink/10"></div>
                        <div class="h-10 animate-pulse bg-brand-ink/10"></div>
                    </div>
                </div>
                <div class="h-11 w-full animate-pulse bg-brand-ink/15"></div>
                <p class="text-xs text-brand-ink/45">{{ __('This usually takes under 5 seconds.') }}</p>
            </div>
        </div>

        <div
            x-show="state === 'processing'"
            x-cloak
            class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-white/95 p-6 text-center"
            role="status"
            aria-live="assertive"
            data-testid="sumup-processing-overlay"
        >
            <div class="flex h-10 w-10 items-center justify-center">
                <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-brand-ink/20 border-t-chapter-produce" aria-hidden="true"></span>
            </div>
            <p class="font-display text-lg font-semibold text-brand-ink">{{ __('Processing your payment') }}</p>
            <p class="max-w-md text-sm leading-relaxed text-brand-ink/65">
                {{ __('Please do not close this window. Your bank may ask for a quick confirmation (SMS, app, or popup).') }}
            </p>
            {{-- "Stuck?" affordance: appears after 25s in processing — that's
                 also when the stall beacon fires server-side. Tapping flags
                 the report so we can correlate the user complaint with the
                 stall event in payment_diagnostics. --}}
            <button
                type="button"
                x-show="stallTimeoutId === null"
                x-cloak
                x-on:click="reportProblem()"
                class="text-xs text-brand-ink/55 underline-offset-4 hover:text-brand-ink hover:underline"
                data-testid="sumup-stuck-report"
            >
                {{ __('Stuck for too long? Tap to report.') }}
            </button>
        </div>

        <div
            x-show="state === 'timeout' || state === 'error'"
            x-cloak
            class="mt-3 border border-chapter-meat/40 bg-chapter-meat/5 p-3 text-sm text-brand-ink"
            role="alert"
            aria-live="polite"
        >
            <p x-text="widgetError"></p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    x-on:click="retry()"
                    class="btn-solid text-xs"
                >
                    {{ __('Try again') }}
                </button>
                <button
                    type="button"
                    x-on:click="reportProblem()"
                    class="text-xs text-brand-ink/55 underline-offset-4 hover:text-brand-ink hover:underline"
                    data-testid="sumup-error-report"
                >
                    {{ __('Stuck for too long? Tap to report.') }}
                </button>
            </div>
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
