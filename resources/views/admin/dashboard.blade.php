@php
    /** @var \App\Services\Admin\AdminDashboardMetricsSnapshot $metrics */
    /** @var list<\App\Services\Admin\AdminOperationalAlert> $operationalAlerts */
@endphp
<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.dashboard.heading') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @include('admin.partials.operational-alerts', ['alerts' => $operationalAlerts])

            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm sm:rounded-lg" data-testid="admin-trial-pipeline">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('admin.trials.pipeline_title') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ __('admin.trials.pipeline_hint') }}</p>
                    </div>
                    <a
                        href="{{ route('app.admin.orders.index', ['product_type' => 'trial_box']) }}"
                        class="text-sm font-medium text-emerald-700 hover:underline"
                    >
                        {{ __('admin.trials.pipeline_link_all') }}
                    </a>
                </div>

                {{-- Zero-count cards dim down so admins see at a glance which
                     stages have items needing attention. The number is the
                     signal; we keep it but mute the visual weight when 0. --}}
                @php
                    $pipelineCardClass = fn (int $count): string => $count > 0
                        ? 'rounded-lg border border-gray-100 bg-white p-4 shadow-sm transition hover:border-gray-200'
                        : 'rounded-lg border border-gray-100 bg-gray-50/60 p-4 shadow-sm transition hover:border-gray-200';
                    $pipelineNumberClass = fn (int $count): string => $count > 0
                        ? 'mt-2 text-2xl font-semibold tabular-nums text-gray-900'
                        : 'mt-2 text-2xl font-semibold tabular-nums text-gray-400';
                @endphp
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <a
                        href="{{ route('app.admin.orders.index', ['product_type' => 'trial_box', 'status' => 'pending_payment']) }}"
                        class="{{ $pipelineCardClass($metrics->trialOrdersPendingPayment) }}"
                        data-testid="admin-trial-pipeline-pending-payment"
                    >
                        <p class="text-sm font-medium text-gray-600">{{ __('admin.trials.pipeline_pending_payment') }}</p>
                        <p class="{{ $pipelineNumberClass($metrics->trialOrdersPendingPayment) }}">{{ number_format($metrics->trialOrdersPendingPayment) }}</p>
                        @if ($metrics->trialOrdersPendingPayment === 0)
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.trials.pipeline_pending_payment_empty') }}</p>
                        @endif
                    </a>

                    <a
                        href="{{ route('app.admin.orders.index', ['product_type' => 'trial_box', 'status' => 'paid', 'needs_schedule' => '1']) }}"
                        class="{{ $pipelineCardClass($metrics->trialOrdersPaidNeedsScheduling) }}"
                        data-testid="admin-trial-pipeline-needs-scheduling"
                    >
                        <p class="text-sm font-medium text-gray-600">{{ __('admin.trials.pipeline_paid_needs_scheduling') }}</p>
                        <p class="{{ $pipelineNumberClass($metrics->trialOrdersPaidNeedsScheduling) }}">{{ number_format($metrics->trialOrdersPaidNeedsScheduling) }}</p>
                        <p class="mt-1 text-xs text-gray-500">
                            @if ($metrics->trialOrdersPaidNeedsScheduling === 0)
                                {{ __('admin.trials.pipeline_paid_needs_scheduling_empty') }}
                            @else
                                {{ __('admin.trials.pipeline_paid_needs_scheduling_hint') }}
                            @endif
                        </p>
                    </a>

                    <a
                        href="{{ route('app.admin.orders.index', ['product_type' => 'trial_box', 'status' => 'scheduled', 'needs_box' => '1']) }}"
                        class="{{ $pipelineCardClass($metrics->trialOrdersScheduledNeedsBox) }}"
                        data-testid="admin-trial-pipeline-needs-box"
                    >
                        <p class="text-sm font-medium text-gray-600">{{ __('admin.trials.pipeline_scheduled_needs_box') }}</p>
                        <p class="{{ $pipelineNumberClass($metrics->trialOrdersScheduledNeedsBox) }}">{{ number_format($metrics->trialOrdersScheduledNeedsBox) }}</p>
                        @if ($metrics->trialOrdersScheduledNeedsBox === 0)
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.trials.pipeline_scheduled_needs_box_empty') }}</p>
                        @endif
                    </a>

                    <a
                        href="{{ route('app.admin.orders.index', ['product_type' => 'trial_box', 'status' => 'scheduled', 'ready_to_deliver' => '1']) }}"
                        class="{{ $pipelineCardClass($metrics->trialOrdersReadyToDeliver) }}"
                        data-testid="admin-trial-pipeline-ready"
                    >
                        <p class="text-sm font-medium text-gray-600">{{ __('admin.trials.pipeline_ready_to_deliver') }}</p>
                        <p class="{{ $pipelineNumberClass($metrics->trialOrdersReadyToDeliver) }}">{{ number_format($metrics->trialOrdersReadyToDeliver) }}</p>
                        @if ($metrics->trialOrdersReadyToDeliver === 0)
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.trials.pipeline_ready_to_deliver_empty') }}</p>
                        @endif
                    </a>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Merged user count: shows total + how many are unverified
                     so we still surface the verification gap (the original
                     reason for two separate cards), without burning a whole
                     card on a number that's usually equal to "total". --}}
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-users-total">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.users_total_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->usersTotal) }}</p>
                    @php($unverified = max(0, $metrics->usersTotal - $metrics->usersVerified))
                    <p class="mt-1 text-xs {{ $unverified > 0 ? 'text-amber-700' : 'text-gray-500' }}">
                        @if ($unverified > 0)
                            {{ trans_choice('admin.metrics.users_unverified_summary', $unverified, ['count' => $unverified]) }}
                        @else
                            {{ __('admin.metrics.users_all_verified') }}
                        @endif
                    </p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-users-signups-7d">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.users_signups_7d_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->usersSignedUpLast7Days) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.users_signups_7d_hint') }}</p>
                </div>

                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-subs-active">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.subscriptions_active_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->subscriptionsActive) }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-subs-paused">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.subscriptions_paused_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->subscriptionsPaused) }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-subs-cancelled">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.subscriptions_cancelled_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->subscriptionsCancelled) }}</p>
                </div>

                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-producers">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.producers_total_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->producersTotal) }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-products-published">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.products_published_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->productsPublished) }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-products-draft">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.products_draft_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->productsDraft) }}</p>
                </div>

                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-payments-failed-7d">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.payments_failed_7d_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->paymentsFailedLast7Days) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.payments_failed_7d_hint') }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-payments-pending">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.payments_pending_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->paymentsPending) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.payments_pending_hint') }}</p>
                </div>

                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-upcoming-7d">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.upcoming_charges_7d_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->upcomingBillableCharges7dCount) }}</p>
                    <p class="mt-1 text-sm font-medium text-amber-900 tabular-nums">{{ __('admin.metrics.expected_amount', ['amount' => \App\Support\Money::format($metrics->upcomingBillableAmount7d)]) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.upcoming_charges_hint') }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-trial-pending-scheduling">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.trial_orders_to_schedule_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->trialOrdersPendingScheduling) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.trial_orders_to_schedule_hint') }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-trial-boxes-to-compose">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.trial_boxes_to_compose_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->trialOrdersBoxesToCompose) }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-trial-conversion-30d">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.trial_conversion_30d_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->trialConversionRate30d * 100, 1) }}%</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __(':converted of :delivered delivered', ['converted' => $metrics->trialOrdersConvertedLast30d, 'delivered' => $metrics->trialOrdersDeliveredLast30d]) }}</p>
                </div>

                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-1" data-testid="admin-metric-upcoming-30d">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.upcoming_charges_30d_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->upcomingBillableCharges30dCount) }}</p>
                    <p class="mt-1 text-sm font-medium text-amber-900 tabular-nums">{{ __('admin.metrics.expected_amount', ['amount' => \App\Support\Money::format($metrics->upcomingBillableAmount30d)]) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.upcoming_charges_hint') }}</p>
                </div>
            </div>

            {{-- Removed the "Katalog-Verwaltung" intro panel — it duplicated the
                 cards below. The cards are the functional surface. --}}

            <div class="grid gap-4 sm:grid-cols-2">
                <a
                    href="{{ route('app.admin.producers.index') }}"
                    class="group rounded-lg border border-gray-100 bg-white p-6 shadow-sm transition hover:border-emerald-200 hover:shadow-md sm:rounded-lg"
                >
                    <h3 class="text-sm font-semibold text-gray-900 group-hover:text-emerald-800">{{ __('admin.dashboard.card_producers_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('admin.dashboard.card_producers_desc') }}</p>
                    <span class="mt-4 inline-flex text-sm font-medium text-emerald-700">{{ __('admin.dashboard.view_list') }} →</span>
                </a>
                <a
                    href="{{ route('app.admin.products.index') }}"
                    class="group rounded-lg border border-gray-100 bg-white p-6 shadow-sm transition hover:border-emerald-200 hover:shadow-md sm:rounded-lg"
                >
                    <h3 class="text-sm font-semibold text-gray-900 group-hover:text-emerald-800">{{ __('admin.dashboard.card_products_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ __('admin.dashboard.card_products_desc') }}</p>
                    <span class="mt-4 inline-flex text-sm font-medium text-emerald-700">{{ __('admin.dashboard.view_list') }} →</span>
                </a>
            </div>
        </div>
    </div>
</x-admin-layout>
