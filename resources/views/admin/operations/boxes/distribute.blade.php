@php
    /** @var \Illuminate\Support\Carbon $week */
    /** @var array $preview */
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_distribute.title') }}
            </h2>
            <a href="{{ route('app.admin.boxes.planner') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.box_planner.back_to_planner') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ __('admin.box_distribute.intro', ['date' => $week->translatedFormat('d.m.Y')]) }}
            </p>

            <div class="rounded-lg border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-950">
                <p class="font-medium">{{ __('admin.box_distribute.summary_title') }}</p>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    <li>{{ __('admin.box_distribute.summary_targets', ['count' => $preview['target_count']]) }}</li>
                    <li>{{ __('admin.box_distribute.summary_skipped', ['count' => $preview['skipped_locked_count']]) }}</li>
                </ul>
            </div>

            @if ($preview['target_count'] === 0)
                <p class="text-sm text-gray-600">{{ __('admin.box_distribute.no_targets') }}</p>
            @else
                <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <caption class="sr-only">{{ __('admin.box_distribute.table_caption') }}</caption>
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <th scope="col" class="py-2 pe-4">{{ __('admin.operations.user') }}</th>
                                <th scope="col" class="py-2 pe-4">{{ __('Box size') }}</th>
                                <th scope="col" class="py-2 pe-4">{{ __('admin.box_distribute.col_box_id') }}</th>
                                <th scope="col" class="py-2 pe-4">{{ __('admin.box_distribute.col_categories') }}</th>
                                <th scope="col" class="py-2 pe-0">{{ __('admin.box_distribute.col_products') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($preview['rows'] as $row)
                                @php
                                    $box = $row['box'];
                                    $subscription = $row['subscription'];
                                @endphp
                                <tr class="align-top">
                                    <td class="py-3 pe-4 text-gray-900">{{ $box->user?->email ?? '—' }}</td>
                                    <td class="py-3 pe-4 text-gray-700">{{ $subscription->box_size }}</td>
                                    <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $box->id }}</td>
                                    <td class="py-3 pe-4 text-gray-700">
                                        @forelse ($row['category_counts'] as $cat => $cnt)
                                            <span class="me-2 inline-block whitespace-nowrap">{{ $cat }}: {{ $cnt }}</span>
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                    <td class="py-3 pe-0 text-gray-600">
                                        {{ \Illuminate\Support\Str::limit(implode(', ', $row['product_names']), 200) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm font-medium text-rose-900">{{ __('admin.box_distribute.confirm_title') }}</p>
                    <p class="mt-1 text-sm text-rose-800">{{ __('admin.box_distribute.confirm_body') }}</p>
                    <form method="post" action="{{ route('app.admin.boxes.distribute.apply') }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />
                        <label class="flex items-center gap-2 text-sm text-rose-900">
                            <input type="checkbox" name="confirm_distribute" value="1" class="rounded border-gray-300 text-rose-700 focus:ring-rose-600" />
                            {{ __('admin.box_distribute.confirm_checkbox') }}
                        </label>
                        <button type="submit" class="inline-flex rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-800">
                            {{ __('admin.box_distribute.submit') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
