@props([
    'checkoutId',
    'returnUrl',
    'email' => null,
    'intent' => 'one_time', // one_time | setup_recurring
    'appendCheckoutParam' => false, // if true, append ?checkout=<id>[&intent=update] to returnUrl
    'appendIntentParam' => null, // extra ?intent= value (e.g. 'update')
    'timeoutSeconds' => 30,
    // B12 (UX notes): a required consent checkbox above the widget that
    // gates the SumUp pay button until the user accepts AGB +
    // Datenschutz (and, for setup_recurring, the recurring-billing
    // mandate). When false, the widget runs unchanged for flows that
    // don't need re-acceptance (e.g. updating an existing card).
    'requireConsent' => false,
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
        // B12: consent state. When `requireConsent` is true, the widget
        // visually disables the pay button until `consent === true`.
        // Defaults to !requireConsent so legacy callers stay unblocked.
        requireConsent: {{ $requireConsent ? 'true' : 'false' }},
        consent: {{ $requireConsent ? 'false' : 'true' }},
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
        heartbeatId: null,
        processingStartedAt: 0,
        armStallDetector() {
            if (this.stallTimeoutId) clearTimeout(this.stallTimeoutId);
            if (this.heartbeatId) clearInterval(this.heartbeatId);
            this.processingStartedAt = Date.now();
            // Heartbeat every 3s tells us how long the user waited even if
            // they close the tab before the 8s stall fires. Stops itself
            // once we're out of the processing state.
            this.heartbeatId = setInterval(() => {
                if (this.state !== 'processing') {
                    clearInterval(this.heartbeatId);
                    this.heartbeatId = null;
                    return;
                }
                this.beacon('processing_heartbeat', {
                    waited_ms: Date.now() - this.processingStartedAt,
                });
            }, 3000);
            // 8s threshold: matches when impatient mobile users give up
            // (the friend on /e/OS closed the tab before our previous 25s
            // timer ever had a chance to fire). Sentinel value while
            // armed; nulled when fired so the UI can distinguish
            // 'still waiting (<8s)' from 'past the threshold'.
            this.stallTimeoutId = setTimeout(() => {
                if (this.state === 'processing') this.beacon('stalled', { reason: 'no_response_8s' });
                this.stallTimeoutId = null;
            }, 8000);
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
                        // B14 (UX notes): for setup_recurring the save-card
                        // option is mandatory — if SumUp ignores the
                        // `showSaveCardOption: false` flag and renders the
                        // toggle anyway, we tick it AND disable it so the
                        // customer can't untick (and accidentally end up
                        // with no mandate). Skip our own consent checkbox
                        // (#preferences-eggs-addon etc.) which is a real
                        // Erdgut-owned input — only the SumUp-injected
                        // sibling is a save-card toggle candidate.
                        const sumupHost = document.getElementById('sumup-card');
                        const poll = setInterval(function() {
                            const cb = sumupHost && sumupHost.querySelector('input[type=checkbox]');
                            if (cb) {
                                if (!cb.checked) { cb.click(); }
                                cb.disabled = true;
                                cb.setAttribute('aria-readonly', 'true');
                                clearInterval(poll);
                            }
                        }, 100);
                        setTimeout(function() { clearInterval(poll); }, 5000);
                        @endif
                        // Intercept the widget's submit button so we can flip to the
                        // 'processing' overlay before the 3-D Secure round-trip.
                        // B12: also block the click when consent is required but
                        // not yet given. Capture-phase listener so it runs before
                        // SumUp's own handler.
                        const host = document.getElementById('sumup-card');
                        if (host) {
                            host.addEventListener('click', function(ev) {
                                const btn = ev.target.closest('button[type=submit], button[data-name=\'pay_button\']');
                                if (btn) {
                                    if (self.requireConsent && !self.consent) {
                                        ev.stopImmediatePropagation();
                                        ev.preventDefault();
                                        self.widgetError = {{ json_encode(__('Please accept the terms before paying.')) }};
                                        self.beacon('consent_blocked');
                                        return;
                                    }
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
                        if (self.heartbeatId) { clearInterval(self.heartbeatId); self.heartbeatId = null; }
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
                        // 'invalid' is SumUp's response when the submitted card
                        // fails validation (bad/declined card, incomplete field,
                        // unsupported card for this checkout). It MUST be handled
                        // alongside error/fail — otherwise the widget stays stuck
                        // on the 'processing' overlay forever (the stall timer
                        // fires but state is never reset). Root-caused 2026-05-28.
                        if (type === 'error' || type === 'fail' || type === 'invalid') {
                            self.state = 'ready';
                            const code = body?.error_code || body?.errorCode || body?.code || null;
                            self.beacon('response_' + type, {
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
                            return;
                        }

                        // Defensive catch-all: any other (unexpected) response type
                        // must still release the processing overlay so the widget
                        // can never hang indefinitely. Reset to 'ready', surface a
                        // generic error, and record the unhandled type for triage.
                        if (type !== 'success') {
                            self.state = 'ready';
                            self.widgetError = genericFail;
                            self.beacon('response_unhandled', {
                                response_type: typeof type === 'string' ? type.slice(0, 40) : null,
                            });
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

    @if ($requireConsent)
        {{-- B12 (UX notes): explicit consent above the SumUp widget. The
             checkbox is required for German B2C recurring billing — AGB +
             Datenschutz acceptance must be opt-in (no pre-checked) and
             linked to stable URLs. The widget visually grays out and
             refuses to submit until this is checked; see the consent_blocked
             beacon for the click guard. --}}
        <div class="mb-4 border border-brand-ink/15 bg-brand-cream/40 p-4" data-testid="payment-consent-gate">
            <label class="flex items-start gap-3 text-sm text-brand-ink/85 cursor-pointer">
                <input
                    type="checkbox"
                    x-model="consent"
                    class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer border-brand-ink/30 text-chapter-produce focus:ring-chapter-produce"
                    data-testid="payment-consent-checkbox"
                    aria-required="true"
                />
                <span class="leading-relaxed">
                    @if ($intent === 'setup_recurring')
                        {!! __('I accept the <a href=":terms" target="_blank" rel="noopener" class="underline underline-offset-4 hover:text-chapter-produce">Terms</a> and <a href=":privacy" target="_blank" rel="noopener" class="underline underline-offset-4 hover:text-chapter-produce">Privacy notice</a>, and authorise Erdgut to charge my card for each delivery until I cancel.', [
                                'terms' => route('public.terms'),
                                'privacy' => route('public.privacy'),
                            ]) !!}
                    @else
                        {!! __('I accept the <a href=":terms" target="_blank" rel="noopener" class="underline underline-offset-4 hover:text-chapter-produce">Terms</a> and <a href=":privacy" target="_blank" rel="noopener" class="underline underline-offset-4 hover:text-chapter-produce">Privacy notice</a>.', [
                                'terms' => route('public.terms'),
                                'privacy' => route('public.privacy'),
                            ]) !!}
                    @endif
                </span>
            </label>
        </div>
    @endif

    <div class="relative">
        <div
            id="sumup-card"
            x-bind:class="requireConsent && !consent ? 'pointer-events-none opacity-60' : ''"
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
            {{-- "Stuck?" affordance: appears after 8s in processing (the
                 stall threshold), after which the server has also recorded
                 a `stalled` beacon. The hint explains the most likely
                 cause we've actually seen in production (mobile-network
                 path to api.sumup.com dropping the request); the report
                 button correlates the user complaint with the stall
                 event in payment_diagnostics. --}}
            <div
                x-show="stallTimeoutId === null && state === 'processing'"
                x-cloak
                class="space-y-3 border-t border-brand-ink/10 pt-3 text-left"
            >
                <p class="text-xs leading-relaxed text-brand-ink/65">
                    {{ __('payment.processing_stalled_hint') }}
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        x-on:click="retry()"
                        class="text-xs text-brand-ink underline underline-offset-4 hover:text-chapter-produce"
                    >
                        {{ __('payment.processing_stalled_retry') }}
                    </button>
                    <button
                        type="button"
                        x-on:click="reportProblem()"
                        class="text-xs text-brand-ink/55 underline-offset-4 hover:text-brand-ink hover:underline"
                        data-testid="sumup-stuck-report"
                    >
                        {{ __('Stuck for too long? Tap to report.') }}
                    </button>
                </div>
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
