@php
    $ledgerHeadingId = 'payment-ledger-heading';
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
    <section class="border-b border-brand-ink/10 py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.05fr_1fr] lg:gap-16">

                {{-- Left: heading + lead --}}
                <div class="flex flex-col justify-between">
                    <div>
                        <p class="eyebrow text-chapter-produce">{{ __('Billing') }}</p>
                        <h1 class="mt-4 font-display text-5xl leading-[1.05] tracking-tight text-balance text-brand-ink md:text-6xl lg:text-7xl">
                            {{ __('customer.payments.hero_h1_line1') }}<br>
                            <em class="font-normal italic text-chapter-produce">{{ __('customer.payments.hero_h1_line2') }}</em>
                        </h1>
                        <p class="mt-6 max-w-md text-base leading-relaxed text-brand-ink/60">
                            {{ __('customer.story.payments.ledger_intro') }}
                        </p>
                    </div>
                    <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                        <a href="{{ route('app.subscriptions.show') }}"
                           class="rounded-sm bg-chapter-produce px-7 py-3 font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                            {{ __('Subscription overview') }}
                        </a>
                    </div>
                </div>

                {{-- Right: payment method panel --}}
                <div class="bg-brand-paper p-8 lg:p-10">
                    <div class="flex items-start justify-between">
                        <span class="eyebrow text-chapter-produce">{{ __('Payment method') }}</span>
                        @if ($hasPaymentMethod)
                            <span class="eyebrow text-chapter-produce/70">{{ __('Active') }}</span>
                        @else
                            <span class="eyebrow text-brand-ink/40">{{ __('Not set up') }}</span>
                        @endif
                    </div>

                    <p class="mt-6 font-display text-2xl leading-tight text-brand-ink">
                        @if ($hasPaymentMethod)
                            {{ __('Card on file') }}
                        @else
                            {{ __('No card on file') }}
                        @endif
                    </p>

                    <dl class="mt-8 grid grid-cols-2 gap-x-8 gap-y-6 border-t border-brand-ink/10 pt-6">
                        <div>
                            <dt class="eyebrow text-brand-ink/55">{{ __('Next charge date') }}</dt>
                            <dd class="mt-2 text-sm text-brand-ink/80">{{ $billingDateDisplay }}</dd>
                        </div>
                        <div>
                            <dt class="eyebrow text-brand-ink/55">{{ __('Card details') }}</dt>
                            <dd class="mt-2 text-sm text-brand-ink/80">{{ __('Secured via SumUp') }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex flex-wrap gap-3 border-t border-brand-ink/10 pt-6">
                        @if ($canSetupPayment)
                            <a href="{{ route('app.payments.setup') }}"
                               class="rounded-sm bg-chapter-produce px-5 py-2.5 text-xs font-sans text-brand-cream transition hover:bg-chapter-produce/90">
                                {{ __('Set up payment method') }}
                            </a>
                        @endif
                        @if ($canUpdatePayment)
                            <a href="{{ route('app.payments.update') }}"
                               class="rounded-sm border border-brand-ink/20 px-5 py-2.5 text-xs font-sans text-brand-ink/70 transition hover:border-brand-ink/40 hover:text-brand-ink">
                                {{ __('Update payment method') }}
                            </a>
                        @endif
                    </div>

                    <p class="mt-4 text-xs leading-relaxed text-brand-ink/45">
                        {{ __('Card details are entered on a secure SumUp page. We do not store your full card number on our servers.') }}
                    </p>
                </div>

            </div>
        </div>
    </section>

    {{-- § 2 — Why weekly (manifesto) --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper py-16 lg:py-20">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="mx-auto max-w-2xl text-center">
                <p class="eyebrow text-chapter-produce">{{ __('A word from the founders') }}</p>
                <blockquote class="mt-6 font-display text-2xl italic leading-relaxed text-brand-ink/80 sm:text-3xl">
                    <span aria-hidden="true">"</span>{{ __('customer.story.payments.why_weekly') }}<span aria-hidden="true">"</span>
                </blockquote>
                <div class="mt-8 flex items-center justify-center gap-3">
                    <span class="block h-px w-10 shrink-0 bg-brand-ink/40" aria-hidden="true"></span>
                    <p class="text-xs font-semibold uppercase tracking-eyebrow text-brand-ink/80">
                        {{ __('customer.story.dashboard.founder_signature') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- § 3 — Charge history --}}
    <section class="py-20 lg:py-24" aria-labelledby="{{ $ledgerHeadingId }}">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            <div class="mb-10">
                <p class="eyebrow text-chapter-produce">{{ __('Charges.') }}</p>
                <h2 id="{{ $ledgerHeadingId }}" class="mt-2 font-display text-4xl text-brand-ink md:text-5xl">
                    {{ __('Charge history') }}
                </h2>
                <p class="mt-4 max-w-md text-base leading-relaxed text-brand-ink/60">
                    {{ __('Recorded payment attempts for your subscription.') }}
                </p>
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
                {{-- Mobile cards --}}
                <div class="space-y-3 sm:hidden" role="list">
                    @foreach ($payments as $payment)
                        @php
                            $plan = $payment->subscription?->plan;
                            $desc = $plan
                                ? __('Subscription charge (:plan)', ['plan' => $plan === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery')])
                                : __('Subscription charge');
                        @endphp
                        <div class="border border-brand-ink/15 bg-brand-paper p-4" role="listitem">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-display text-base text-brand-ink">{{ $payment->created_at->format('d.m.Y') }}</p>
                                <x-status-badge :value="$payment->status" />
                            </div>
                            <p class="mt-2 text-sm text-brand-ink/75">{{ $desc }}</p>
                            <dl class="mt-3 space-y-1.5 text-sm">
                                <div class="flex justify-between gap-2">
                                    <dt class="eyebrow text-brand-ink/60">{{ __('Amount') }}</dt>
                                    <dd class="tabular-nums font-medium text-brand-ink">@money($payment->amount) {{ $payment->currency }}</dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="eyebrow text-brand-ink/60">{{ __('Attempt') }}</dt>
                                    <dd class="font-medium text-brand-ink">{{ $payment->attempt_number }}</dd>
                                </div>
                                @if ($payment->status === \App\Models\Payment::STATUS_FAILED)
                                    <div class="mt-2 border border-chapter-meat/30 bg-chapter-meat/5 px-2 py-1.5 text-xs text-brand-ink">
                                        @if ($showFailureHint($payment->failure_reason))
                                            {{ $payment->failure_reason }}
                                        @else
                                            {{ __('Payment failed') }}
                                        @endif
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop table --}}
                <div class="hidden sm:block">
                    <table class="min-w-full divide-y divide-brand-ink/10 border border-brand-ink/15" aria-describedby="{{ $ledgerHeadingId }}">
                        <caption class="sr-only">{{ __('Charge history table') }}</caption>
                        <thead class="bg-brand-paper">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left eyebrow text-brand-ink/60">{{ __('Date') }}</th>
                                <th scope="col" class="px-6 py-4 text-left eyebrow text-brand-ink/60">{{ __('Description') }}</th>
                                <th scope="col" class="px-6 py-4 text-left eyebrow text-brand-ink/60">{{ __('Amount') }}</th>
                                <th scope="col" class="px-6 py-4 text-left eyebrow text-brand-ink/60">{{ __('Status') }}</th>
                                <th scope="col" class="px-6 py-4 text-left eyebrow text-brand-ink/60">{{ __('Attempt') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-ink/10 bg-brand-cream">
                            @foreach ($payments as $payment)
                                @php
                                    $plan = $payment->subscription?->plan;
                                    $desc = $plan
                                        ? __('Subscription charge (:plan)', ['plan' => $plan === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery')])
                                        : __('Subscription charge');
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-brand-ink">
                                        {{ $payment->created_at->format('d.m.Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-brand-ink/80">{{ $desc }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm tabular-nums text-brand-ink">
                                        @money($payment->amount) {{ $payment->currency }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <x-status-badge :value="$payment->status" />
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-brand-ink">
                                        {{ $payment->attempt_number }}
                                    </td>
                                </tr>
                                @if ($payment->status === \App\Models\Payment::STATUS_FAILED)
                                    <tr class="bg-chapter-meat/5">
                                        <td colspan="5" class="px-6 py-3 text-xs text-brand-ink">
                                            @if ($showFailureHint($payment->failure_reason))
                                                {{ $payment->failure_reason }}
                                            @else
                                                {{ __('Payment failed') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-20 flex flex-col items-center gap-3">
                    <x-triple-star class="opacity-70" />
                    <p class="font-editorial text-sm italic text-brand-ink/60">
                        {{ __('A quiet record, kept week by week.') }}
                    </p>
                </div>
            @endif

        </div>
    </section>

    <x-customer.editorial-footer-band />

</x-app-layout>
