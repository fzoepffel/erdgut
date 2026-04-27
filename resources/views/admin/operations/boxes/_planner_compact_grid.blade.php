@props(['weeks'])

<div class="rounded-lg border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.compact_grid_title') }}</h3>
        <p class="text-xs text-gray-500">{{ __('admin.box_setup.compact_grid_hint', ['count' => count($weeks)]) }}</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <caption class="sr-only">{{ __('admin.box_setup.compact_grid_title') }}</caption>
            <thead class="bg-gray-50">
                <tr class="text-left font-medium uppercase tracking-wider text-gray-500">
                    <th scope="col" class="px-3 py-2">{{ __('admin.box_setup.week_label') }}</th>
                    <th scope="col" class="px-3 py-2">{{ __('admin.operations.status') }}</th>
                    <th scope="col" class="px-3 py-2 text-right">{{ __('admin.box_setup.eligible_subs') }}</th>
                    <th scope="col" class="px-3 py-2 text-right">{{ __('admin.box_setup.boxes_set_up') }}</th>
                    <th scope="col" class="px-3 py-2 text-right">S</th>
                    <th scope="col" class="px-3 py-2 text-right">M</th>
                    <th scope="col" class="px-3 py-2 text-right">L</th>
                    <th scope="col" class="px-3 py-2 text-right">{{ __('admin.box_setup.total_value') }}</th>
                    <th scope="col" class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($weeks as $row)
                    @php
                        $status = $row['status'];
                        $statusLabel = __('admin.box_setup.status_'.$status);
                        $statusClass = match ($status) {
                            'set_up' => 'bg-emerald-100 text-emerald-900',
                            'partial' => 'bg-amber-100 text-amber-900',
                            default => 'bg-gray-100 text-gray-700',
                        };
                        $perSize = $row['per_size'];
                    @endphp
                    <tr>
                        <th scope="row" class="whitespace-nowrap px-3 py-2 text-left font-medium text-gray-900">
                            {{ $row['week']->translatedFormat('d.m.') }}
                            <span class="text-gray-500">{{ $row['week']->translatedFormat('Y') }}</span>
                        </th>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-900">{{ $row['eligible_count'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-900">{{ $row['boxes_count'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ ($perSize['small']['boxes'] ?? 0) }}/{{ ($perSize['small']['eligible'] ?? 0) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ ($perSize['medium']['boxes'] ?? 0) }}/{{ ($perSize['medium']['eligible'] ?? 0) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ ($perSize['large']['boxes'] ?? 0) }}/{{ ($perSize['large']['eligible'] ?? 0) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-900">{{ number_format($row['total_estimated_value'], 2, ',', '.') }} €</td>
                        <td class="px-3 py-2 text-right">
                            <a href="{{ route('app.admin.boxes.setup.show', ['week' => $row['week_start_date']]) }}" class="font-medium text-emerald-700 hover:underline">
                                {{ ($row['total_boxes_count'] ?? $row['boxes_count']) > 0 ? __('admin.box_setup.compact_grid_cta_review') : __('admin.box_setup.compact_grid_cta_setup') }}
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
