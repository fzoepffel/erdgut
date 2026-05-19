@php
    /** @var \Illuminate\Support\Carbon $start */
    /** @var \Illuminate\Support\Carbon $end */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Product> $products */
    /** @var \Illuminate\Support\Collection<int, array<string,float>> $availabilityMatrix */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProductStockInflow> $inflows */
    /** @var string $previousWindow */
    /** @var string $nextWindow */
    /** @var string $today */

    $dateColumns = [];
    $cursor = $start->copy();
    while ($cursor->lte($end)) {
        $dateColumns[] = $cursor->copy();
        $cursor->addDay();
    }
@endphp
<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.inventory.title') }}
            </h1>
            <a
                href="{{ route('app.admin.products.index') }}"
                class="text-sm font-medium text-emerald-700 hover:underline"
            >
                {{ __('admin.weekly_catalog.back_to_products') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
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

            <p class="text-sm text-gray-600">{{ __('admin.inventory.intro') }}</p>

            {{-- Window navigator --}}
            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">
                            {{ __('admin.inventory.window_label') }}
                        </p>
                        <p class="text-lg font-semibold text-gray-900">
                            {{ $start->translatedFormat('d.m.Y') }} – {{ $end->translatedFormat('d.m.Y') }}
                        </p>
                    </div>
                    <form method="get" action="{{ route('app.admin.inventory.index') }}" class="flex items-end gap-2">
                        <div>
                            <label for="inventory-from" class="block text-xs font-medium text-gray-600 mb-1">
                                {{ __('admin.inventory.jump_to_date') }}
                            </label>
                            <input
                                id="inventory-from"
                                type="date"
                                name="from"
                                value="{{ $start->toDateString() }}"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            />
                        </div>
                        <x-primary-button size="sm">{{ __('admin.inventory.jump_go') }}</x-primary-button>
                    </form>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <a
                        href="{{ route('app.admin.inventory.index', ['from' => $previousWindow]) }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('admin.inventory.previous_window') }}
                    </a>
                    <a
                        href="{{ route('app.admin.inventory.index', ['from' => $today]) }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('admin.inventory.today_window') }}
                    </a>
                    <a
                        href="{{ route('app.admin.inventory.index', ['from' => $nextWindow]) }}"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        {{ __('admin.inventory.next_window') }}
                    </a>
                </div>
            </div>

            {{-- SECTION A — inflow batches in window --}}
            <section class="space-y-3" aria-labelledby="inventory-inflows-heading">
                <div>
                    <h2 id="inventory-inflows-heading" class="text-base font-semibold text-gray-900">
                        {{ __('admin.inventory.section_inflows') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.inventory.section_inflows_hint') }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        @if ($inflows->isEmpty())
                            <p class="p-6 text-sm text-gray-500">{{ __('admin.inventory.empty_inflows') }}</p>
                        @else
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                        <th class="px-3 py-2">{{ __('admin.inventory.col_received_on') }}</th>
                                        <th class="px-3 py-2">{{ __('admin.inventory.col_product') }}</th>
                                        <th class="px-3 py-2">{{ __('admin.inventory.col_quantity') }}</th>
                                        <th class="px-3 py-2">{{ __('admin.inventory.col_freshness') }}</th>
                                        <th class="px-3 py-2">{{ __('admin.inventory.col_source') }}</th>
                                        <th class="px-3 py-2">{{ __('admin.inventory.col_actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($inflows as $inflow)
                                        @php
                                            $product = $inflow->product;
                                            $displayUnit = $product?->price_display_unit ?? $product?->unit?->defaultDisplayUnit() ?? 'piece';
                                            $factor = $product?->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;
                                            $displayQty = (float) $inflow->quantity / $factor;
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 tabular-nums">{{ $inflow->received_on?->translatedFormat('d.m.Y') }}</td>
                                            <td class="px-3 py-2">{{ $product?->name ?? '—' }}</td>
                                            <td class="px-3 py-2 tabular-nums">
                                                {{ rtrim(rtrim(number_format($displayQty, 2, ',', '.'), '0'), ',') }} {{ \App\Enums\ProductUnit::displaySymbol($displayUnit) }}
                                            </td>
                                            <td class="px-3 py-2 tabular-nums">{{ $inflow->freshness_window_days }}</td>
                                            <td class="px-3 py-2">
                                                @if ($inflow->source_schedule_id !== null)
                                                    <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                                        {{ __('admin.inventory.source_schedule') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                        {{ __('admin.inventory.source_manual') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                <form
                                                    method="POST"
                                                    action="{{ route('app.admin.inventory.inflows.destroy', $inflow) }}"
                                                    onsubmit="return confirm('{{ __('admin.inventory.confirm_delete_inflow') }}');"
                                                    class="inline"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="_redirect" value="{{ url()->full() }}" />
                                                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">
                                                        {{ __('admin.inventory.delete_inflow') }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </section>

            {{-- SECTION B — availability matrix --}}
            <section class="space-y-3" aria-labelledby="inventory-matrix-heading">
                <div>
                    <h2 id="inventory-matrix-heading" class="text-base font-semibold text-gray-900">
                        {{ __('admin.inventory.section_matrix') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.inventory.section_matrix_hint') }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        @if ($products->isEmpty())
                            <p class="p-6 text-sm text-gray-500">{{ __('admin.inventory.matrix_no_products') }}</p>
                        @else
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr class="text-left font-medium uppercase tracking-wide text-gray-500">
                                        <th class="sticky left-0 z-10 bg-gray-50 px-3 py-2">{{ __('admin.inventory.col_product') }}</th>
                                        @foreach ($dateColumns as $date)
                                            <th class="px-2 py-2 whitespace-nowrap text-center">
                                                <div>{{ $date->translatedFormat('D') }}</div>
                                                <div class="text-[10px] text-gray-500 normal-case">{{ $date->translatedFormat('d.m.') }}</div>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($products as $product)
                                        @php
                                            $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit() ?? 'piece';
                                            $factor = $product->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;
                                            $row = $availabilityMatrix[$product->id] ?? [];
                                        @endphp
                                        <tr>
                                            <td class="sticky left-0 z-10 bg-white px-3 py-2 align-top">
                                                <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                                <div class="mt-0.5 text-[10px] text-gray-500">
                                                    {{ \App\Enums\ProductUnit::displaySymbol($displayUnit) }}
                                                </div>
                                            </td>
                                            @foreach ($dateColumns as $date)
                                                @php
                                                    $iso = $date->toDateString();
                                                    $value = $row[$iso] ?? 0.0;
                                                    $display = $value / $factor;
                                                @endphp
                                                <td class="px-2 py-2 text-center tabular-nums {{ $value <= 0 ? 'bg-gray-50 text-gray-400' : '' }}">
                                                    @if ($value <= 0)
                                                        —
                                                    @else
                                                        {{ rtrim(rtrim(number_format($display, 2, ',', '.'), '0'), ',') }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </section>

            {{-- SECTION C — recurring inflows per product --}}
            <section class="space-y-3" aria-labelledby="inventory-schedules-heading">
                <div>
                    <h2 id="inventory-schedules-heading" class="text-base font-semibold text-gray-900">
                        {{ __('admin.inventory.section_schedules') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.inventory.section_schedules_hint') }}</p>
                </div>
                <div class="space-y-2">
                    @foreach ($products as $product)
                        @php
                            $schedules = $product->stockInflowSchedules->sortByDesc('starts_on')->values();
                        @endphp
                        <details class="rounded-lg border border-gray-200 bg-white shadow-sm">
                            <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                                <span>{{ $product->name }}</span>
                                <span class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                    @if ($schedules->isEmpty())
                                        {{ __('admin.inventory.no_schedules_for_product') }}
                                    @else
                                        {{ $schedules->count() }} ×
                                    @endif
                                </span>
                            </summary>
                            <div class="border-t border-gray-100 p-4 space-y-4">
                                @include('admin.inventory._schedules', ['product' => $product, 'schedules' => $schedules])
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- SECTION D — manual one-off inflow --}}
            <section class="space-y-3" aria-labelledby="inventory-manual-heading">
                <div>
                    <h2 id="inventory-manual-heading" class="text-base font-semibold text-gray-900">
                        {{ __('admin.inventory.section_manual') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.inventory.manual_intro') }}</p>
                </div>
                <form
                    method="POST"
                    action="{{ route('app.admin.inventory.inflows.store') }}"
                    class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm grid grid-cols-1 gap-3 sm:grid-cols-2"
                >
                    @csrf
                    <input type="hidden" name="_redirect" value="{{ url()->full() }}" />
                    <label class="text-xs text-gray-700">
                        {{ __('admin.inventory.manual_product') }}
                        <select name="product_id" required class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">—</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs text-gray-700">
                        {{ __('admin.inventory.manual_received_on') }}
                        <input type="date" name="received_on" required value="{{ $today }}" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                    </label>
                    <label class="text-xs text-gray-700">
                        {{ __('admin.inventory.manual_quantity') }}
                        <input type="number" name="quantity" required step="0.01" min="0.01" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                    </label>
                    <label class="text-xs text-gray-700">
                        {{ __('admin.inventory.manual_freshness') }}
                        <input type="number" name="freshness_window_days" min="1" max="60" placeholder="—" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        <span class="mt-1 block text-[11px] text-gray-500">{{ __('admin.inventory.manual_freshness_hint') }}</span>
                    </label>
                    <label class="text-xs text-gray-700 sm:col-span-2">
                        {{ __('admin.inventory.manual_notes') }}
                        <textarea name="notes" rows="2" maxlength="500" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"></textarea>
                    </label>
                    <div class="sm:col-span-2 flex justify-end">
                        <x-primary-button size="sm">{{ __('admin.inventory.manual_submit') }}</x-primary-button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-admin-layout>
