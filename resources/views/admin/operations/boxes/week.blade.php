<x-admin-layout>
    <x-slot name="breadcrumbs">
        <x-admin.breadcrumbs :items="[
            ['label' => __('admin.shell.overview'), 'href' => route('app.admin.dashboard')],
            ['label' => __('admin.box_setup.title'), 'href' => route('app.admin.boxes.index')],
            ['label' => __('admin.breadcrumbs.week_label', ['date' => $week->translatedFormat('d.m.Y')]), 'href' => route('app.admin.boxes.setup.show', ['week' => $week->toDateString()])],
            ['label' => __('admin.box_setup.cta_view_boxes')],
        ]" />
    </x-slot>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_setup.week_boxes_title', ['date' => $week->translatedFormat('d.m.Y')]) }}
            </h2>
            <div class="flex items-center gap-4">
                <a href="{{ route('app.admin.boxes.setup.show', ['week' => $week->toDateString()]) }}" class="text-sm font-medium text-emerald-700 hover:underline">
                    {{ __('admin.box_setup.cta_review') }}
                </a>
                <a href="{{ route('app.admin.boxes.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                    {{ __('admin.box_setup.back_to_overview') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-600">
                {{ __('admin.box_setup.week_boxes_intro') }}
            </p>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @if (empty($rows))
                    <p class="p-6 text-center text-sm text-gray-600">
                        {{ __('admin.box_setup.week_boxes_empty') }}
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_customer') }}</th>
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_size') }}</th>
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_box_status') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right">{{ __('admin.box_setup.col_items') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right">{{ __('admin.box_setup.col_estimated_value') }}</th>
                                    <th scope="col" class="px-4 py-2">{{ __('admin.box_setup.col_modification_deadline') }}</th>
                                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('admin.box_setup.col_actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    @php
                                        $box = $row['box'];
                                        $size = $row['box_size'];
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ $box->user?->name ?? '—' }}</div>
                                            <div class="text-xs text-gray-500">{{ $box->user?->email ?? '—' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @if ($size !== null)
                                                {{ __('admin.box_setup.size_'.$size) }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3"><x-status-badge :value="$box->status" type="box" /></td>
                                        <td class="px-4 py-3 text-right tabular-nums text-gray-700">
                                            {{ $row['non_extra_count'] }}
                                            @if ($row['extra_count'] > 0)
                                                <span class="text-xs text-gray-500">
                                                    (+{{ $row['extra_count'] }} {{ __('admin.box_setup.extras_suffix') }})
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-gray-900">
                                            @eur($box->estimated_value)
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            {{ $box->modification_deadline_at?->translatedFormat('d.m.Y H:i') ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a
                                                href="{{ route('app.admin.boxes.show', $box) }}"
                                                class="text-sm font-semibold text-emerald-700 hover:underline"
                                            >
                                                {{ __('admin.box_setup.cta_open_box') }}
                                            </a>
                                        </td>
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
