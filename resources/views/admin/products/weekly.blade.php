@php
    $extraRowCount = 5;
    $rowIndex = 0;
@endphp
<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.weekly_catalog.title') }}
            </h2>
            <a
                href="{{ route('app.admin.products.index') }}"
                class="text-sm font-medium text-emerald-700 hover:underline"
            >
                {{ __('admin.weekly_catalog.back_to_products') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-rose-50 p-4 text-sm text-rose-800" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">
                            {{ __('admin.weekly_catalog.week_label') }}
                        </p>
                        <p class="text-lg font-semibold text-gray-900">
                            {{ $week->translatedFormat('l, d.m.Y') }}
                        </p>
                    </div>
                    {{-- G1 (UX notes): two forms on this page both have
                         actions matching `*products/weekly*`, which used to
                         confuse selectors and tab-Enter chains. Tagged
                         data-purpose to disambiguate without changing
                         behaviour. --}}
                    <form method="get" action="{{ route('app.admin.products.weekly.show') }}" class="flex items-end gap-2" data-purpose="week-jump">
                        <div>
                            <label for="weekly-catalog-week" class="block text-xs font-medium text-gray-600 mb-1">
                                {{ __('admin.weekly_catalog.jump_to_week') }}
                            </label>
                            <input
                                id="weekly-catalog-week"
                                type="date"
                                name="week"
                                value="{{ $week->toDateString() }}"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            />
                        </div>
                        <x-primary-button size="sm">
                            {{ __('admin.weekly_catalog.jump_go') }}
                        </x-primary-button>
                    </form>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <a
                        href="{{ route('app.admin.products.weekly.show', ['week' => $previousWeek->toDateString()]) }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        ← {{ $previousWeek->translatedFormat('d.m.') }}
                    </a>
                    <a
                        href="{{ route('app.admin.products.weekly.show') }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('admin.weekly_catalog.default_week') }}
                    </a>
                    <a
                        href="{{ route('app.admin.products.weekly.show', ['week' => $nextWeek->toDateString()]) }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ $nextWeek->translatedFormat('d.m.') }} →
                    </a>
                </div>
            </div>

            <p class="text-sm text-gray-600">{{ __('admin.weekly_catalog.help') }}</p>

            @if ($overCount > 0)
                <div class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
                    {{ __('admin.weekly_catalog.over_planned_warning', ['count' => $overCount]) }}
                </div>
            @endif

            <form method="post" action="{{ route('app.admin.products.weekly.save', ['week' => $week->toDateString()]) }}" class="space-y-4" data-purpose="weekly-catalog-save">
                @csrf
                <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />

                @if ($rows->isEmpty())
                    {{-- Empty-state hint sits ABOVE the table. The table still
                         renders $extraRowCount empty input rows below, so a
                         "no rows" message inside the tbody used to read as a
                         contradiction ("no rows" + visible empty rows). --}}
                    <div class="rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700" data-testid="weekly-catalog-empty-hint">
                        {{ __('admin.weekly_catalog.no_rows') }}
                    </div>
                @endif

                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                    <th class="px-3 py-2">
                                        <span class="inline-flex items-center gap-1">
                                            {{ __('admin.weekly_catalog.col_mode') }}
                                            <span
                                                tabindex="0"
                                                class="cursor-help rounded-full border border-gray-400 px-1.5 text-[10px] font-bold normal-case tracking-normal text-gray-500 hover:border-gray-600 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                                title="{{ __('admin.weekly_catalog.col_mode_hint') }}"
                                                aria-label="{{ __('admin.weekly_catalog.col_mode_hint') }}"
                                            >?</span>
                                        </span>
                                    </th>
                                    <th class="px-3 py-2">{{ __('admin.weekly_catalog.col_product') }}</th>
                                    <th class="px-3 py-2">{{ __('admin.weekly_catalog.col_quantity') }}</th>
                                    <th class="px-3 py-2">{{ __('admin.weekly_catalog.col_unit') }}</th>
                                    <th class="whitespace-nowrap px-3 py-2">{{ __('admin.weekly_catalog.col_remaining') }}</th>
                                    <th class="whitespace-nowrap px-3 py-2">{{ __('admin.weekly_catalog.col_delete') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rows as $row)
                                    @php
                                        /** @var \App\Models\ProductWeekStock $stock */
                                        $stock = $row['stock'];
                                        /** @var \App\Models\Product|null $product */
                                        $product = $row['product'];
                                        $rowIndexKey = $rowIndex;
                                    @endphp
                                    <tr class="{{ $row['over'] ? 'bg-rose-50' : '' }}">
                                        <td class="px-3 py-2 align-top">
                                            <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                {{ __('admin.weekly_catalog.row_mode_existing') }}
                                            </span>
                                            <input type="hidden" name="rows[{{ $rowIndexKey }}][mode]" value="existing" />
                                            <input type="hidden" name="rows[{{ $rowIndexKey }}][stock_id]" value="{{ $stock->id }}" />
                                            <input type="hidden" name="rows[{{ $rowIndexKey }}][product_id]" value="{{ $product?->id }}" />
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <div class="font-medium text-gray-900">{{ $product?->name ?? '—' }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ __('admin.weekly_catalog.distributed_label') }}
                                                {{ rtrim(rtrim(number_format($row['display_distributed'], 2, ',', '.'), '0'), ',') }}
                                                {{ \App\Enums\ProductUnit::displaySymbol($row['display_unit']) }}
                                            </div>
                                            @if ($product?->id !== null)
                                                <a
                                                    href="#product-schedule-{{ $product->id }}"
                                                    class="mt-1 inline-flex text-xs font-medium text-emerald-700 hover:underline"
                                                >
                                                    {{ __('admin.weekly_catalog.manage_recurring_link') }} →
                                                </a>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <input
                                                type="number"
                                                name="rows[{{ $rowIndexKey }}][quantity]"
                                                value="{{ old('rows.'.$rowIndexKey.'.quantity', $row['display_quantity']) }}"
                                                step="0.01"
                                                min="0"
                                                max="50000"
                                                class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                            />
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            {{-- Unit is the product's chosen display unit (g/kg/ml/L/
                                                 piece/bunch). Read-only here so the weekly amount
                                                 can't drift relative to the price scale. To change
                                                 the unit, edit the product. --}}
                                            <span class="inline-flex items-center rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700" data-testid="weekly-row-unit-{{ $rowIndexKey }}">
                                                {{ \App\Enums\ProductUnit::displaySymbol($row['display_unit']) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <span class="tabular-nums {{ $row['over'] ? 'font-semibold text-rose-700' : 'text-gray-700' }}">
                                                {{ rtrim(rtrim(number_format($row['display_remaining'], 2, ',', '.'), '0'), ',') }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                                <input
                                                    type="checkbox"
                                                    name="rows[{{ $rowIndexKey }}][_delete]"
                                                    value="1"
                                                    class="rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                                                />
                                                {{ __('admin.weekly_catalog.delete_line') }}
                                            </label>
                                        </td>
                                    </tr>
                                    @php $rowIndex++; @endphp
                                @endforeach

                                {{-- Extra empty rows: existing product select --}}
                                @for ($i = 0; $i < $extraRowCount; $i++)
                                    @php $rowIndexKey = $rowIndex; @endphp
                                    <tr class="bg-gray-50/40">
                                        <td class="px-3 py-2 align-top">
                                            <select
                                                name="rows[{{ $rowIndexKey }}][mode]"
                                                title="{{ __('admin.weekly_catalog.col_mode_hint') }}"
                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                            >
                                                <option value="existing">{{ __('admin.weekly_catalog.row_mode_existing') }}</option>
                                                <option value="new">{{ __('admin.weekly_catalog.row_mode_new') }}</option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <div class="space-y-2">
                                                <select
                                                    name="rows[{{ $rowIndexKey }}][product_id]"
                                                    class="block w-full max-w-xs rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                >
                                                    <option value="">{{ __('admin.weekly_catalog.product_placeholder') }}</option>
                                                    @foreach ($catalogProducts as $cp)
                                                        <option value="{{ $cp->id }}">{{ $cp->name }}</option>
                                                    @endforeach
                                                </select>
                                                <details class="text-xs text-gray-600">
                                                    <summary class="cursor-pointer select-none">
                                                        {{ __('admin.weekly_catalog.new_product_toggle') }}
                                                    </summary>
                                                    <div class="mt-2 space-y-2 rounded border border-gray-200 bg-white p-2">
                                                        <input
                                                            type="text"
                                                            name="rows[{{ $rowIndexKey }}][new][name]"
                                                            placeholder="{{ __('admin.weekly_catalog.new_name_placeholder') }}"
                                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                        />
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <select
                                                                name="rows[{{ $rowIndexKey }}][new][producer_id]"
                                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                            >
                                                                <option value="">{{ __('admin.weekly_catalog.producer_placeholder') }}</option>
                                                                @foreach ($producers as $prod)
                                                                    <option value="{{ $prod->id }}">{{ $prod->name }}</option>
                                                                @endforeach
                                                            </select>
                                                            <select
                                                                name="rows[{{ $rowIndexKey }}][new][category]"
                                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                            >
                                                                <option value="">{{ __('admin.weekly_catalog.category_placeholder') }}</option>
                                                                @foreach ($categories as $cat)
                                                                    <option value="{{ $cat->value }}">{{ __('admin.category.'.$cat->value) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <input
                                                                type="number"
                                                                name="rows[{{ $rowIndexKey }}][new][unit_price_cents]"
                                                                placeholder="{{ __('admin.weekly_catalog.new_price_cents_placeholder') }}"
                                                                min="0"
                                                                step="1"
                                                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                            />
                                                            <label class="inline-flex items-center gap-1 text-xs text-gray-700">
                                                                <input
                                                                    type="checkbox"
                                                                    name="rows[{{ $rowIndexKey }}][new][is_published]"
                                                                    value="1"
                                                                    checked
                                                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                                />
                                                                {{ __('admin.weekly_catalog.new_is_published') }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                </details>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            <input
                                                type="number"
                                                name="rows[{{ $rowIndexKey }}][quantity]"
                                                value=""
                                                step="0.01"
                                                min="0"
                                                max="5000"
                                                class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                placeholder="0"
                                            />
                                        </td>
                                        <td class="px-3 py-2 align-top">
                                            {{-- Only meaningful when mode=new (inline product creation):
                                                 the new Product's canonical unit. For mode=existing the
                                                 product's stored unit wins; this select is ignored. --}}
                                            <select
                                                name="rows[{{ $rowIndexKey }}][unit]"
                                                class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                title="{{ __('admin.weekly_catalog.unit_select_hint') }}"
                                            >
                                                @foreach (\App\Enums\ProductUnit::cases() as $unitCase)
                                                    <option value="{{ $unitCase->value }}" @selected($unitCase === \App\Enums\ProductUnit::Piece)>
                                                        {{ $unitCase->value }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 align-top text-xs text-gray-400">—</td>
                                        <td class="px-3 py-2 align-top text-xs text-gray-400">—</td>
                                    </tr>
                                    @php $rowIndex++; @endphp
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <x-primary-button>
                        {{ __('admin.weekly_catalog.save_button') }}
                    </x-primary-button>
                </div>
            </form>

            {{-- Recurring weekly-stock schedules. Lives outside the catalog
                 save form because each panel hosts its own POST forms
                 (create / cancel / adjust) and HTML disallows nested forms. --}}
            @php
                // Dedup rows to one panel per product so multi-stock-row
                // edge cases (shouldn't happen in practice, but defensive)
                // don't render duplicate panels.
                $schedulePanelRows = collect($rows)
                    ->filter(fn ($r) => ($r['product'] ?? null) !== null)
                    ->unique(fn ($r) => $r['product']->id)
                    ->values();
            @endphp
            @if ($schedulePanelRows->isNotEmpty())
                <div class="space-y-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ __('admin.product_schedules.heading') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ __('admin.product_schedules.intro') }}</p>
                    </div>
                    <div class="space-y-3">
                        @foreach ($schedulePanelRows as $scheduleRow)
                            @php
                                /** @var \App\Models\Product $scheduleProduct */
                                $scheduleProduct = $scheduleRow['product'];
                                $scheduleCollection = $scheduleRow['schedules'] ?? collect();
                                $anchorId = 'product-schedule-'.$scheduleProduct->id;
                            @endphp
                            <details id="{{ $anchorId }}" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                                <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                                    <span>{{ $scheduleProduct->name }}</span>
                                    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                        {{ trans_choice('admin.product_schedules.summary_count', $scheduleCollection->count(), ['count' => $scheduleCollection->count()]) }}
                                    </span>
                                </summary>
                                <div class="border-t border-gray-100">
                                    @include('admin.products._schedules', [
                                        'product' => $scheduleProduct,
                                        'schedules' => $scheduleCollection,
                                    ])
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
