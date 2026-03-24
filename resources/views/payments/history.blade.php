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
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payments and billing') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @include('payments.partials.session-status')

            <div class="rounded-lg border border-emerald-100 bg-emerald-50/80 px-4 py-4 sm:px-5">
                <h3 class="text-sm font-semibold text-emerald-900">{{ __('Payment method status') }}</h3>
                @if ($hasPaymentMethod)
                    <p class="mt-2 text-sm text-emerald-800">
                        {{ __('A payment method is saved for your subscription. Recurring charges use this card via our payment partner.') }}
                    </p>
                @else
                    <p class="mt-2 text-sm text-emerald-800">
                        {{ __('No payment method on file yet. Add a card to activate subscription billing.') }}
                    </p>
                @endif
                <p class="mt-3 text-sm text-emerald-900">
                    <span class="font-medium">{{ __('Next charge date') }}:</span>
                    {{ $billingDateDisplay }}
                </p>
                <p class="mt-2 text-xs text-emerald-800/90">
                    {{ __('Card details are entered on a secure SumUp page. We do not store your full card number on our servers.') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($canSetupPayment)
                        <a
                            href="{{ route('app.payments.setup') }}"
                            class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                        >
                            {{ __('Set up payment method') }}
                        </a>
                    @endif
                    @if ($canUpdatePayment)
                        <a
                            href="{{ route('app.payments.update') }}"
                            class="inline-flex items-center justify-center rounded-md border border-emerald-600 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50"
                        >
                            {{ __('Update payment method') }}
                        </a>
                    @endif
                    <a
                        href="{{ route('app.subscriptions.show') }}"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('Subscription overview') }}
                    </a>
                    <a
                        href="{{ route('app.dashboard') }}"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('Dashboard') }}
                    </a>
                </div>
            </div>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" aria-labelledby="{{ $ledgerHeadingId }}">
                <div class="border-b border-gray-100 px-4 py-4 sm:px-6">
                    <h2 id="{{ $ledgerHeadingId }}" class="text-base font-semibold text-gray-900">
                        {{ __('Charge history') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Recorded payment attempts for your subscription.') }}</p>
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
                                <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
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
                                <div class="rounded-lg border border-gray-200 p-4 shadow-sm" role="listitem">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-medium text-gray-900">{{ $payment->created_at->format('d.m.Y') }}</p>
                                        <x-status-badge :value="$payment->status" />
                                    </div>
                                    <p class="mt-2 text-sm text-gray-600">{{ $desc }}</p>
                                    <dl class="mt-3 space-y-1 text-sm">
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-gray-600">{{ __('Amount') }}</dt>
                                            <dd class="font-medium tabular-nums text-gray-900">{{ number_format((float) $payment->amount, 2, ',', '.') }} {{ $payment->currency }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-gray-600">{{ __('Attempt') }}</dt>
                                            <dd class="font-medium text-gray-900">{{ $payment->attempt_number }}</dd>
                                        </div>
                                        @if ($payment->status === \App\Models\Payment::STATUS_FAILED)
                                            <div class="mt-2 rounded-md bg-red-50 px-2 py-1.5 text-xs text-red-800">
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
                            <table class="min-w-full divide-y divide-gray-200" aria-describedby="{{ $ledgerHeadingId }}">
                                <caption class="sr-only">{{ __('Charge history table') }}</caption>
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Date') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Description') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Amount') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Status') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                            {{ __('Attempt') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach ($payments as $payment)
                                        @php
                                            $plan = $payment->subscription?->plan;
                                            $desc = $plan
                                                ? __('Subscription charge (:plan)', ['plan' => $plan === \App\Enums\SubscriptionPlan::Weekly ? __('Weekly delivery') : __('Biweekly delivery')])
                                                : __('Subscription charge');
                                        @endphp
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                {{ $payment->created_at->format('d.m.Y') }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">
                                                {{ $desc }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-gray-900">
                                                {{ number_format((float) $payment->amount, 2, ',', '.') }} {{ $payment->currency }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                                <x-status-badge :value="$payment->status" />
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                {{ $payment->attempt_number }}
                                            </td>
                                        </tr>
                                        @if ($payment->status === \App\Models\Payment::STATUS_FAILED)
                                            <tr class="bg-red-50/50">
                                                <td colspan="5" class="px-4 py-2 text-xs text-red-800">
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
        </div>
    </div>
</x-app-layout>
