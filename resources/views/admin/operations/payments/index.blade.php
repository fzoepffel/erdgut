@php
    $hasFilters = filled(request('status'))
        || filled(request('kind'))
        || filled(request('subscription_id'))
        || filled(request('order_id'))
        || filled(request('date_from'))
        || filled(request('date_to'))
        || (request('date_field') && request('date_field') !== 'created_at');
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.payments_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'admin-payment-bulk-marked-paid-externally')
                @php
                    $bulkMarkedCount = (int) session('bulk_marked_count', 0);
                    $bulkSkippedCount = (int) session('bulk_skipped_count', 0);
                @endphp
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status" data-testid="admin-payments-bulk-flash">
                    {{ trans_choice('admin.operations.payments.bulk_marked_paid_externally_flash', $bulkMarkedCount, ['count' => $bulkMarkedCount]) }}
                    @if ($bulkSkippedCount > 0)
                        <span class="ms-1 text-emerald-700">{{ trans_choice('admin.operations.payments.bulk_skipped_count', $bulkSkippedCount, ['count' => $bulkSkippedCount]) }}</span>
                    @endif
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    {{-- Filters: stack on mobile, switch to a horizontal flex
                         row at md: (768px+) so most desktops get the compact
                         layout. Was lg: which left ~1024px viewports stuck
                         with the vertical stack. --}}
                    <form method="get" action="{{ route('app.admin.payments.index') }}" class="flex flex-col flex-wrap gap-3 md:flex-row md:items-end">
                        <input type="hidden" name="sort" value="{{ request('sort', 'created_at') }}" />
                        <input type="hidden" name="direction" value="{{ request('direction', 'desc') }}" />
                        <div class="w-full sm:w-auto">
                            <label for="admin-pay-kind" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Type') }}</label>
                            <select
                                id="admin-pay-kind"
                                name="kind"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-44"
                            >
                                <option value="">{{ __('All') }}</option>
                                <option value="subscription" @selected(request('kind') === 'subscription')>{{ __('Subscription') }}</option>
                                <option value="order" @selected(request('kind') === 'order')>{{ __('Trial order') }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-pay-subscription" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.filter_subscription_id') }}</label>
                            <input
                                id="admin-pay-subscription"
                                type="number"
                                name="subscription_id"
                                min="1"
                                value="{{ request('subscription_id') }}"
                                placeholder="ID"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-32"
                            />
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-pay-order" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Order') }} ID</label>
                            <input
                                id="admin-pay-order"
                                type="number"
                                name="order_id"
                                min="1"
                                value="{{ request('order_id') }}"
                                placeholder="ID"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-32"
                            />
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-pay-status" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.status') }}</label>
                            <select id="admin-pay-status" name="status" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-40">
                                <option value="">{{ __('admin.operations.filter_all') }}</option>
                                <option value="pending" @selected(request('status') === 'pending')>{{ __('admin.operations.payment_status.pending') }}</option>
                                <option value="paid" @selected(request('status') === 'paid')>{{ __('admin.operations.payment_status.paid') }}</option>
                                <option value="failed" @selected(request('status') === 'failed')>{{ __('admin.operations.payment_status.failed') }}</option>
                                <option value="refunded" @selected(request('status') === 'refunded')>{{ __('admin.operations.payment_status.refunded') }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-pay-date-field" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.payments_date_field') }}</label>
                            <select id="admin-pay-date-field" name="date_field" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-44">
                                <option value="created_at" @selected($dateField === 'created_at')>{{ __('admin.operations.payments_date_field_created') }}</option>
                                <option value="paid_at" @selected($dateField === 'paid_at')>{{ __('admin.operations.payments_date_field_paid') }}</option>
                                <option value="failed_at" @selected($dateField === 'failed_at')>{{ __('admin.operations.payments_date_field_failed') }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-pay-from" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.date_from') }}</label>
                            <input id="admin-pay-from" type="date" name="date_from" value="{{ request('date_from') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-pay-to" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.date_to') }}</label>
                            <input id="admin-pay-to" type="date" name="date_to" value="{{ request('date_to') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-primary-button size="sm">
                                {{ __('admin.catalog.apply_filters') }}
                            </x-primary-button>
                            @if ($hasFilters)
                                <a href="{{ route('app.admin.payments.index') }}" class="btn-ghost-sm">
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                            <a href="{{ route('app.admin.payments.export', request()->query()) }}" class="btn-ghost-sm">
                                {{ __('admin.operations.payments_export_csv') }}
                            </a>
                        </div>
                    </form>

                    @if ($payments->isEmpty())
                        <div class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center">
                            <p class="text-sm font-medium text-gray-900">{{ __('admin.operations.payments_empty') }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ __('admin.operations.payments_empty_hint') }}</p>
                        </div>
                    @else
                        @php
                            $bulkEligibleIds = $payments
                                ->reject(fn ($p) => in_array($p->status, [\App\Models\Payment::STATUS_PAID, \App\Models\Payment::STATUS_REFUNDED], true))
                                ->pluck('id')
                                ->values()
                                ->all();
                        @endphp
                        <div
                            x-data="{
                                selectedIds: [],
                                reference: '',
                                eligibleIds: @js($bulkEligibleIds),
                                toggle(id) {
                                    const i = this.selectedIds.indexOf(id);
                                    if (i === -1) this.selectedIds.push(id);
                                    else this.selectedIds.splice(i, 1);
                                },
                                isSelected(id) { return this.selectedIds.includes(id); },
                                allEligibleSelected() {
                                    return this.eligibleIds.length > 0
                                        && this.eligibleIds.every(id => this.selectedIds.includes(id));
                                },
                                toggleAllEligible(ev) {
                                    if (ev.target.checked) {
                                        this.selectedIds = [...new Set([...this.selectedIds, ...this.eligibleIds])];
                                    } else {
                                        this.selectedIds = this.selectedIds.filter(id => !this.eligibleIds.includes(id));
                                    }
                                },
                                clearSelection() { this.selectedIds = []; this.reference = ''; },
                            }"
                            class="space-y-3"
                        >
                            {{-- Bulk action bar — only renders when at least one
                                 payment is selected. Submits its own form (the
                                 per-row mark-paid form is independent). --}}
                            <form
                                method="POST"
                                action="{{ route('app.admin.payments.bulk-mark-paid-externally') }}"
                                x-show="selectedIds.length > 0"
                                x-cloak
                                x-on:submit="if (!confirm(@js(__('admin.operations.payments.bulk_mark_paid_externally_confirm')))) $event.preventDefault();"
                                class="flex flex-wrap items-end gap-3 rounded-md border border-emerald-200 bg-emerald-50 p-3"
                                data-testid="admin-payments-bulk-bar"
                            >
                                @csrf
                                <template x-for="id in selectedIds" :key="'bulk-id-' + id">
                                    <input type="hidden" name="payment_ids[]" :value="id" />
                                </template>
                                <div class="flex-1 min-w-[14rem]">
                                    <label for="admin-pay-bulk-ref" class="block text-xs font-medium text-emerald-900">
                                        <span x-text="@js(__('admin.operations.payments.bulk_selection_label')).replace(':count', selectedIds.length)"></span>
                                    </label>
                                    <input
                                        id="admin-pay-bulk-ref"
                                        type="text"
                                        name="external_reference"
                                        required
                                        maxlength="160"
                                        x-model="reference"
                                        placeholder="{{ __('admin.operations.payments.external_reference_placeholder') }}"
                                        class="mt-1 block w-full rounded-md border-emerald-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                    />
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-primary-button size="sm" data-testid="admin-payments-bulk-submit">
                                        {{ __('admin.operations.payments.bulk_mark_paid_externally') }}
                                    </x-primary-button>
                                    <button type="button" class="btn-ghost-sm" @click="clearSelection()">
                                        {{ __('admin.operations.payments.bulk_clear_selection') }}
                                    </button>
                                </div>
                            </form>

                        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">{{ __('admin.operations.payments_table_caption') }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th scope="col" class="py-2 pe-2 w-8">
                                            @if (count($bulkEligibleIds) > 0)
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600"
                                                    :checked="allEligibleSelected()"
                                                    @change="toggleAllEligible($event)"
                                                    aria-label="{{ __('admin.operations.payments.bulk_select_all_aria') }}"
                                                    data-testid="admin-payments-select-all"
                                                />
                                            @endif
                                        </th>
                                        <th scope="col" class="py-2 pe-4">ID</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.user') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Type') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Source') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.amount') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.status') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.attempt') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.failure_reason') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.created_at') }}</th>
                                        <th scope="col" class="py-2 pe-4">paid_at</th>
                                        <th scope="col" class="py-2 pe-4">failed_at</th>
                                        <th scope="col" class="py-2 pe-0 text-right">{{ __('admin.operations.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($payments as $payment)
                                        @php
                                            $bulkEligible = ! in_array($payment->status, [\App\Models\Payment::STATUS_PAID, \App\Models\Payment::STATUS_REFUNDED], true);
                                        @endphp
                                        <tr data-testid="admin-payment-row-{{ $payment->id }}" :class="isSelected({{ $payment->id }}) ? 'bg-emerald-50/40' : ''">
                                            <td class="py-3 pe-2 align-top">
                                                @if ($bulkEligible)
                                                    <input
                                                        type="checkbox"
                                                        class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600"
                                                        :checked="isSelected({{ $payment->id }})"
                                                        @change="toggle({{ $payment->id }})"
                                                        aria-label="{{ __('admin.operations.payments.bulk_select_row_aria', ['id' => $payment->id]) }}"
                                                        data-testid="admin-payments-row-checkbox-{{ $payment->id }}"
                                                    />
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $payment->id }}</td>
                                            <td class="py-3 pe-4 text-gray-900">
                                                {{ $payment->subscription?->user?->email ?? $payment->order?->user?->email ?? '—' }}
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                {{ $payment->subscription_id ? __('Subscription') : __('Trial order') }}
                                            </td>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">
                                                @if ($payment->subscription_id)
                                                    #{{ $payment->subscription_id }}
                                                @elseif ($payment->order_id)
                                                    #{{ $payment->order_id }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $payment->amount }} {{ $payment->currency }}</td>
                                            <td class="py-3 pe-4 text-gray-600"><x-status-badge :value="$payment->status" type="payment" /></td>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $payment->attempt_number }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $payment->failure_reason ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $payment->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $payment->paid_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $payment->failed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                                            <td class="py-3 pe-0 text-right">
                                                <div class="flex flex-wrap items-center justify-end gap-2">
                                                    @if ($payment->status !== \App\Models\Payment::STATUS_PAID && $payment->status !== \App\Models\Payment::STATUS_REFUNDED)
                                                        <form method="POST" action="{{ route('app.admin.payments.mark-paid-externally', $payment) }}" class="inline-flex items-center gap-2" onsubmit="return confirm('{{ __('admin.operations.payments.mark_paid_externally_confirm') }}');">
                                                            @csrf
                                                            <input type="text" name="external_reference" required maxlength="160" placeholder="{{ __('admin.operations.payments.external_reference_placeholder') }}" class="w-40 rounded-md border-gray-300 text-xs shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                                                            <x-primary-button size="sm">
                                                                {{ __('admin.operations.payments.mark_paid_externally') }}
                                                            </x-primary-button>
                                                        </form>
                                                    @elseif ($payment->status === \App\Models\Payment::STATUS_PAID && $payment->external_reference)
                                                        <span class="text-xs text-gray-500" title="{{ $payment->external_reference }}">{{ __('admin.operations.payments.external_marked') }}</span>
                                                    @endif
                                                    <a href="{{ route('app.admin.payments.show', $payment) }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50" data-testid="admin-payment-view-{{ $payment->id }}">
                                                        {{ __('admin.operations.payments.view') }}
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="pt-2">{{ $payments->links() }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
