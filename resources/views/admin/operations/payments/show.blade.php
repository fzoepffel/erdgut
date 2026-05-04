<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.payments.detail_title', ['id' => $payment->id]) }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'admin-payment-refunded')
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ __('admin.operations.payments.refund_success') }}
                </div>
            @elseif (session('status') === 'admin-payment-refund-recorded-provider-failed')
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ __('admin.operations.payments.refund_provider_failed') }}
                </div>
            @elseif (session('status') === 'admin-payment-refund-not-allowed')
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ __('admin.operations.payments.refund_not_allowed') }}
                </div>
            @elseif (session('status') === 'admin-payment-marked-paid-externally')
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ __('admin.operations.payments.marked_paid_success') }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <a href="{{ route('app.admin.payments.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    &larr; {{ __('admin.operations.payments.back_to_index') }}
                </a>
            </div>

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="p-6 sm:p-8">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('admin.operations.payments.details_heading') }}</h3>
                    <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-medium text-gray-500">ID</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">#{{ $payment->id }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">{{ __('admin.operations.status') }}</dt>
                            <dd class="mt-1 text-gray-900"><x-status-badge :value="$payment->status" type="payment" /></dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">{{ __('admin.operations.amount') }}</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">{{ $payment->amount }} {{ $payment->currency }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">{{ __('admin.operations.user') }}</dt>
                            <dd class="mt-1 text-gray-900">
                                {{ $payment->subscription?->user?->email ?? $payment->order?->user?->email ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">{{ __('admin.operations.attempt') }}</dt>
                            <dd class="mt-1 tabular-nums text-gray-900">{{ $payment->attempt_number }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">{{ __('admin.operations.created_at') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                        </div>
                        @if ($payment->paid_at)
                            <div>
                                <dt class="font-medium text-gray-500">paid_at</dt>
                                <dd class="mt-1 text-gray-900">{{ $payment->paid_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                            </div>
                        @endif
                        @if ($payment->failed_at)
                            <div>
                                <dt class="font-medium text-gray-500">failed_at</dt>
                                <dd class="mt-1 text-gray-900">{{ $payment->failed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                            </div>
                        @endif
                        @if ($payment->refunded_at)
                            <div>
                                <dt class="font-medium text-gray-500">refunded_at</dt>
                                <dd class="mt-1 text-gray-900">{{ $payment->refunded_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500">{{ __('admin.operations.payments.refund_amount') }}</dt>
                                <dd class="mt-1 tabular-nums text-gray-900">{{ $payment->refund_amount }} {{ $payment->currency }}</dd>
                            </div>
                            @if ($payment->refund_reference)
                                <div>
                                    <dt class="font-medium text-gray-500">{{ __('admin.operations.payments.refund_reference') }}</dt>
                                    <dd class="mt-1 text-gray-900">{{ $payment->refund_reference }}</dd>
                                </div>
                            @endif
                        @endif
                    </dl>

                    @if ($payment->failure_reason)
                        <p class="mt-4 text-sm text-red-700">
                            <span class="font-medium">{{ __('admin.operations.failure_reason') }}:</span>
                            {{ $payment->failure_reason }}
                        </p>
                    @endif
                </div>
            </div>

            @if ($payment->status === \App\Models\Payment::STATUS_PAID)
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6 sm:p-8">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('admin.operations.payments.refund_heading') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $refundApiEnabled
                                ? __('admin.operations.payments.refund_intro_with_api')
                                : __('admin.operations.payments.refund_intro_ledger_only') }}
                        </p>
                        <form method="POST" action="{{ route('app.admin.payments.refund', $payment) }}" class="mt-4 space-y-3" onsubmit="return confirm('{{ __('admin.operations.payments.refund_confirm') }}');">
                            @csrf
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="refund-amount" class="mb-1 block text-xs font-medium text-gray-600">
                                        {{ __('admin.operations.payments.refund_amount') }}
                                    </label>
                                    <input
                                        id="refund-amount"
                                        type="number"
                                        step="0.01"
                                        name="refund_amount"
                                        min="0.01"
                                        max="{{ $payment->amount }}"
                                        value="{{ old('refund_amount', $payment->amount) }}"
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                    />
                                    <x-input-error class="mt-1" :messages="$errors->get('refund_amount')" />
                                </div>
                                <div>
                                    <label for="refund-reference" class="mb-1 block text-xs font-medium text-gray-600">
                                        {{ __('admin.operations.payments.refund_reference') }}
                                    </label>
                                    <input
                                        id="refund-reference"
                                        type="text"
                                        name="refund_reference"
                                        maxlength="160"
                                        value="{{ old('refund_reference') }}"
                                        placeholder="{{ __('admin.operations.payments.refund_reference_placeholder') }}"
                                        class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                    />
                                    <x-input-error class="mt-1" :messages="$errors->get('refund_reference')" />
                                </div>
                            </div>
                            <div>
                                <label for="refund-reason" class="mb-1 block text-xs font-medium text-gray-600">
                                    {{ __('admin.operations.payments.refund_reason') }}
                                </label>
                                <textarea
                                    id="refund-reason"
                                    name="refund_reason"
                                    rows="2"
                                    maxlength="500"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                >{{ old('refund_reason') }}</textarea>
                                <x-input-error class="mt-1" :messages="$errors->get('refund_reason')" />
                            </div>
                            <x-danger-button data-testid="admin-payment-refund-submit">
                                {{ __('admin.operations.payments.refund_submit') }}
                            </x-danger-button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($payment->subscription_id || $payment->order_id)
                <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="p-6 sm:p-8">
                        <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.operations.emails.resend.section_heading') }}</h3>
                        <p class="mt-1 text-xs text-gray-600">{{ __('admin.operations.emails.resend.section_intro') }}</p>
                        @if (session('status') === 'admin-email-resent')
                            <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-900">{{ __('admin.operations.emails.resend.flash_resent') }}</p>
                        @elseif (session('status') === 'admin-email-resend-not-found')
                            <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-900">{{ __('admin.operations.emails.resend.flash_not_found') }}</p>
                        @endif
                        <div class="mt-3">
                            @if ($payment->subscription_id)
                                <x-admin.email-resend context="subscription" :entity-id="$payment->subscription_id" />
                            @elseif ($payment->order_id)
                                <x-admin.email-resend context="order" :entity-id="$payment->order_id" />
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="p-6 sm:p-8">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('admin.operations.payments.audit_heading') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.operations.payments.audit_intro') }}</p>

                    @if ($auditEvents->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">{{ __('admin.operations.payments.audit_empty') }}</p>
                    @else
                        <ul class="mt-4 divide-y divide-gray-100">
                            @foreach ($auditEvents as $event)
                                <li class="py-3 text-sm" data-testid="admin-payment-audit-row-{{ $event->id }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="font-medium text-gray-900">{{ $event->action }}</span>
                                        <span class="tabular-nums text-xs text-gray-500">{{ $event->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-600">
                                        {{ __('admin.operations.payments.audit_actor') }}:
                                        {{ $event->actor?->email ?? __('admin.operations.payments.audit_actor_system') }}
                                        @if ($event->from_state || $event->to_state)
                                            · {{ $event->from_state ?? '—' }} &rarr; {{ $event->to_state ?? '—' }}
                                        @endif
                                    </p>
                                    @if ($event->reason)
                                        <p class="mt-1 text-xs italic text-gray-700">&ldquo;{{ $event->reason }}&rdquo;</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <x-admin.audit-timeline entity-type="payment" :entity-id="$payment->id" />
        </div>
    </div>
</x-admin-layout>
