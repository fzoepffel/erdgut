@php
    /** @var \App\Services\Admin\AdminDashboardMetricsSnapshot $metrics */
    /** @var list<\App\Services\Admin\AdminOperationalAlert> $operationalAlerts */
@endphp
<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @include('admin.partials.operational-alerts', ['alerts' => $operationalAlerts])

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-users-total">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.users_total_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->usersTotal) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.users_total_hint') }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-users-verified">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.users_verified_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->usersVerified) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.users_verified_hint') }}</p>
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
                    <p class="mt-1 text-sm font-medium text-amber-900 tabular-nums">{{ __('admin.metrics.expected_amount', ['amount' => number_format($metrics->upcomingBillableAmount7d, 2, ',', '.')]) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.upcoming_charges_hint') }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-trial-pending-scheduling">
                    <p class="text-sm font-medium text-gray-600">{{ __('Trial orders to schedule') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->trialOrdersPendingScheduling) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Paid trial orders without a delivery date.') }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-trial-boxes-to-compose">
                    <p class="text-sm font-medium text-gray-600">{{ __('Trial boxes to compose') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->trialOrdersBoxesToCompose) }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-metric-trial-conversion-30d">
                    <p class="text-sm font-medium text-gray-600">{{ __('Trial → subscription (30d)') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->trialConversionRate30d * 100, 1) }}%</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __(':converted of :delivered delivered', ['converted' => $metrics->trialOrdersConvertedLast30d, 'delivered' => $metrics->trialOrdersDeliveredLast30d]) }}</p>
                </div>

                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-1" data-testid="admin-metric-upcoming-30d">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.metrics.upcoming_charges_30d_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($metrics->upcomingBillableCharges30dCount) }}</p>
                    <p class="mt-1 text-sm font-medium text-amber-900 tabular-nums">{{ __('admin.metrics.expected_amount', ['amount' => number_format($metrics->upcomingBillableAmount30d, 2, ',', '.')]) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.metrics.upcoming_charges_hint') }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.dashboard.intro_title') }}</h3>
                <p class="mt-2 text-sm text-gray-600">
                    {{ __('admin.dashboard.intro_body') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a
                        href="{{ route('app.admin.producers.index') }}"
                        class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                    >
                        {{ __('Producers') }}
                    </a>
                    <a
                        href="{{ route('app.admin.products.index') }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                    >
                        {{ __('Products') }}
                    </a>
                </div>
            </div>

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
