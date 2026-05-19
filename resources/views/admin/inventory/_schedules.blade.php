@php
    /** @var \App\Models\Product $product */
    /** @var \Illuminate\Support\Collection $schedules */

    $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit() ?? 'piece';
    $unitLabel = \App\Enums\ProductUnit::displaySymbol($displayUnit);
    $factor = $product->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;
    $today = \Illuminate\Support\Carbon::now()->startOfDay();
    $defaultFreshness = (int) ($product->default_freshness_window_days ?? 1);

    $weekdayLabels = [
        0 => __('admin.inflow_schedules.weekday_0'),
        1 => __('admin.inflow_schedules.weekday_1'),
        2 => __('admin.inflow_schedules.weekday_2'),
        3 => __('admin.inflow_schedules.weekday_3'),
        4 => __('admin.inflow_schedules.weekday_4'),
        5 => __('admin.inflow_schedules.weekday_5'),
        6 => __('admin.inflow_schedules.weekday_6'),
    ];
@endphp

@if ($schedules->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_weekday') }}</th>
                    <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_quantity') }}</th>
                    <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_freshness') }}</th>
                    <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_starts') }}</th>
                    <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_ends') }}</th>
                    <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_notes') }}</th>
                    <th class="py-2 pe-0 text-right">{{ __('admin.inflow_schedules.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($schedules as $schedule)
                    @php
                        $active = $schedule->ends_on === null || $schedule->ends_on->gte($today);
                    @endphp
                    <tr x-data="{ adjusting: false, cancelling: false }">
                        <td class="py-3 pe-4">{{ $weekdayLabels[(int) $schedule->weekday] ?? '—' }}</td>
                        <td class="py-3 pe-4 tabular-nums">
                            {{ rtrim(rtrim(number_format((float) $schedule->planned_quantity / $factor, 2, ',', '.'), '0'), ',') }} {{ $unitLabel }}
                        </td>
                        <td class="py-3 pe-4 tabular-nums">{{ $schedule->freshness_window_days }}</td>
                        <td class="py-3 pe-4 text-gray-600 tabular-nums">{{ $schedule->starts_on?->translatedFormat('d.m.Y') }}</td>
                        <td class="py-3 pe-4 text-gray-600 tabular-nums">
                            @if ($schedule->ends_on === null)
                                <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                    {{ __('admin.inflow_schedules.indefinite') }}
                                </span>
                            @else
                                {{ $schedule->ends_on->translatedFormat('d.m.Y') }}
                            @endif
                        </td>
                        <td class="py-3 pe-4 text-xs text-gray-600">{{ $schedule->notes ?? '—' }}</td>
                        <td class="py-3 pe-0 text-right">
                            @if ($active)
                                <div class="flex flex-col items-end gap-2">
                                    <div class="flex gap-2">
                                        <button type="button" class="btn-ghost-sm" x-on:click="adjusting = ! adjusting; cancelling = false">
                                            {{ __('admin.inflow_schedules.action_adjust') }}
                                        </button>
                                        <button type="button" class="btn-ghost-sm text-rose-700" x-on:click="cancelling = ! cancelling; adjusting = false">
                                            {{ __('admin.inflow_schedules.action_cancel') }}
                                        </button>
                                    </div>

                                    <form
                                        x-show="adjusting"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('app.admin.inflow-schedules.adjust', ['product' => $product, 'schedule' => $schedule]) }}"
                                        class="flex flex-wrap items-end gap-2 rounded border border-emerald-200 bg-emerald-50/40 p-3 text-left"
                                    >
                                        @csrf
                                        <input type="hidden" name="_redirect" value="{{ url()->full() }}" />
                                        <label class="text-xs text-gray-700">
                                            {{ __('admin.inflow_schedules.field_from_date') }}
                                            <input type="date" name="from_date" required class="mt-1 block rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                                        </label>
                                        <label class="text-xs text-gray-700">
                                            {{ __('admin.inflow_schedules.field_new_quantity') }} ({{ $unitLabel }})
                                            <input type="number" name="new_quantity" step="0.01" min="0.01" required class="mt-1 block w-32 rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                                        </label>
                                        <x-primary-button size="sm">{{ __('admin.inflow_schedules.submit_adjust') }}</x-primary-button>
                                    </form>

                                    <form
                                        x-show="cancelling"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('app.admin.inflow-schedules.cancel', ['product' => $product, 'schedule' => $schedule]) }}"
                                        class="flex flex-wrap items-end gap-2 rounded border border-rose-200 bg-rose-50/40 p-3 text-left"
                                        onsubmit="return confirm('{{ __('admin.inflow_schedules.confirm_cancel') }}');"
                                    >
                                        @csrf
                                        <input type="hidden" name="_redirect" value="{{ url()->full() }}" />
                                        <label class="text-xs text-gray-700">
                                            {{ __('admin.inflow_schedules.field_from_date') }}
                                            <input type="date" name="from_date" required class="mt-1 block rounded border-gray-300 text-sm shadow-sm focus:border-rose-600 focus:ring-rose-600" />
                                        </label>
                                        <x-danger-button type="submit" size="sm">{{ __('admin.inflow_schedules.submit_cancel') }}</x-danger-button>
                                    </form>
                                </div>
                            @else
                                <span class="text-xs text-gray-400">{{ __('admin.inflow_schedules.ended') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<details class="rounded border border-gray-200 bg-gray-50/50 p-4">
    <summary class="cursor-pointer text-sm font-semibold text-gray-900">
        {{ __('admin.inflow_schedules.create_heading') }}
    </summary>
    <form
        method="POST"
        action="{{ route('app.admin.inflow-schedules.store', $product) }}"
        class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2"
    >
        @csrf
        <input type="hidden" name="_redirect" value="{{ url()->full() }}" />
        <label class="text-xs text-gray-700">
            {{ __('admin.inflow_schedules.field_weekday') }}
            <select name="weekday" required class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                @foreach ($weekdayLabels as $value => $label)
                    <option value="{{ $value }}" @selected($value === 3)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs text-gray-700">
            {{ __('admin.inflow_schedules.field_quantity') }} ({{ $unitLabel }})
            <input type="number" name="planned_quantity" step="0.01" min="0.01" required class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
        </label>
        <label class="text-xs text-gray-700">
            {{ __('admin.inflow_schedules.field_freshness') }}
            <input type="number" name="freshness_window_days" min="1" max="60" value="{{ $defaultFreshness }}" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
        </label>
        <label class="text-xs text-gray-700">
            {{ __('admin.inflow_schedules.field_starts_on') }}
            <input type="date" name="starts_on" required class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
        </label>
        <label class="text-xs text-gray-700 sm:col-span-2">
            {{ __('admin.inflow_schedules.field_ends_on_optional') }}
            <input type="date" name="ends_on" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
            <span class="mt-1 block text-[11px] text-gray-500">{{ __('admin.inflow_schedules.field_ends_on_hint') }}</span>
        </label>
        <label class="text-xs text-gray-700 sm:col-span-2">
            {{ __('admin.inflow_schedules.field_notes') }}
            <textarea name="notes" rows="2" maxlength="500" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"></textarea>
        </label>
        <div class="sm:col-span-2 flex justify-end">
            <x-primary-button size="sm">{{ __('admin.inflow_schedules.submit_create') }}</x-primary-button>
        </div>
    </form>
</details>
