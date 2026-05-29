@php
    $showFailureHint = static function (?string $reason): bool {
        if ($reason === null || trim($reason) === '') {
            return false;
        }
        $lower = strtolower($reason);

        return strlen($reason) <= 160
            && ! str_contains($lower, 'exception')
            && ! str_contains($lower, 'sql')
            && ! str_contains($lower, 'stack');
    };

    $stateLabel = [
        \App\Models\Payment::STATUS_PAID      => __('Drawn'),
        \App\Models\Payment::STATUS_PENDING   => __('Scheduled'),
        \App\Models\Payment::STATUS_REFUNDED  => __('Refunded'),
        \App\Models\Payment::STATUS_FAILED    => __('Failed'),
    ];
    $stateClass = [
        \App\Models\Payment::STATUS_PAID      => 'text-brand-ink',
        \App\Models\Payment::STATUS_PENDING   => 'text-chapter-produce',
        \App\Models\Payment::STATUS_REFUNDED  => 'text-chapter-meat',
        \App\Models\Payment::STATUS_FAILED    => 'text-chapter-meat',
    ];

    $allocationSlices = [
        ['name' => __('Farms & bakers'),         'pct' => 68, 'sub' => __('Five to eight producers per box')],
        ['name' => __('Pack & van'),              'pct' => 17, 'sub' => __('Warehouse, Hannes, fuel')],
        ['name' => __('VAT'),                     'pct' => 7,  'sub' => __('Remitted to the Finanzamt')],
        ['name' => __('Payment processing'),      'pct' => 3,  'sub' => __('SumUp, SEPA fees')],
        ['name' => __('customer.payments.allocation_erdgut'), 'pct' => 5, 'sub' => __('customer.story.dashboard.founder_signature')],
    ];

    $nextChargeFormatted = $subscription?->next_charge_date
        ? $subscription->next_charge_date->locale(app()->getLocale())->isoFormat('ddd, D. MMM')
        : '—';

    $chargeAmount = $subscription?->expectedChargeAmount();
@endphp

<x-app-layout :hide-app-footer="true">

    {{-- Flash messages --}}
    @if (session('status'))
        <div class="border-b border-brand-ink/10 bg-brand-cream">
            <div class="mx-auto max-w-[1400px] px-6 py-4 lg:px-12">
                @include('payments.partials.session-status')
            </div>
        </div>
    @endif

    {{-- § 1 — Hero --}}
    <section class="relative border-b border-brand-ink/10 py-16 lg:py-24">

        {{-- Top-right tag --}}
        @if ($hasPaymentMethod)
            <span class="absolute right-6 top-6 hidden font-mono text-xs tracking-widest text-chapter-produce lg:block" aria-hidden="true">
                SEPA · {{ __('Active') }}
            </span>
        @endif

        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.05fr_1fr] lg:gap-16">

                {{-- Left --}}
                <div class="flex flex-col justify-between gap-10">
                    <div>
                        <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.payments.hero_eyebrow') }}</p></x-reveal>
                        <x-reveal variant="fade-up" delay="100"><h1 class="mt-5 font-display text-5xl leading-[1.02] tracking-tight text-balance text-brand-ink md:text-6xl lg:text-[5rem]">
                            {{ __('customer.payments.hero_h1_line1') }}<br>
                            <em class="font-normal italic text-chapter-produce">{{ __('customer.payments.hero_h1_line2') }}</em><br>
                            {{ __('customer.payments.hero_h1_line3') }}
                        </h1></x-reveal>
                        <x-reveal variant="fade-up" delay="200"><p class="mt-6 max-w-md text-sm leading-relaxed text-brand-ink/55">
                            {{ __('customer.story.payments.ledger_intro') }}
                        </p></x-reveal>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                        @if ($canUpdatePayment)
                            <a href="{{ route('app.payments.update') }}"
                               class="rounded-sm bg-chapter-produce px-7 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Update payment method') }}
                            </a>
                        @elseif ($canSetupPayment)
                            <a href="{{ route('app.payments.setup') }}"
                               class="rounded-sm bg-chapter-produce px-7 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Set up payment method') }}
                            </a>
                        @endif
                        <a href="#ledger"
                           class="text-brand-ink/60 underline underline-offset-4 transition hover:text-brand-ink">
                            {{ __('Download all receipts') }}
                        </a>
                        <a href="mailto:{{ $user?->email }}"
                           class="text-brand-ink/50 underline underline-offset-4 transition hover:text-brand-ink/80">
                            {{ __('Billing email') }}
                        </a>
                    </div>
                </div>

                {{-- Right: at a glance --}}
                <x-reveal variant="fade-up" delay="150"><div class="bg-brand-paper p-8 lg:p-10">
                    <p class="eyebrow text-chapter-produce">{{ __('At a glance') }}</p>

                    <dl class="mt-6 divide-y divide-brand-ink/10">
                        <div class="grid py-4 first:pt-0" style="grid-template-columns: 7rem 1fr; gap: 1rem; align-items: baseline;">
                            <dt class="eyebrow text-brand-ink/45">{{ __('Next charge') }}</dt>
                            <dd class="font-display text-xl leading-tight text-brand-ink">
                                {{ $nextChargeFormatted }}
                                <span class="ml-2 text-xs text-brand-ink/45">{{ __('drawn 06:00 CET') }}</span>
                            </dd>
                        </div>
                        <div class="grid py-4" style="grid-template-columns: 7rem 1fr; gap: 1rem; align-items: baseline;">
                            <dt class="eyebrow text-brand-ink/45">{{ __('Amount') }}</dt>
                            <dd class="font-display text-xl leading-tight text-brand-ink">
                                @if ($chargeAmount)
                                    @money($chargeAmount)
                                    <span class="ml-2 text-xs text-brand-ink/45">{{ __('incl. VAT') }}</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="grid py-4" style="grid-template-columns: 7rem 1fr; gap: 1rem; align-items: baseline;">
                            <dt class="eyebrow text-brand-ink/45">{{ __('Method') }}</dt>
                            <dd class="font-display text-xl leading-tight text-brand-ink">
                                @if ($hasPaymentMethod)
                                    SEPA
                                    <span class="ml-2 text-xs text-brand-ink/45">{{ __('Direct Debit') }}</span>
                                @else
                                    <span class="text-brand-ink/35">—</span>
                                @endif
                            </dd>
                        </div>
                        <div class="grid py-4" style="grid-template-columns: 7rem 1fr; gap: 1rem; align-items: baseline;">
                            <dt class="eyebrow text-brand-ink/45">{{ __('Receipts to') }}</dt>
                            <dd class="font-display text-xl leading-tight text-brand-ink" style="overflow-wrap: break-word; word-break: break-all;">
                                {{ $user?->email ?? '—' }}
                            </dd>
                        </div>
                        <div class="grid py-4 last:pb-0" style="grid-template-columns: 7rem 1fr; gap: 1rem; align-items: baseline;">
                            <dt class="eyebrow text-brand-ink/45">{{ __('This year') }}</dt>
                            <dd class="font-display text-xl leading-tight text-brand-ink">
                                @money($ytdPaid)
                                <span class="ml-2 text-xs text-brand-ink/45">{{ $ytdCount }} {{ __('deliveries') }}</span>
                            </dd>
                        </div>
                    </dl>
                </div></x-reveal>

            </div>
        </div>
    </section>

    {{-- § 2 — Manifesto --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper py-20 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="mx-auto max-w-3xl text-center">
                <x-reveal variant="fade-up" delay="100"><blockquote class="font-display text-3xl italic leading-snug text-balance text-brand-ink/80 md:text-4xl">
                    <span class="mr-1 font-display text-6xl leading-none text-chapter-produce" style="vertical-align: -0.15em;" aria-hidden="true">"</span>{{ __('customer.story.payments.why_weekly') }}
                </blockquote></x-reveal>
                <div class="mt-8 flex items-center justify-center gap-4">
                    <span class="h-px w-10 shrink-0 bg-chapter-produce" aria-hidden="true"></span>
                    <p class="font-mono text-xs uppercase tracking-widest text-chapter-produce">
                        {{ __('customer.story.dashboard.founder_signature') }}
                    </p>
                    <span class="h-px w-10 shrink-0 bg-chapter-produce" aria-hidden="true"></span>
                </div>
            </div>
        </div>
    </section>

    {{-- § 3 — Payment method --}}
    <section class="border-b border-brand-ink/10 py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            {{-- Header --}}
            <div class="mb-12 flex flex-wrap items-end justify-between gap-6">
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.payments.method_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                        {{ __('Payment method.') }}
                    </h2></x-reveal>
                </div>
                <span class="inline-flex items-center gap-2 border px-3 py-1 font-mono text-[0.65rem] uppercase tracking-widest"
                      style="border-color: {{ $hasPaymentMethod ? 'rgb(var(--color-chapter-produce, 37 62 36))' : 'rgba(0,0,0,0.15)' }}; color: {{ $hasPaymentMethod ? 'rgb(var(--color-chapter-produce, 37 62 36))' : 'rgba(0,0,0,0.4)' }};">
                    @if ($hasPaymentMethod)
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-chapter-produce" aria-hidden="true"></span>
                        {{ __('Saved · active') }}
                    @else
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-brand-ink/30" aria-hidden="true"></span>
                        {{ __('Not set up') }}
                    @endif
                </span>
            </div>

            {{-- Body --}}
            <div class="grid gap-10 lg:grid-cols-[1.2fr_1fr] lg:gap-16">

                {{-- Left: DL --}}
                <dl class="grid gap-x-12 gap-y-10 sm:grid-cols-2">
                    <div>
                        <dt class="eyebrow text-chapter-produce">{{ __('Method') }}</dt>
                        <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">
                            @if ($hasPaymentMethod) SEPA Direct Debit @else — @endif
                        </dd>
                        <div class="mt-4 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/55">
                            @if ($hasPaymentMethod) {{ __('via SumUp mandate') }} @else {{ __('No mandate on file') }} @endif
                        </p>
                    </div>
                    <div>
                        <dt class="eyebrow text-chapter-produce">{{ __('Account') }}</dt>
                        <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">
                            @if ($hasPaymentMethod) •••• @else — @endif
                        </dd>
                        <div class="mt-4 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/55">{{ __('Secured via SumUp') }}</p>
                    </div>
                    <div>
                        <dt class="eyebrow text-chapter-produce">{{ __('Holder') }}</dt>
                        <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">
                            {{ $user?->name ?? '—' }}
                        </dd>
                        <div class="mt-4 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/55">{{ __('matches subscription') }}</p>
                    </div>
                    <div>
                        <dt class="eyebrow text-chapter-produce">{{ __('Next charge') }}</dt>
                        <dd class="mt-3 font-display text-3xl leading-tight text-brand-ink">
                            {{ $nextChargeFormatted }}
                        </dd>
                        <div class="mt-4 h-px bg-brand-ink/10"></div>
                        <p class="mt-3 text-sm text-brand-ink/55">
                            @if ($chargeAmount) @money($chargeAmount) @else — @endif
                        </p>
                    </div>
                </dl>

                {{-- Right: security aside --}}
                <div class="flex flex-col justify-between bg-brand-paper p-8 lg:p-10">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Card on the table') }}</p>
                        <p class="mt-6 font-display text-2xl leading-snug text-brand-ink">
                            {{ __('customer.payments.method_security_copy') }}
                        </p>
                    </div>
                    <div class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm">
                        @if ($canUpdatePayment)
                            <a href="{{ route('app.payments.update') }}"
                               class="rounded-sm bg-chapter-produce px-6 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Update payment method') }}
                            </a>
                        @elseif ($canSetupPayment)
                            <a href="{{ route('app.payments.setup') }}"
                               class="rounded-sm bg-chapter-produce px-6 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Set up payment method') }}
                            </a>
                        @endif
                        <a href="{{ route('app.subscriptions.show') }}"
                           class="self-center text-brand-ink/55 underline underline-offset-4 transition hover:text-brand-ink">
                            {{ __('How charges work') }} →
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- § 4 — Charge history (ledger) --}}
    <section id="ledger" class="border-b border-brand-ink/10 bg-brand-paper py-16 lg:py-24" data-testid="customer-editorial-footer-band-sibling">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            {{-- Header --}}
            <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.payments.ledger_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                        {{ __('Charge history.') }}
                    </h2></x-reveal>
                </div>
            </div>

            @if ($payments->isEmpty())
                <x-empty-state
                    variant="card"
                    heading="h3"
                    :title="__('No payment history yet')"
                    :description="__('Your subscription charges will appear here once billing starts.')"
                >
                    <x-slot name="icon">
                        <svg class="h-12 w-12 text-brand-ink/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </x-slot>
                </x-empty-state>
            @else

                {{-- Ledger rows --}}
                <ul class="divide-y divide-brand-ink/10 border-y border-brand-ink/10">
                    @foreach ($payments as $payment)
                        @php
                            $wk  = $payment->fulfillment_week_start ? 'W'.$payment->fulfillment_week_start->weekOfYear : '—';
                            $date = $payment->created_at->locale(app()->getLocale())->isoFormat('ddd, D. MMM');
                            $note = $payment->status === \App\Models\Payment::STATUS_FAILED && $showFailureHint($payment->failure_reason)
                                ? $payment->failure_reason
                                : ($payment->status === \App\Models\Payment::STATUS_REFUNDED ? __('Credit · ref. :id', ['id' => $payment->id]) : __('Receipt · ref. :id', ['id' => $payment->id]));
                            $stateKey = $payment->status;
                        @endphp
                        <li class="grid items-baseline gap-x-4 gap-y-2 py-7" style="grid-template-columns: 3rem 1fr 6rem 1fr 8rem;">
                            <span class="font-mono text-xs uppercase tracking-widest text-chapter-produce">{{ $wk }}</span>
                            <span class="font-display text-2xl text-brand-ink">{{ $date }}</span>
                            <span class="font-display text-xl tabular-nums text-brand-ink">
                                @if ($payment->status === \App\Models\Payment::STATUS_REFUNDED)
                                    @if ($payment->refund_amount) @money($payment->refund_amount) @else — @endif
                                @elseif ($payment->amount)
                                    @money($payment->amount)
                                @else
                                    —
                                @endif
                            </span>
                            <span class="text-sm text-brand-ink/50">{{ $note }}</span>
                            <span class="font-mono text-xs uppercase tracking-widest {{ $stateClass[$stateKey] ?? 'text-brand-ink/50' }}"
                                  @class(['line-through' => $payment->status === \App\Models\Payment::STATUS_REFUNDED])>
                                {{ $stateLabel[$stateKey] ?? $payment->status }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                {{-- Totals strip --}}
                <div class="mt-10 grid grid-cols-2 gap-6 sm:grid-cols-4">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Drawn (YTD)') }}</p>
                        <p class="mt-2 font-display text-2xl tabular-nums text-brand-ink">@money($ytdPaid)</p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                    </div>
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Refunded') }}</p>
                        <p class="mt-2 font-display text-2xl tabular-nums text-brand-ink">@money($ytdRefunded)</p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                    </div>
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Deliveries (YTD)') }}</p>
                        <p class="mt-2 font-display text-2xl tabular-nums text-brand-ink">{{ $ytdCount }}</p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                    </div>
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Average / week') }}</p>
                        <p class="mt-2 font-display text-2xl tabular-nums text-brand-ink">
                            @if ($ytdAvg) @money($ytdAvg) @else — @endif
                        </p>
                        <div class="mt-3 h-px bg-brand-ink/10"></div>
                    </div>
                </div>

            @endif

        </div>
    </section>

    {{-- § 5 — Allocation --}}
    <section class="border-b border-brand-ink/10 py-20 lg:py-28">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1fr_1.4fr] lg:gap-16">

                {{-- Left --}}
                <div class="flex flex-col">
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.payments.allocation_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl leading-tight text-balance text-brand-ink md:text-5xl">
                        @if ($chargeAmount)
                            {{ __('Out of every') }} @money($chargeAmount).
                        @else
                            {{ __('customer.payments.allocation_h2') }}
                        @endif
                    </h2></x-reveal>
                    <x-reveal variant="fade-up" delay="200"><p class="mt-6 max-w-md text-sm leading-relaxed text-brand-ink/55">
                        {{ __('customer.payments.allocation_body') }}
                    </p></x-reveal>
                    <a href="{{ route('app.subscriptions.show') }}"
                       class="mt-8 inline-flex items-center gap-2 self-start border-b border-chapter-produce pb-1 text-sm text-chapter-produce transition hover:text-chapter-produce/70">
                        {{ __('Read the long version') }} →
                    </a>
                </div>

                {{-- Right: bars --}}
                <ul class="divide-y divide-brand-ink/10 border-y border-brand-ink/10">
                    @foreach ($allocationSlices as $slice)
                        <li class="grid items-center gap-x-4 gap-y-2 py-5" style="grid-template-columns: 10rem 1fr 3.5rem;">
                            <span class="font-display text-xl text-brand-ink">{{ $slice['name'] }}</span>
                            <div>
                                <div class="h-2 w-full bg-brand-cream">
                                    <div class="h-full bg-chapter-produce" style="width: {{ $slice['pct'] }}%"></div>
                                </div>
                                <p class="mt-2 text-xs text-brand-ink/45">{{ $slice['sub'] }}</p>
                            </div>
                            <span class="justify-self-end font-mono text-sm tabular-nums text-chapter-produce">
                                {{ str_pad((string) $slice['pct'], 2, '0', STR_PAD_LEFT) }} %
                            </span>
                        </li>
                    @endforeach
                </ul>

            </div>
        </div>
    </section>

    {{-- § 6 — Receipts --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper py-20 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1fr_1.4fr] lg:gap-16">

                {{-- Left --}}
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.payments.receipts_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                        {{ __('Receipts & invoices.') }}
                    </h2></x-reveal>
                    <x-reveal variant="fade-up" delay="200"><p class="mt-6 text-sm leading-relaxed text-brand-ink/55">
                        {{ __('customer.payments.receipts_body') }}
                    </p></x-reveal>
                </div>

                {{-- Right --}}
                <div>
                    {{-- Billing email (display only) --}}
                    <label class="block">
                        <span class="eyebrow text-chapter-produce">{{ __('Billing email') }}</span>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                            <input type="email"
                                   value="{{ $user?->email }}"
                                   readonly
                                   class="w-full border border-brand-ink/20 bg-brand-cream px-4 py-3 font-display text-xl text-brand-ink/70 outline-none">
                            <button type="button"
                                    class="shrink-0 rounded-sm bg-chapter-produce px-6 py-3 text-sm font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Save email') }}
                            </button>
                        </div>
                    </label>

                    {{-- Spec grid --}}
                    <dl class="mt-10 grid grid-cols-1 gap-x-12 gap-y-8 sm:grid-cols-2">
                        <div>
                            <dt class="eyebrow text-chapter-produce">{{ __('Format') }}</dt>
                            <dd class="mt-3 font-display text-2xl text-brand-ink">PDF + ZUGFeRD</dd>
                            <div class="mt-3 h-px bg-brand-ink/10"></div>
                        </div>
                        <div>
                            <dt class="eyebrow text-chapter-produce">{{ __('Language') }}</dt>
                            <dd class="mt-3 font-display text-2xl text-brand-ink">{{ __('Deutsch / English') }}</dd>
                            <div class="mt-3 h-px bg-brand-ink/10"></div>
                        </div>
                        <div>
                            <dt class="eyebrow text-chapter-produce">{{ __('VAT ID on receipt') }}</dt>
                            <dd class="mt-3 font-display text-2xl text-brand-ink">{{ __('Optional · add one') }}</dd>
                            <div class="mt-3 h-px bg-brand-ink/10"></div>
                        </div>
                        <div>
                            <dt class="eyebrow text-chapter-produce">{{ __('Archive') }}</dt>
                            <dd class="mt-3 font-display text-2xl text-brand-ink">{{ __('Seven years on file') }}</dd>
                            <div class="mt-3 h-px bg-brand-ink/10"></div>
                        </div>
                    </dl>

                    {{-- Footer actions --}}
                    <div class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm">
                        <a href="#ledger"
                           class="rounded-sm border border-chapter-produce px-6 py-3 font-sans text-chapter-produce transition hover:bg-chapter-produce hover:text-brand-cream">
                            {{ __('Download all receipts (ZIP)') }}
                        </a>
                        <a href="#ledger"
                           class="self-center text-brand-ink/55 underline underline-offset-4 transition hover:text-brand-ink">
                            {{ __('View latest invoice') }} →
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <x-customer.editorial-footer-band />

</x-app-layout>
