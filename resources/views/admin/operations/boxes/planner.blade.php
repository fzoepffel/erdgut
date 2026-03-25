<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.box_planner.title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="text-sm text-gray-600">{{ __('admin.box_planner.intro') }}</p>

            <details class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 text-sm text-gray-800">
                <summary class="cursor-pointer font-medium text-gray-900">{{ __('admin.box_planner.legend_title') }}</summary>
                <div class="mt-3 space-y-3 text-gray-700">
                    <p>{{ __('admin.box_planner.legend_canonical_body') }}</p>
                    <p>{{ __('admin.box_planner.legend_distribute_body') }}</p>
                    <p class="text-xs text-gray-500">{{ __('admin.box_planner.legend_docs_hint') }}</p>
                </div>
            </details>

            @if ($planningMarginRate > 0 && $planningMarginRate < 1)
                <p class="text-xs text-gray-500">
                    {{ __('admin.box_planner.planning_margin_hint', ['percent' => (int) round($planningMarginRate * 100)]) }}
                </p>
            @endif

            @if (count($weeks) > 0)
                <div class="flex flex-wrap gap-2 lg:hidden">
                    @foreach ($weeks as $week)
                        <a
                            href="{{ route('app.admin.boxes.planner.canonical-preview', ['week_start_date' => $week->toDateString()]) }}"
                            class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-900 hover:bg-emerald-100"
                        >
                            {{ __('admin.box_fill_canonical.link_short') }} · {{ $week->translatedFormat('d.m.') }}
                        </a>
                        <a
                            href="{{ route('app.admin.boxes.distribute', ['week_start_date' => $week->toDateString()]) }}"
                            class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100"
                        >
                            {{ __('admin.box_distribute.link_short') }} · {{ $week->translatedFormat('d.m.') }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Mobile: stacked cards per subscription --}}
            <div class="space-y-6 lg:hidden">
                @forelse ($matrix as $row)
                    @php
                        $subscription = $row['subscription'];
                        $includedBudget = $row['included_budget'];
                        $planningHint = $row['planning_hint'];
                    @endphp
                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="mb-3 border-b border-gray-100 pb-3">
                            <p class="text-sm font-semibold text-gray-900">{{ $subscription->user?->email ?? '—' }}</p>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('admin.box_planner.sub_id') }} {{ $subscription->id }} ·
                                {{ __('admin.box_planner.size_label_' . $subscription->box_size) }}
                            </p>
                            <p class="mt-0.5 text-xs tabular-nums text-gray-600">
                                {{ __('admin.box_planner.nominal_price_label') }}
                                {{ number_format($row['nominal_included_ttc'], 2, ',', '.') }} €
                            </p>
                            <p class="mt-0.5 text-xs tabular-nums text-gray-600">
                                {{ __('admin.box_planner.effective_budget_label') }}
                                {{ number_format($includedBudget, 2, ',', '.') }} €
                            </p>
                            @if ($row['trial_budget_notice'])
                                <p class="mt-0.5 text-xs text-amber-800">{{ __('admin.box_planner.trial_budget_notice') }}</p>
                            @endif
                            @if ($planningHint !== null)
                                <p class="mt-0.5 text-xs tabular-nums text-gray-500">
                                    {{ __('admin.box_planner.planning_hint_label') }} {{ number_format($planningHint, 2, ',', '.') }} €
                                </p>
                            @endif
                        </div>
                        <div class="space-y-4">
                            @foreach ($row['cells'] as $cell)
                                @php
                                    $week = $cell['week'];
                                    $eligible = $cell['eligible'];
                                    $box = $cell['box'];
                                @endphp
                                <div class="rounded-md border border-gray-100 bg-gray-50/80 p-3">
                                    <p class="text-xs font-medium text-gray-700">{{ $week->translatedFormat('d.m.Y') }} ({{ __('admin.box_planner.week_monday') }})</p>
                                    @include('admin.operations.boxes.partials.planner-cell', [
                                        'subscription' => $subscription,
                                        'week' => $week,
                                        'eligible' => $eligible,
                                        'box' => $box,
                                        'included_budget' => $includedBudget,
                                        'estimated_value' => $cell['estimated_value'] ?? null,
                                        'over_budget' => $cell['over_budget'] ?? false,
                                        'items_summary' => $cell['items_summary'] ?? null,
                                    ])
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-600">{{ __('admin.box_planner.empty_subscriptions') }}</p>
                @endforelse
            </div>

            {{-- Desktop: wide table --}}
            <div class="hidden lg:block">
                @if ($matrix === [])
                    <p class="text-sm text-gray-600">{{ __('admin.box_planner.empty_subscriptions') }}</p>
                @else
                    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <caption class="sr-only">{{ __('admin.box_planner.table_caption') }}</caption>
                            <thead>
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th scope="col" class="sticky left-0 z-10 bg-white py-2 pe-4 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">
                                        {{ __('admin.operations.subscription') }}
                                    </th>
                                    @foreach ($weeks as $week)
                                        <th scope="col" class="min-w-[12rem] py-2 pe-4 align-bottom">
                                            <div class="space-y-2">
                                                <span class="block whitespace-nowrap">{{ $week->translatedFormat('d.m.Y') }}</span>
                                                <div class="space-y-1 text-[0.7rem] font-normal normal-case">
                                                    <a
                                                        href="{{ route('app.admin.boxes.planner.canonical-preview', ['week_start_date' => $week->toDateString()]) }}"
                                                        class="block w-full rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-center font-medium text-emerald-900 hover:bg-emerald-100"
                                                    >
                                                        {{ __('admin.box_fill_canonical.link_short') }}
                                                    </a>
                                                    <a
                                                        href="{{ route('app.admin.boxes.distribute', ['week_start_date' => $week->toDateString()]) }}"
                                                        class="mt-1 block text-center text-[0.7rem] font-medium text-amber-800 hover:underline"
                                                    >
                                                        {{ __('admin.box_distribute.link_short') }}
                                                    </a>
                                                </div>
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($matrix as $row)
                                    @php
                                        $subscription = $row['subscription'];
                                        $includedBudget = $row['included_budget'];
                                        $planningHint = $row['planning_hint'];
                                    @endphp
                                    <tr class="align-top">
                                        <th scope="row" class="sticky left-0 z-10 bg-white py-3 pe-4 text-left text-xs font-normal text-gray-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">
                                            <div class="max-w-[16rem]">
                                                <span class="block truncate font-medium">{{ $subscription->user?->email ?? '—' }}</span>
                                                <span class="mt-1 block text-gray-500">
                                                    #{{ $subscription->id }} · {{ __('admin.box_planner.size_label_' . $subscription->box_size) }}
                                                </span>
                                                <span class="mt-1 block tabular-nums text-gray-600">
                                                    {{ __('admin.box_planner.nominal_price_label') }}
                                                    {{ number_format($row['nominal_included_ttc'], 2, ',', '.') }} €
                                                </span>
                                                <span class="mt-0.5 block tabular-nums text-gray-600">
                                                    {{ __('admin.box_planner.effective_budget_label') }}
                                                    {{ number_format($includedBudget, 2, ',', '.') }} €
                                                </span>
                                                @if ($row['trial_budget_notice'])
                                                    <span class="mt-0.5 block text-xs text-amber-800">{{ __('admin.box_planner.trial_budget_notice') }}</span>
                                                @endif
                                                @if ($planningHint !== null)
                                                    <span class="mt-0.5 block tabular-nums text-xs text-gray-500">
                                                        {{ __('admin.box_planner.planning_hint_label') }}
                                                        {{ number_format($planningHint, 2, ',', '.') }} €
                                                    </span>
                                                @endif
                                            </div>
                                        </th>
                                        @foreach ($row['cells'] as $cell)
                                            @php
                                                $week = $cell['week'];
                                                $eligible = $cell['eligible'];
                                                $box = $cell['box'];
                                            @endphp
                                            <td class="py-3 pe-4">
                                                @include('admin.operations.boxes.partials.planner-cell', [
                                                    'subscription' => $subscription,
                                                    'week' => $week,
                                                    'eligible' => $eligible,
                                                    'box' => $box,
                                                    'included_budget' => $includedBudget,
                                                    'estimated_value' => $cell['estimated_value'] ?? null,
                                                    'over_budget' => $cell['over_budget'] ?? false,
                                                    'items_summary' => $cell['items_summary'] ?? null,
                                                ])
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
