<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_setup.title') }}
            </h2>
            <a href="{{ route('app.admin.products.weekly.show') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.box_setup.link_weekly_catalog') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-600">
                {{ __('admin.box_setup.intro') }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($weeks as $row)
                    @php
                        $status = $row['status'];
                        $statusLabel = __('admin.box_setup.status_'.$status);
                        $statusClass = match ($status) {
                            'set_up' => 'bg-emerald-100 text-emerald-900 border-emerald-200',
                            'partial' => 'bg-amber-100 text-amber-900 border-amber-200',
                            default => 'bg-gray-100 text-gray-700 border-gray-200',
                        };
                    @endphp
                    <div class="flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">
                                    {{ __('admin.box_setup.week_label') }}
                                </p>
                                <p class="text-base font-semibold text-gray-900">
                                    {{ $row['week']->translatedFormat('d.m.Y') }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $row['week']->translatedFormat('l') }}
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500">{{ __('admin.box_setup.eligible_subs') }}</dt>
                                <dd class="mt-0.5 tabular-nums text-gray-900">{{ $row['eligible_count'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">{{ __('admin.box_setup.boxes_set_up') }}</dt>
                                <dd class="mt-0.5 tabular-nums text-gray-900">{{ $row['boxes_count'] }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs text-gray-500">{{ __('admin.box_setup.total_value') }}</dt>
                                <dd class="mt-0.5 tabular-nums text-gray-900">
                                    {{ number_format($row['total_estimated_value'], 2, ',', '.') }} €
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-3 border-t border-gray-100 pt-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {{ __('admin.box_setup.by_size') }}
                            </p>
                            <ul class="mt-1 space-y-0.5 text-xs text-gray-700">
                                @foreach ($row['per_size'] as $size => $counts)
                                    <li class="flex items-center justify-between">
                                        <span>{{ __('admin.box_setup.size_'.$size) }}</span>
                                        <span class="tabular-nums text-gray-900">
                                            {{ $counts['boxes'] }} / {{ $counts['eligible'] }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="mt-4">
                            <a
                                href="{{ route('app.admin.boxes.setup.show', ['week' => $row['week_start_date']]) }}"
                                class="inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                            >
                                {{ $row['boxes_count'] > 0 ? __('admin.box_setup.cta_review') : __('admin.box_setup.cta_setup') }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-admin-layout>
