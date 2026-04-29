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

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <p class="eyebrow text-chapter-fish">{{ __('Billing') }}</p>
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ __('Payments and billing') }}
            </h1>
            <p class="mt-3 max-w-2xl font-editorial text-base italic leading-relaxed text-brand-ink/75">
                {{ __('The ledger of your weekly rhythm — quietly kept.') }}
            </p>
        </div>
    </x-slot>

    <div class="py-10 sm:py-16">
        <div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
            @include('payments.partials.session-status')

            <div class="border border-chapter-fish/40 bg-chapter-fish/5 px-4 py-5 sm:px-6">
                <p class="eyebrow text-chapter-fish">{{ __('Payment method status') }}</p>
                @if ($hasPaymentMethod)
                    <p class="mt-3 text-base text-brand-ink/80">
                        {{ __('A payment method is saved for your subscription. Recurring charges use this card via our payment partner.') }}
                    </p>
                @else
                    <p class="mt-3 text-base text-brand-ink/80">
                        {{ __('No payment method on file yet. Add a card to activate subscription billing.') }}
                    </p>
                @endif
                <p class="mt-3 text-base text-brand-ink">
                    <span class="font-semibold">{{ __('Next charge date') }}:</span>
                    {{ $billingDateDisplay }}
                </p>
                <p class="mt-3 text-xs text-brand-ink/60">
                    {{ __('Card details are entered on a secure SumUp page. We do not store your full card number on our servers.') }}
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($canSetupPayment)
                        <a href="{{ route('app.payments.setup') }}" class="btn-solid text-xs">
                            {{ __('Set up payment method') }}
                        </a>
                    @endif
                    @if ($canUpdatePayment)
                        <a href="{{ route('app.payments.update') }}" class="btn-ghost text-xs">
                            {{ __('Update payment method') }}
                        </a>
                    @endif
                    <a href="{{ route('app.subscriptions.show') }}" class="btn-ghost text-xs">
                        {{ __('Subscription overview') }}
                    </a>
                    <a href="{{ route('app.dashboard') }}" class="btn-ghost text-xs">
                        {{ __('Dashboard') }}
                    </a>
                </div>
            </div>

            <section class="border border-brand-ink/15" aria-labelledby="{{ $ledgerHeadingId }}">
                <div class="border-b border-brand-ink/10 px-4 py-5 sm:px-6">
                    <p class="eyebrow text-chapter-fish">{{ __('Charges') }}</p>
                    <h2 id="{{ $ledgerHeadingId }}" class="mt-2 font-display text-2xl text-brand-ink">
                        {{ __('Charge history') }}
                    </h2>
                    <p class="mt-2 text-sm text-brand-ink/70">{{ __('Recorded payment attempts for your subscription.') }}</p>
                </div>
                <div class="p-4 sm:p-6">
                    @if ($payments->isEmpty())
                        <x-empty-state
                            variant="card-muted"
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
                        <div class="space-y-3 sm:hidden" role="list">
                            @foreach ($payments as $payment)
                                @php
                                    $plan = $payment->subscription?->plan;
                                    $desc = $plan
                                        ? __('Subscription charge (:plan)', ['plan' => $plan === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery')])
                                        : __('Subscription charge');
                                @endphp
                                <div class="border border-brand-ink/15 p-4" role="listitem">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-display text-base text-brand-ink">{{ $payment->created_at->format('d.m.Y') }}</p>
                                        <x-status-badge :value="$payment->status" />
                                    </div>
                                    <p class="mt-2 text-sm text-brand-ink/75">{{ $desc }}</p>
                                    <dl class="mt-3 space-y-1.5 text-sm">
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-brand-ink/60">{{ __('Amount') }}</dt>
                                            <dd class="tabular-nums font-medium text-brand-ink">@money($payment->amount) {{ $payment->currency }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-brand-ink/60">{{ __('Attempt') }}</dt>
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

                        <div class="hidden sm:block">
                            <table class="min-w-full divide-y divide-brand-ink/10" aria-describedby="{{ $ledgerHeadingId }}">
                                <caption class="sr-only">{{ __('Charge history table') }}</caption>
                                <thead class="bg-brand-ink/[0.03]">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-eyebrow text-brand-ink/60">
                                            {{ __('Date') }}
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-eyebrow text-brand-ink/60">
                                            {{ __('Description') }}
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-eyebrow text-brand-ink/60">
                                            {{ __('Amount') }}
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-eyebrow text-brand-ink/60">
                                            {{ __('Status') }}
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-eyebrow text-brand-ink/60">
                                            {{ __('Attempt') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-brand-ink/10">
                                    @foreach ($payments as $payment)
                                        @php
                                            $plan = $payment->subscription?->plan;
                                            $desc = $plan
                                                ? __('Subscription charge (:plan)', ['plan' => $plan === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery')])
                                                : __('Subscription charge');
                                        @endphp
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-brand-ink">
                                                {{ $payment->created_at->format('d.m.Y') }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-brand-ink/80">
                                                {{ $desc }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-brand-ink">
                                                @money($payment->amount) {{ $payment->currency }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                                <x-status-badge :value="$payment->status" />
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-brand-ink">
                                                {{ $payment->attempt_number }}
                                            </td>
                                        </tr>
                                        @if ($payment->status === \App\Models\Payment::STATUS_FAILED)
                                            <tr class="bg-chapter-meat/5">
                                                <td colspan="5" class="px-4 py-2 text-xs text-brand-ink">
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
                    @endif
                </div>
            </section>

            @unless ($payments->isEmpty())
                <div class="flex flex-col items-center gap-3 pt-4">
                    <x-triple-star class="opacity-70" />
                    <p class="font-editorial text-sm italic text-brand-ink/60">
                        {{ __('A quiet record, kept week by week.') }}
                    </p>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
