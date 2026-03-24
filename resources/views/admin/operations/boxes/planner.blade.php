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

            {{-- Mobile: stacked cards per subscription --}}
            <div class="space-y-6 lg:hidden">
                @forelse ($matrix as $row)
                    @php
                        $subscription = $row['subscription'];
                    @endphp
                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="mb-3 border-b border-gray-100 pb-3">
                            <p class="text-sm font-semibold text-gray-900">{{ $subscription->user?->email ?? '—' }}</p>
                            <p class="text-xs text-gray-500">
                                {{ __('admin.box_planner.sub_id') }} {{ $subscription->id }} ·
                                {{ __('admin.operations.plan') }}:
                                @if ($subscription->plan->value === 'weekly')
                                    {{ __('admin.box_planner.plan_weekly') }}
                                @else
                                    {{ __('admin.box_planner.plan_biweekly') }}
                                @endif
                                · {{ __('Box size') }}: {{ $subscription->box_size }}
                            </p>
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
                                        <th scope="col" class="min-w-[11rem] py-2 pe-4 align-bottom">
                                            <div class="space-y-2">
                                                <span class="block whitespace-nowrap">{{ $week->translatedFormat('d.m.Y') }}</span>
                                                <form method="post" action="{{ route('app.admin.boxes.planner.bulk-preset') }}" class="space-y-1 text-[0.7rem] font-normal normal-case">
                                                    @csrf
                                                    <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />
                                                    <label class="flex items-center gap-1 text-gray-600">
                                                        <input type="checkbox" name="confirm_bulk" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" />
                                                        {{ __('admin.box_planner.bulk_confirm_short') }}
                                                    </label>
                                                    <button type="submit" class="w-full rounded bg-gray-900 px-2 py-1 text-xs font-semibold text-white hover:bg-gray-800">
                                                        {{ __('admin.box_planner.bulk_fill_column') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($matrix as $row)
                                    @php
                                        $subscription = $row['subscription'];
                                    @endphp
                                    <tr class="align-top">
                                        <th scope="row" class="sticky left-0 z-10 bg-white py-3 pe-4 text-left text-xs font-normal text-gray-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">
                                            <div class="max-w-[14rem]">
                                                <span class="block truncate font-medium">{{ $subscription->user?->email ?? '—' }}</span>
                                                <span class="mt-1 block text-gray-500">#{{ $subscription->id }} · {{ $subscription->box_size }}</span>
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
