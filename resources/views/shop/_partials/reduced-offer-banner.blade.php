{{-- Reduced-offer banner: shown at the top of the shop while only a small
     selection is in stock. Explains the situation, counts down to when the
     full offer returns, and lets visitors join the notify-me waitlist.

     Params:
       $reopenAtIso  — target datetime, "Y-m-d\TH:i" (local wall-clock)
       $reopenLabel  — human reopen label, e.g. "Freitag, 24. Juli 16:00" --}}
<div
    x-data="reducedOfferBanner('{{ $reopenAtIso }}')"
    class="border-b border-shop-border bg-brand-moss/10"
    data-testid="reduced-offer-banner"
>
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs uppercase tracking-eyebrow text-[#ce7720]">{{ __('shop.reduced_offer.eyebrow') }}</p>
                <p class="mt-2 text-base leading-relaxed text-brand-ink">
                    {{ __('shop.reduced_offer.body', ['date' => $reopenLabel]) }}
                </p>

                {{-- Countdown --}}
                <div class="mt-4 flex items-center gap-3 font-mono text-[#ce7720]" role="timer" aria-live="off">
                    <template x-for="unit in units" :key="unit.key">
                        <div class="flex flex-col items-center">
                            <span class="text-2xl tabular-nums" x-text="unit.value"></span>
                            <span class="text-[0.6rem] uppercase tracking-widest text-[#ce7720]/70" x-text="unit.label"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Notify-me form --}}
            <div class="w-full max-w-sm shrink-0">
                @if (session('offer_notify_status'))
                    <p class="rounded border border-brand-moss/40 bg-brand-moss/10 px-4 py-3 text-sm text-brand-ink" data-testid="offer-notify-success">
                        {{ session('offer_notify_status') }}
                    </p>
                @else
                    <p class="text-sm text-brand-ink/70">{{ __('shop.reduced_offer.signup_prompt') }}</p>
                    <form method="POST" action="{{ route('public.shop.notify.store') }}" class="mt-2 flex gap-2">
                        @csrf
                        <label for="offer-notify-email" class="sr-only">{{ __('shop.reduced_offer.email_label') }}</label>
                        <input
                            id="offer-notify-email"
                            type="email"
                            name="email"
                            required
                            autocomplete="email"
                            placeholder="{{ __('shop.reduced_offer.email_placeholder') }}"
                            class="min-w-0 flex-1 border border-shop-border bg-brand-cream px-3 py-2 text-sm text-brand-ink placeholder:text-brand-ink/40 focus:border-brand-ink focus:outline-none"
                        >
                        <button type="submit" class="shrink-0 bg-[#ce7720] px-4 py-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-cream transition hover:opacity-80">
                            {{ __('shop.reduced_offer.signup_button') }}
                        </button>
                    </form>
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Inline (not @stack): the shop layout has no scripts stack. A plain inline
     script runs during parse, before the deferred Alpine module, so the
     function is global by the time Alpine evaluates x-data. --}}
@once
    <script>
        function reducedOfferBanner(targetIso) {
                return {
                    units: [],
                    _timer: null,
                    init() {
                        this.tick();
                        // Recompute once a second; template x-for reflects it live.
                        this._timer = setInterval(() => this.tick(), 1000);
                    },
                    destroy() {
                        if (this._timer) clearInterval(this._timer);
                    },
                    tick() {
                        const target = new Date(targetIso).getTime();
                        let diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
                        const days = Math.floor(diff / 86400); diff -= days * 86400;
                        const hours = Math.floor(diff / 3600); diff -= hours * 3600;
                        const minutes = Math.floor(diff / 60);
                        const seconds = diff - minutes * 60;
                        const pad = (n) => String(n).padStart(2, '0');
                        this.units = [
                            { key: 'd', value: String(days), label: @js(__('shop.reduced_offer.unit_days')) },
                            { key: 'h', value: pad(hours), label: @js(__('shop.reduced_offer.unit_hours')) },
                            { key: 'm', value: pad(minutes), label: @js(__('shop.reduced_offer.unit_minutes')) },
                            { key: 's', value: pad(seconds), label: @js(__('shop.reduced_offer.unit_seconds')) },
                        ];
                    },
                };
            }
        </script>
@endonce
