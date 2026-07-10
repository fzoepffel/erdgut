@php
    /** @var \App\Services\Admin\AdminDashboardMetricsSnapshot $metrics */
    /** @var \App\Services\Admin\AdminOperationsSnapshot $ops */
    /** @var list<\App\Services\Admin\AdminOperationalAlert> $operationalAlerts */
@endphp
<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.dashboard.heading') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            @include('admin.partials.operational-alerts', ['alerts' => $operationalAlerts])

            {{-- ── Operations: KPI row ─────────────────────────────────── --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-testid="admin-ops-kpis">
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-ops-kpi-revenue-week">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_revenue_week_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">@eur($ops->revenueThisWeekNet)</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __('admin.ops.kpi_revenue_week_gross', ['amount' => \App\Support\Money::format($ops->revenueThisWeekGross)]) }}
                        @if ($ops->revenueWeekDeltaPct !== null)
                            <span class="{{ $ops->revenueWeekDeltaPct >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                · {{ __('admin.ops.kpi_vs_last_week', ['pct' => ($ops->revenueWeekDeltaPct >= 0 ? '+' : '').number_format($ops->revenueWeekDeltaPct, 1)]) }}
                            </span>
                        @endif
                    </p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-ops-kpi-orders-week">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_orders_week_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($ops->ordersThisWeekB2c + $ops->ordersThisWeekB2b) }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.ops.kpi_orders_split', ['b2c' => $ops->ordersThisWeekB2c, 'b2b' => $ops->ordersThisWeekB2b]) }}</p>
                </div>
                <a href="{{ route('app.admin.restaurant-orders.index', ['status' => 'submitted']) }}"
                   class="rounded-lg border p-5 shadow-sm transition hover:shadow-md {{ $ops->openB2bRequests > 0 ? 'border-amber-300 bg-amber-50/70' : 'border-amber-100 bg-white' }}"
                   data-testid="admin-ops-kpi-open-requests">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_open_requests_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums {{ $ops->openB2bRequests > 0 ? 'text-amber-900' : 'text-gray-400' }}">{{ number_format($ops->openB2bRequests) }}</p>
                </a>
                <a href="{{ route('app.admin.restaurant-orders.index') }}"
                   class="rounded-lg border p-5 shadow-sm transition hover:shadow-md {{ $ops->unpaidB2bInvoicesCount > 0 ? 'border-amber-300 bg-amber-50/70' : 'border-amber-100 bg-white' }}"
                   data-testid="admin-ops-kpi-unpaid-invoices">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_unpaid_invoices_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums {{ $ops->unpaidB2bInvoicesCount > 0 ? 'text-amber-900' : 'text-gray-400' }}">{{ number_format($ops->unpaidB2bInvoicesCount) }}</p>
                    @if ($ops->unpaidB2bInvoicesCount > 0)
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.ops.kpi_unpaid_invoices_sum', ['amount' => \App\Support\Money::format($ops->unpaidB2bInvoicesNet)]) }}</p>
                    @endif
                </a>
                <a href="{{ route('app.admin.shop-orders.index') }}" class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm transition hover:shadow-md" data-testid="admin-ops-kpi-cash-outstanding">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_cash_outstanding_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">@eur($ops->outstandingInPersonEur)</p>
                </a>
                <a href="{{ route('app.admin.standing-orders.index') }}" class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm transition hover:shadow-md" data-testid="admin-ops-kpi-standing-orders">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_standing_orders_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($ops->activeStandingOrders) }}</p>
                </a>
                <a href="{{ route('app.admin.restaurant-accounts.index') }}"
                   class="rounded-lg border p-5 shadow-sm transition hover:shadow-md {{ $ops->pendingRestaurantApplications > 0 ? 'border-amber-300 bg-amber-50/70' : 'border-amber-100 bg-white' }}"
                   data-testid="admin-ops-kpi-applications">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_applications_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums {{ $ops->pendingRestaurantApplications > 0 ? 'text-amber-900' : 'text-gray-400' }}">{{ number_format($ops->pendingRestaurantApplications) }}</p>
                </a>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-ops-kpi-aov-30d">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_aov_30d_label') }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">@eur($ops->avgOrderValueB2c30dNet)</p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.ops.kpi_aov_split', ['b2c' => \App\Support\Money::format($ops->avgOrderValueB2c30dNet), 'b2b' => \App\Support\Money::format($ops->avgOrderValueB2b30dNet)]) }}</p>
                </div>
                <div class="rounded-lg border border-amber-100 bg-white p-5 shadow-sm" data-testid="admin-ops-kpi-margin-week">
                    <p class="text-sm font-medium text-gray-600">{{ __('admin.ops.kpi_margin_week_label') }}</p>
                    @if ($ops->marginThisWeekNet !== null)
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">@eur($ops->marginThisWeekNet)</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.ops.kpi_margin_estimate_hint', ['pct' => number_format($ops->marginCoveragePct, 0)]) }}</p>
                    @else
                        <p class="mt-2 text-2xl font-semibold tabular-nums text-gray-400">–</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.ops.kpi_margin_unknown') }}</p>
                    @endif
                </div>
            </div>

            {{-- ── Operations: weekly revenue chart ────────────────────── --}}
            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm" data-testid="admin-ops-revenue-chart">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.ops.revenue_chart_title') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('admin.ops.revenue_chart_hint') }}</p>
                @php
                    $hasRevenue = collect($ops->weeklyRevenue)->contains(fn ($b) => $b->b2cNet > 0 || $b->b2bNet > 0);
                @endphp
                @if ($hasRevenue)
                    <div class="relative mt-4 h-72 sm:h-80">
                        <canvas id="admin-ops-weekly-chart"></canvas>
                    </div>
                @else
                    <p class="mt-4 text-sm text-gray-400">{{ __('admin.ops.no_data') }}</p>
                @endif
                <script type="application/json" id="admin-ops-weekly-data">@json(['buckets' => $ops->weeklyRevenue, 'labels' => ['b2c' => __('admin.ops.channel_b2c'), 'b2b' => __('admin.ops.channel_b2b')]])</script>
            </div>

            {{-- ── Operations: top products chart ──────────────────────── --}}
            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm" data-testid="admin-ops-top-products">
                <h3 class="text-base font-semibold text-gray-900">{{ __('admin.ops.top_products_title') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('admin.ops.top_products_hint') }}</p>
                @if (count($ops->topProducts) > 0)
                    <div class="relative mt-4 h-64 sm:h-72">
                        <canvas id="admin-ops-products-chart"></canvas>
                    </div>
                    <ol class="mt-4 divide-y divide-gray-100 text-sm">
                        @foreach ($ops->topProducts as $row)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                                <span class="text-gray-800">{{ $row->name }}@if ($row->producer) <span class="text-gray-400">({{ $row->producer }})</span>@endif</span>
                                <span class="tabular-nums text-gray-500">{{ rtrim(rtrim(number_format($row->qtyDisplay, 2, ',', '.'), '0'), ',') }} {{ $row->unitSymbol }} · @eur($row->netRevenue)</span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="mt-4 text-sm text-gray-400">{{ __('admin.ops.no_data') }}</p>
                @endif
                <script type="application/json" id="admin-ops-products-data">@json(['rows' => $ops->topProducts, 'label' => __('admin.ops.top_products_title')])</script>
            </div>


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
@push('scripts')
    @vite('resources/js/admin-dashboard.js')
@endpush
</x-admin-layout>
