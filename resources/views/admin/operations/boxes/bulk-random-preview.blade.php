<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_fill_random.preview_title') }}
            </h2>
            <a href="{{ route('app.admin.boxes.planner') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.box_planner.back_to_planner') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ __('admin.box_fill_random.preview_intro', ['date' => $week->translatedFormat('d.m.Y')]) }}
            </p>

            @if ($preview['rows'] === [])
                <p class="text-sm text-gray-600">{{ __('admin.box_fill_random.no_rows') }}</p>
            @else
                <div class="space-y-8">
                    @foreach ($preview['rows'] as $row)
                        @php
                            $sub = $row['subscription'];
                        @endphp
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 pb-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $sub->user?->email ?? '—' }}</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ __('admin.box_planner.sub_id') }} {{ $sub->id }} ·
                                        {{ __('admin.box_planner.size_label_' . $sub->box_size) }}
                                        · {{ __('admin.box_fill_random.budget_abbr') }}
                                        {{ number_format($row['included_budget'], 2, ',', '.') }} €
                                    </p>
                                </div>
                                <div class="text-right text-sm">
                                    <span class="tabular-nums font-medium text-gray-900">
                                        {{ __('admin.box_fill_random.preview_total') }}:
                                        {{ number_format($row['estimated_value'], 2, ',', '.') }} €
                                    </span>
                                    @if ($row['over_budget'])
                                        <span class="ms-2 rounded bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">
                                            {{ __('admin.box_planner.over_budget_badge') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if ($row['will_create_box'])
                                <p class="mt-2 text-xs text-amber-800">{{ __('admin.box_fill_random.will_create_box') }}</p>
                            @endif
                            @if ($row['skipped_not_editable'])
                                <p class="mt-2 text-xs text-amber-800">{{ __('admin.box_fill_random.skipped_not_editable') }}</p>
                            @endif
                            <ul class="mt-3 list-inside list-disc text-sm text-gray-700">
                                @forelse ($row['lines'] as $line)
                                    @php $p = $line['product']; @endphp
                                    <li>
                                        {{ $p->name }}
                                        <span class="tabular-nums text-gray-500">
                                            ({{ number_format($line['line_total'], 2, ',', '.') }} €)
                                        </span>
                                    </li>
                                @empty
                                    <li>{{ __('admin.box_fill_random.no_lines') }}</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-sm font-medium text-rose-900">{{ __('admin.box_fill_random.confirm_title') }}</p>
                    <p class="mt-1 text-sm text-rose-800">{{ __('admin.box_fill_random.confirm_body_random') }}</p>
                    <form method="post" action="{{ route('app.admin.boxes.planner.bulk-random-apply') }}" class="mt-4 space-y-3">
                        @csrf
                        <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />
                        <label class="flex items-center gap-2 text-sm text-rose-900">
                            <input type="checkbox" name="confirm_fill" value="1" class="rounded border-gray-300 text-rose-700 focus:ring-rose-600" />
                            {{ __('admin.box_fill_random.confirm_checkbox') }}
                        </label>
                        <button type="submit" class="inline-flex rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-800">
                            {{ __('admin.box_fill_random.submit_apply') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
