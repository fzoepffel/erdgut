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

    $weekdayLabels = [
        0 => __('admin.inflow_schedules.weekday_0'),
        1 => __('admin.inflow_schedules.weekday_1'),
        2 => __('admin.inflow_schedules.weekday_2'),
        3 => __('admin.inflow_schedules.weekday_3'),
        4 => __('admin.inflow_schedules.weekday_4'),
        5 => __('admin.inflow_schedules.weekday_5'),
        6 => __('admin.inflow_schedules.weekday_6'),
    ];

    // Build a JS-readable map of every product → its display unit /
    // freshness / id / name. Used by the shared modal Alpine state
    // to render context-specific labels without an extra request.
    $productContext = $products->mapWithKeys(function (\App\Models\Product $product) {
        $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit() ?? 'piece';

        return [$product->id => [
            'id' => $product->id,
            'name' => trim($product->name.($product->producer?->name ? ' · '.$product->producer->name : '')),
            'display_unit' => $displayUnit,
            'display_unit_symbol' => \App\Enums\ProductUnit::displaySymbol($displayUnit),
            'default_freshness' => (int) ($product->default_freshness_window_days ?? 1),
            'primary_producer_id' => $product->producer_id,
            'producers' => $product->producers->map(fn ($p) => [
                'id' => $p->id,
                'name' => (string) $p->name,
            ])->values()->all(),
            'schedule_count' => $product->stockInflowSchedules->count(),
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->localizedName(),
                'is_active' => (bool) $v->is_active,
            ])->values()->all(),
        ]];
    })->all();
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

    <div
        class="py-8 sm:py-12"
        x-data="{
            modal: null,
            ctx: null,
            open(name, productCtx) {
                this.ctx = productCtx;
                this.modal = name;
                document.body.style.overflow = 'hidden';
            },
            close() {
                this.modal = null;
                this.ctx = null;
                document.body.style.overflow = '';
            },
            products: @js($productContext),
            openFor(productId, name) {
                const ctx = this.products[productId];
                if (! ctx) return;
                this.open(name, ctx);
            },
        }"
        x-on:keydown.escape.window="close()"
    >
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

            {{-- MAIN: Availability matrix with per-row controls --}}
            <section class="space-y-3" aria-labelledby="inventory-matrix-heading">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="inventory-matrix-heading" class="text-base font-semibold text-gray-900">
                            {{ __('admin.inventory.section_matrix') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">{{ __('admin.inventory.section_matrix_hint') }} <span class="font-medium text-amber-700">{{ __('admin.inventory.b2b_stock_legend') }}</span></p>
                    </div>
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
                                        <th class="sticky right-0 z-10 bg-gray-50 px-3 py-2 text-right">{{ __('admin.inventory.col_actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($products as $product)
                                        @php
                                            $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit() ?? 'piece';
                                            $factor = $product->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;
                                            $row = $availabilityMatrix[$product->id] ?? [];
                                            $b2bRow = $b2bAvailabilityMatrix[$product->id] ?? [];
                                            $scheduleCount = $product->stockInflowSchedules->count();
                                        @endphp
                                        <tr>
                                            <td class="sticky left-0 z-10 bg-white px-3 py-2 align-top">
                                                <div class="font-medium text-gray-900">{{ $product->name }}</div>
                                                @if ($product->producer?->name)
                                                    <div class="text-[11px] text-gray-500">{{ $product->producer->name }}</div>
                                                @endif
                                                <div class="mt-0.5 text-[10px] text-gray-500">
                                                    {{ \App\Enums\ProductUnit::displaySymbol($displayUnit) }}
                                                    · {{ __('admin.inventory.freshness_short', ['days' => (int) ($product->default_freshness_window_days ?? 1)]) }}
                                                </div>
                                            </td>
                                            @foreach ($dateColumns as $date)
                                                @php
                                                    $iso = $date->toDateString();
                                                    $value = $row[$iso] ?? 0.0;
                                                    $display = $value / $factor;
                                                    // Stock only the trade audience can buy (b2b_only batches).
                                                    $b2bExtra = max(0.0, ($b2bRow[$iso] ?? 0.0) - $value);
                                                    $b2bDisplay = $b2bExtra / $factor;
                                                @endphp
                                                <td class="px-2 py-2 text-center tabular-nums {{ $value <= 0 && $b2bExtra <= 0 ? 'bg-gray-50 text-gray-400' : '' }}">
                                                    @if ($value <= 0 && $b2bExtra <= 0)
                                                        —
                                                    @elseif ($value <= 0)
                                                        <span class="font-medium text-amber-700" title="{{ __('admin.inventory.b2b_stock_title') }}" data-testid="inventory-b2b-cell-{{ $product->id }}-{{ $iso }}">{{ rtrim(rtrim(number_format($b2bDisplay, 2, ',', '.'), '0'), ',') }}</span>
                                                    @else
                                                        {{ rtrim(rtrim(number_format($display, 2, ',', '.'), '0'), ',') }}@if ($b2bExtra > 0)<span class="ml-0.5 text-[10px] font-medium text-amber-700" title="{{ __('admin.inventory.b2b_stock_title') }}">+{{ rtrim(rtrim(number_format($b2bDisplay, 2, ',', '.'), '0'), ',') }}</span>@endif
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="sticky right-0 z-10 bg-white px-3 py-2 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button
                                                        type="button"
                                                        x-on:click="openFor({{ $product->id }}, 'inflow')"
                                                        class="inline-flex items-center rounded border border-emerald-600 bg-white px-2 py-1 text-[11px] font-medium text-emerald-700 hover:bg-emerald-50"
                                                    >
                                                        + {{ __('admin.inventory.btn_inflow') }}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        x-on:click="openFor({{ $product->id }}, 'schedules')"
                                                        class="inline-flex items-center rounded border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                                        title="{{ __('admin.inventory.btn_schedules_title') }}"
                                                    >
                                                        ⟳
                                                        @if ($scheduleCount > 0)
                                                            <span class="ml-1 rounded-full bg-emerald-100 px-1.5 text-emerald-800">{{ $scheduleCount }}</span>
                                                        @endif
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Per-grade lanes: the product row above is the total; each
                                             grade (default + variants) gets its own indented lane. --}}
                                        @php $variantLanes = $variantMatrix[$product->id] ?? null; @endphp
                                        @if ($variantLanes !== null)
                                            @php
                                                $grades = collect([['key' => 0, 'label' => __('admin.inventory.variant_default'), 'active' => true]]);
                                                foreach ($product->variants as $variant) {
                                                    $grades->push(['key' => $variant->id, 'label' => $variant->localizedName(), 'active' => (bool) $variant->is_active]);
                                                }
                                            @endphp
                                            @foreach ($grades as $grade)
                                                @php $laneRow = $variantLanes[$grade['key']] ?? []; @endphp
                                                <tr class="bg-gray-50/40">
                                                    <td class="sticky left-0 z-10 bg-gray-50/40 px-3 py-1.5 align-top">
                                                        <div class="flex items-center gap-1.5 pl-3 text-xs {{ $grade['active'] ? 'text-gray-600' : 'text-gray-400 line-through' }}">
                                                            <span class="text-gray-300">&#x2514;</span>
                                                            <span>{{ $grade['label'] }}</span>
                                                        </div>
                                                    </td>
                                                    @foreach ($dateColumns as $date)
                                                        @php
                                                            $iso = $date->toDateString();
                                                            $value = $laneRow[$iso] ?? 0.0;
                                                            $display = $value / $factor;
                                                        @endphp
                                                        <td class="px-2 py-1.5 text-center text-xs tabular-nums {{ $value <= 0 ? 'text-gray-300' : 'text-gray-600' }}">
                                                            @if ($value <= 0)
                                                                &mdash;
                                                            @else
                                                                {{ rtrim(rtrim(number_format($display, 2, ',', '.'), '0'), ',') }}
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="sticky right-0 z-10 bg-gray-50/40 px-3 py-1.5"></td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Eingänge log — collapsible, secondary --}}
            <section class="space-y-3" aria-labelledby="inventory-inflows-heading">
                <details class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
                        <div>
                            <h2 id="inventory-inflows-heading" class="text-sm font-semibold text-gray-900">
                                {{ __('admin.inventory.section_inflows') }}
                            </h2>
                            <p class="mt-0.5 text-xs text-gray-500">{{ __('admin.inventory.section_inflows_hint') }}</p>
                        </div>
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            {{ $inflows->count() }}
                        </span>
                    </summary>
                    <div class="border-t border-gray-100 overflow-x-auto">
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
                                            <td class="px-3 py-2">
                                                {{ $product?->name ?? '—' }}
                                                @php $inflowProducer = $inflow->producer ?? $product?->producer; @endphp
                                                @if ($inflowProducer?->name)
                                                    <span @class([
                                                        'text-xs',
                                                        // Amber when this batch comes from a non-primary supplier.
                                                        'font-medium text-amber-700' => $inflow->producer_id !== null && $inflow->producer_id !== $product?->producer_id,
                                                        'text-gray-400' => $inflow->producer_id === null || $inflow->producer_id === $product?->producer_id,
                                                    ]) data-testid="inflow-producer-{{ $inflow->id }}">({{ $inflowProducer->name }})</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 tabular-nums">
                                                {{ rtrim(rtrim(number_format($displayQty, 2, ',', '.'), '0'), ',') }} {{ \App\Enums\ProductUnit::displaySymbol($displayUnit) }}
                                            </td>
                                            <td class="px-3 py-2 tabular-nums">{{ $inflow->freshness_window_days }}</td>
                                            <td class="px-3 py-2">
                                                <div class="flex flex-wrap items-center gap-1">
                                                    @if ($inflow->b2b_only)
                                                        <span class="inline-flex items-center rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">
                                                            {{ __('admin.inventory.b2b_only_badge') }}
                                                        </span>
                                                    @endif
                                                    @if ($inflow->source_schedule_id !== null)
                                                        <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                                            {{ __('admin.inventory.source_schedule') }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                            {{ __('admin.inventory.source_manual') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-3">
                                                    <button
                                                        type="button"
                                                        x-on:click="open('inflow-edit', {
                                                            id: {{ $inflow->id }},
                                                            product_name: @js(trim(($product?->name ?? '').($product?->producer?->name ? ' · '.$product->producer->name : ''))),
                                                            display_unit_symbol: @js(\App\Enums\ProductUnit::displaySymbol($displayUnit)),
                                                            received_on: @js((string) ($inflow->received_on?->toDateString() ?? '')),
                                                            quantity: {{ $displayQty }},
                                                            freshness_window_days: {{ (int) $inflow->freshness_window_days }},
                                                            b2b_only: @js((bool) $inflow->b2b_only),
                                                            notes: @js((string) ($inflow->notes ?? '')),
                                                            variant_id: @js($inflow->variant_id !== null ? (int) $inflow->variant_id : ''),
                                                            variants: @js($product?->variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->localizedName(), 'is_active' => (bool) $v->is_active])->values()->all() ?? []),
                                                            producer_id: @js($inflow->producer_id !== null ? (int) $inflow->producer_id : ($product?->producer_id ?? '')),
                                                            producers: @js($product?->producers->map(fn ($p) => ['id' => $p->id, 'name' => (string) $p->name])->values()->all() ?? []),
                                                            update_url: @js(route('app.admin.inventory.inflows.update', $inflow)),
                                                        })"
                                                        class="text-xs font-semibold text-emerald-700 hover:underline"
                                                    >
                                                        {{ __('admin.inventory.edit_inflow') }}
                                                    </button>
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
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </details>
            </section>

            {{-- MODAL: Manual inflow --}}
            <div
                x-show="modal === 'inflow'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                x-on:click.self="close()"
            >
                <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl" x-on:click.stop>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900" x-text="ctx?.name ? '{{ addslashes(__('admin.inventory.modal_inflow_title')) }}: ' + ctx.name : '{{ __('admin.inventory.modal_inflow_title') }}'"></h3>
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.inventory.manual_intro') }}</p>
                        </div>
                        <button type="button" x-on:click="close()" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>
                    <form
                        method="POST"
                        action="{{ route('app.admin.inventory.inflows.store') }}"
                        class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2"
                    >
                        @csrf
                        <input type="hidden" name="_redirect" value="{{ url()->full() }}" />
                        <input type="hidden" name="product_id" :value="ctx?.id" />
                        <label class="text-xs text-gray-700">
                            {{ __('admin.inventory.manual_received_on') }}
                            <input type="date" name="received_on" required value="{{ $today }}" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </label>
                        <label class="text-xs text-gray-700">
                            <span x-text="'{{ __('admin.inventory.manual_quantity') }}' + (ctx?.display_unit_symbol ? ' (' + ctx.display_unit_symbol + ')' : '')"></span>
                            <input type="number" name="quantity" required step="0.01" min="0.01" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2" x-show="(ctx?.variants?.length ?? 0) > 0" x-cloak>
                            {{ __('admin.inventory.manual_variant') }}
                            <select name="variant_id" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="">{{ __('admin.inventory.variant_default') }}</option>
                                <template x-for="v in (ctx?.variants ?? [])" :key="v.id">
                                    <option :value="v.id" x-text="v.name" :disabled="! v.is_active"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2" x-show="(ctx?.producers?.length ?? 0) > 1" x-cloak>
                            {{ __('admin.inventory.inflow_producer_label') }}
                            <select name="producer_id" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <template x-for="p in (ctx?.producers ?? [])" :key="p.id">
                                    <option :value="p.id" x-text="p.name" :selected="String(ctx?.primary_producer_id) === String(p.id)"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2">
                            {{ __('admin.inventory.manual_freshness') }}
                            <input type="number" name="freshness_window_days" min="1" max="60" :value="ctx?.default_freshness ?? 1" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            <span class="mt-1 block text-[11px] text-gray-500">{{ __('admin.inventory.manual_freshness_hint') }}</span>
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2">
                            {{ __('admin.inventory.manual_notes') }}
                            <textarea name="notes" rows="2" maxlength="500" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"></textarea>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-gray-700 sm:col-span-2">
                            <input type="hidden" name="b2b_only" value="0">
                            <input type="checkbox" name="b2b_only" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            <span>{{ __('admin.inventory.b2b_only_label') }}</span>
                        </label>
                        <div class="sm:col-span-2 flex justify-end gap-2">
                            <button type="button" x-on:click="close()" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                {{ __('admin.inventory.modal_cancel') }}
                            </button>
                            <x-primary-button size="sm">{{ __('admin.inventory.manual_submit') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- MODAL: Edit an existing inflow row --}}
            <div
                x-show="modal === 'inflow-edit'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                x-on:click.self="close()"
            >
                <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl" x-on:click.stop>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">
                                <span>{{ __('admin.inventory.modal_inflow_edit_title') }}</span>
                                <span x-show="ctx?.product_name" x-cloak>:&nbsp;<span x-text="ctx?.product_name"></span></span>
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.inventory.modal_inflow_edit_hint') }}</p>
                        </div>
                        <button type="button" x-on:click="close()" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>
                    <form
                        method="POST"
                        :action="ctx?.update_url"
                        class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_redirect" value="{{ url()->full() }}" />
                        <label class="text-xs text-gray-700">
                            {{ __('admin.inventory.manual_received_on') }}
                            <input type="date" name="received_on" required :value="ctx?.received_on" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </label>
                        <label class="text-xs text-gray-700">
                            <span x-text="'{{ __('admin.inventory.manual_quantity') }}' + (ctx?.display_unit_symbol ? ' (' + ctx.display_unit_symbol + ')' : '')"></span>
                            <input type="number" name="quantity" required step="0.01" min="0.01" :value="ctx?.quantity" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2" x-show="(ctx?.variants?.length ?? 0) > 0" x-cloak>
                            {{ __('admin.inventory.manual_variant') }}
                            <select name="variant_id" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="" :selected="! ctx?.variant_id">{{ __('admin.inventory.variant_default') }}</option>
                                <template x-for="v in (ctx?.variants ?? [])" :key="v.id">
                                    <option :value="v.id" x-text="v.name" :selected="String(ctx?.variant_id) === String(v.id)" :disabled="! v.is_active"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2" x-show="(ctx?.producers?.length ?? 0) > 1" x-cloak>
                            {{ __('admin.inventory.inflow_producer_label') }}
                            <select name="producer_id" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <template x-for="p in (ctx?.producers ?? [])" :key="p.id">
                                    <option :value="p.id" x-text="p.name" :selected="String(ctx?.producer_id) === String(p.id)"></option>
                                </template>
                            </select>
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2">
                            {{ __('admin.inventory.manual_freshness') }}
                            <input type="number" name="freshness_window_days" min="1" max="60" :value="ctx?.freshness_window_days" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            <span class="mt-1 block text-[11px] text-gray-500">{{ __('admin.inventory.manual_freshness_hint') }}</span>
                        </label>
                        <label class="text-xs text-gray-700 sm:col-span-2">
                            {{ __('admin.inventory.manual_notes') }}
                            <textarea name="notes" rows="2" maxlength="500" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" x-text="ctx?.notes ?? ''"></textarea>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-gray-700 sm:col-span-2">
                            <input type="hidden" name="b2b_only" value="0">
                            <input type="checkbox" name="b2b_only" value="1" :checked="ctx?.b2b_only" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            <span>{{ __('admin.inventory.b2b_only_label') }}</span>
                        </label>
                        <div class="sm:col-span-2 flex justify-end gap-2">
                            <button type="button" x-on:click="close()" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                {{ __('admin.inventory.modal_cancel') }}
                            </button>
                            <x-primary-button size="sm">{{ __('admin.inventory.edit_submit') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- MODAL: Recurring schedules — list existing + create new --}}
            <div
                x-show="modal === 'schedules'"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4"
                x-on:click.self="close()"
            >
                <div class="my-8 w-full max-w-3xl rounded-lg bg-white p-6 shadow-xl" x-on:click.stop>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900" x-text="ctx?.name ? '{{ addslashes(__('admin.inventory.modal_schedules_title')) }}: ' + ctx.name : '{{ __('admin.inventory.modal_schedules_title') }}'"></h3>
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.inflow_schedules.intro') }}</p>
                        </div>
                        <button type="button" x-on:click="close()" class="text-gray-400 hover:text-gray-600">&times;</button>
                    </div>

                    {{-- One sub-panel per product, swapped via Alpine x-show. --}}
                    @foreach ($products as $product)
                        @php
                            $schedules = $product->stockInflowSchedules->sortByDesc('starts_on')->values();
                            $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit() ?? 'piece';
                            $unitSymbol = \App\Enums\ProductUnit::displaySymbol($displayUnit);
                            $factor = $product->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;
                            $defaultFreshness = (int) ($product->default_freshness_window_days ?? 1);
                            $today = \Illuminate\Support\Carbon::now()->startOfDay();
                        @endphp
                        <div x-show="modal === 'schedules' && ctx?.id === {{ $product->id }}" x-cloak class="mt-4 space-y-4">
                            @if ($schedules->isEmpty())
                                <p class="rounded border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center text-xs text-gray-500">
                                    {{ __('admin.inventory.no_schedules_for_product') }}
                                </p>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead>
                                            <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_weekday') }}</th>
                                                <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_quantity') }}</th>
                                                <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_freshness') }}</th>
                                                <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_starts') }}</th>
                                                <th class="py-2 pe-4">{{ __('admin.inflow_schedules.col_ends') }}</th>
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
                                                        {{ rtrim(rtrim(number_format((float) $schedule->planned_quantity / $factor, 2, ',', '.'), '0'), ',') }} {{ $unitSymbol }}
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
                                                    <td class="py-3 pe-0 text-right">
                                                        @if ($active)
                                                            <div class="flex flex-col items-end gap-2">
                                                                <div class="flex gap-2">
                                                                    <button type="button" class="text-xs font-medium text-emerald-700 hover:underline" x-on:click="adjusting = ! adjusting; cancelling = false">
                                                                        {{ __('admin.inflow_schedules.action_adjust') }}
                                                                    </button>
                                                                    <button type="button" class="text-xs font-medium text-rose-700 hover:underline" x-on:click="cancelling = ! cancelling; adjusting = false">
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
                                                                        {{ __('admin.inflow_schedules.field_new_quantity') }} ({{ $unitSymbol }})
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

                            <details class="rounded border border-gray-200 bg-gray-50/50 p-4" open>
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
                                        {{ __('admin.inflow_schedules.field_quantity') }} ({{ $unitSymbol }})
                                        <input type="number" name="planned_quantity" step="0.01" min="0.01" required class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                                    </label>
                                    <label class="text-xs text-gray-700">
                                        {{ __('admin.inflow_schedules.field_freshness') }}
                                        <input type="number" name="freshness_window_days" min="1" max="60" value="{{ $defaultFreshness }}" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                                    </label>
                                    @if ($product->producers->count() > 1)
                                        <label class="text-xs text-gray-700">
                                            {{ __('admin.inventory.inflow_producer_label') }}
                                            <select name="producer_id" class="mt-1 block w-full rounded border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                                @foreach ($product->producers as $supplier)
                                                    <option value="{{ $supplier->id }}" @selected($supplier->id === $product->producer_id)>{{ $supplier->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
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
                                    <label class="flex items-center gap-2 text-xs text-gray-700 sm:col-span-2">
                                        <input type="hidden" name="b2b_only" value="0">
                                        <input type="checkbox" name="b2b_only" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                                        <span>{{ __('admin.inventory.b2b_only_label') }}</span>
                                    </label>
                                    <div class="sm:col-span-2 flex justify-end gap-2">
                                        <button type="button" x-on:click="close()" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            {{ __('admin.inventory.modal_cancel') }}
                                        </button>
                                        <x-primary-button size="sm">{{ __('admin.inflow_schedules.submit_create') }}</x-primary-button>
                                    </div>
                                </form>
                            </details>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
