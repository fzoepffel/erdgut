<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_fill_canonical.preview_title') }}
            </h2>
            <a href="{{ route('app.admin.boxes.planner') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.box_planner.back_to_planner') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <p class="text-sm text-gray-600">
                {{ __('admin.box_fill_canonical.preview_intro', ['date' => $week->translatedFormat('d.m.Y')]) }}
            </p>
            <p class="text-xs text-gray-500">{{ __('admin.box_fill_canonical.edit_hint') }}</p>

            @if ($preview['size_blocks'] === [])
                <p class="text-sm text-gray-600">{{ __('admin.box_fill_canonical.no_blocks') }}</p>
            @else
                <form method="post" action="{{ route('app.admin.boxes.planner.canonical-apply') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />

                    @foreach ($preview['size_blocks'] as $block)
                        @php
                            $size = $block['box_size'];
                            $lineIndex = 0;
                        @endphp
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">
                                {{ __('admin.box_planner.size_label_' . $size) }}
                                <span class="ms-2 font-normal text-gray-600">
                                    ({{ __('admin.box_fill_canonical.cap_used') }} {{ number_format($block['cap_used'], 2, ',', '.') }} € ·
                                    {{ __('admin.box_fill_canonical.template_total') }} {{ number_format($block['template_total'], 2, ',', '.') }} €)
                                </span>
                            </h3>
                            <p class="mt-2 text-xs text-gray-600">{{ __('admin.box_fill_canonical.same_lines_hint') }}</p>

                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead>
                                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                            <th class="py-2 pe-2">{{ __('admin.box_planner.col_product') }}</th>
                                            <th class="py-2 pe-2">{{ __('admin.box_planner.col_quantity') }}</th>
                                            <th class="py-2 pe-2">{{ __('admin.box_fill_canonical.col_remove') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($block['template_lines'] as $line)
                                            @php $p = $line['product']; @endphp
                                            <tr>
                                                <td class="py-2 pe-2 align-top">
                                                    <select
                                                        name="templates[{{ $size }}][lines][{{ $lineIndex }}][product_id]"
                                                        class="block w-full max-w-md rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    >
                                                        @foreach ($catalogProducts as $cp)
                                                            <option value="{{ $cp->id }}" @selected($cp->id === $p->id)>{{ $cp->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="py-2 pe-2 align-top">
                                                    <input
                                                        type="number"
                                                        name="templates[{{ $size }}][lines][{{ $lineIndex }}][quantity]"
                                                        value="{{ $line['quantity'] }}"
                                                        step="0.01"
                                                        min="0.01"
                                                        max="500"
                                                        class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    />
                                                </td>
                                                <td class="py-2 pe-2 align-top">
                                                    <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                        <input type="checkbox" name="templates[{{ $size }}][lines][{{ $lineIndex }}][_delete]" value="1" class="rounded border-gray-300 text-rose-600 focus:ring-rose-500" />
                                                        {{ __('admin.box_fill_canonical.remove_line') }}
                                                    </label>
                                                </td>
                                            </tr>
                                            @php $lineIndex++; @endphp
                                        @endforeach

                                        @for ($e = 0; $e < 10; $e++)
                                            <tr class="bg-gray-50/50">
                                                <td class="py-2 pe-2 align-top">
                                                    <select
                                                        name="templates[{{ $size }}][lines][{{ $lineIndex }}][product_id]"
                                                        class="block w-full max-w-md rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    >
                                                        <option value="">{{ __('admin.box_fill_canonical.add_line_placeholder') }}</option>
                                                        @foreach ($catalogProducts as $cp)
                                                            <option value="{{ $cp->id }}">{{ $cp->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="py-2 pe-2 align-top">
                                                    <input
                                                        type="number"
                                                        name="templates[{{ $size }}][lines][{{ $lineIndex }}][quantity]"
                                                        value="1"
                                                        step="0.01"
                                                        min="0.01"
                                                        max="500"
                                                        class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                    />
                                                </td>
                                                <td class="py-2 pe-2 align-top text-xs text-gray-400">—</td>
                                            </tr>
                                            @php $lineIndex++; @endphp
                                        @endfor
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 border-t border-gray-100 pt-3">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_fill_canonical.subscriptions_heading') }}</p>
                                <ul class="mt-2 space-y-1 text-sm text-gray-800">
                                    @foreach ($block['subscriptions'] as $subRow)
                                        @php $sub = $subRow['subscription']; @endphp
                                        <li class="flex flex-wrap items-center gap-2">
                                            <span>{{ $sub->user?->email ?? '—' }}</span>
                                            <span class="tabular-nums text-gray-600">
                                                {{ __('admin.box_fill_canonical.budget_abbr') }}
                                                {{ number_format($subRow['included_budget'], 2, ',', '.') }} €
                                            </span>
                                            @if ($subRow['over_budget'])
                                                <span class="rounded bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">
                                                    {{ __('admin.box_planner.over_budget_badge') }}
                                                </span>
                                            @endif
                                            @if ($subRow['will_create_box'])
                                                <span class="text-xs text-amber-700">{{ __('admin.box_fill_canonical.will_create_box_short') }}</span>
                                            @endif
                                            @if ($subRow['skipped_not_editable'])
                                                <span class="text-xs text-amber-700">{{ __('admin.box_fill_canonical.skipped_not_editable_short') }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach

                    <div class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                        <p class="text-sm font-medium text-rose-900">{{ __('admin.box_fill_canonical.confirm_title') }}</p>
                        <p class="mt-1 text-sm text-rose-800">{{ __('admin.box_fill_canonical.confirm_body') }}</p>
                        <div class="mt-4 space-y-3">
                            <label class="flex items-center gap-2 text-sm text-rose-900">
                                <input type="checkbox" name="confirm_fill" value="1" class="rounded border-gray-300 text-rose-700 focus:ring-rose-600" />
                                {{ __('admin.box_fill_canonical.confirm_checkbox') }}
                            </label>
                            <button type="submit" class="inline-flex rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-800">
                                {{ __('admin.box_fill_canonical.submit_apply') }}
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-admin-layout>
