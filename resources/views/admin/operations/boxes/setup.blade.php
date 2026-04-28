@php
    $initialState = [
        'margin_rate' => $data['margin_rate'],
        'stock' => $data['stock'],
        'sizes' => [],
        'customers' => $customers ?? [],
        'egg_candidates' => $eggCandidates ?? [],
    ];
    $sizeMeta = [];
    foreach ($data['size_blocks'] as $block) {
        $sizeMeta[$block['box_size']] = [
            'n' => $block['n'],
            'ttc' => $block['ttc'],
            'target' => $block['target'],
            'max' => $block['max'],
        ];
        $initialState['sizes'][$block['box_size']] = array_map(fn ($line) => [
            'product_id' => $line['product_id'],
            'quantity' => $line['quantity'],
        ], $block['lines']);
    }
    $initialState['size_meta'] = $sizeMeta;
@endphp
<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_setup.setup_title', ['date' => $week->translatedFormat('d.m.Y')]) }}
            </h2>
            <div class="flex items-center gap-4">
                @if ($data['has_existing'] ?? false)
                    <a href="{{ route('app.admin.boxes.week.show', ['week' => $week->toDateString()]) }}" class="text-sm font-medium text-emerald-700 hover:underline">
                        {{ __('admin.box_setup.cta_view_boxes') }}
                    </a>
                @endif
                <a href="{{ route('app.admin.boxes.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                    {{ __('admin.box_setup.back_to_overview') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="mx-auto max-w-[1600px] space-y-4 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (empty($data['size_blocks']))
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                    {{ __('admin.box_setup.no_eligible_subs') }}
                </div>
            @elseif (empty($data['stock']))
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                    {{ __('admin.box_setup.no_stock_warning') }}
                    <a href="{{ route('app.admin.products.weekly.show', ['week' => $week->toDateString()]) }}" class="font-medium underline">
                        {{ __('admin.box_setup.goto_weekly_catalog') }}
                    </a>
                </div>
            @endif

            @if ($data['has_existing'] ?? false)
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ __('admin.box_setup.existing_distribution_notice') }}
                </div>
            @endif

            <div
                x-data="boxSetup(@js($initialState))"
                x-cloak
            >
                <form method="post" action="{{ route('app.admin.boxes.setup.apply', ['week' => $week->toDateString()]) }}">
                    @csrf
                    <input type="hidden" name="week_start_date" value="{{ $week->toDateString() }}" />

                    <div class="grid gap-4 lg:grid-cols-[minmax(280px,1fr)_3fr]">
                        {{-- Left panel: stock overview --}}
                        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    {{ __('admin.box_setup.stock_heading') }}
                                </h3>
                                <span
                                    class="text-xs font-semibold"
                                    :class="anyOver ? 'text-rose-700' : 'text-gray-500'"
                                >
                                    <template x-if="anyOver">
                                        <span>{{ __('admin.box_setup.over_planned_warning') }}</span>
                                    </template>
                                </span>
                            </div>
                            <div class="mt-3 max-h-[70vh] overflow-auto">
                                <table class="min-w-full text-xs">
                                    <thead class="sticky top-0 bg-white">
                                        <tr class="border-b border-gray-200 text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                            <th class="py-1.5">{{ __('admin.box_setup.col_product') }}</th>
                                            <th class="py-1.5 text-right">{{ __('admin.box_setup.col_planned') }}</th>
                                            <th class="py-1.5 text-right">{{ __('admin.box_setup.col_used') }}</th>
                                            <th class="py-1.5 text-right">{{ __('admin.box_setup.col_remaining') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="row in stockRows" :key="row.product_id">
                                            <tr :class="row.remaining < 0 ? 'bg-rose-50' : (row.missing_stock ? 'bg-amber-50/50' : '')">
                                                <td class="py-1 pr-2">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="truncate font-medium text-gray-900" x-text="row.name"></span>
                                                        <template x-if="row.missing_stock">
                                                            <span class="rounded-full border border-amber-300 bg-amber-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-800" x-text="stockOutOfWeekLabel"></span>
                                                        </template>
                                                    </div>
                                                    <div class="text-[10px] text-gray-500">
                                                        <span x-text="row.unit"></span>
                                                        ·
                                                        <span x-text="formatEur(row.unit_price)"></span>
                                                    </div>
                                                </td>
                                                <td class="py-1 text-right tabular-nums text-gray-700" x-text="formatNumber(row.planned)"></td>
                                                <td class="py-1 text-right tabular-nums" :class="row.remaining < 0 ? 'font-semibold text-rose-700' : 'text-gray-700'" x-text="formatNumber(row.used_total)"></td>
                                                <td class="py-1 text-right tabular-nums" :class="row.remaining < 0 ? 'font-semibold text-rose-700' : 'text-gray-700'" x-text="formatNumber(row.remaining)"></td>
                                            </tr>
                                        </template>
                                        <template x-if="stockRows.length === 0">
                                            <tr>
                                                <td colspan="4" class="py-4 text-center text-gray-500">{{ __('admin.box_setup.stock_empty') }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Right panel: size cards --}}
                        <div class="grid gap-4" :class="sizes.length === 1 ? 'grid-cols-1' : (sizes.length === 2 ? 'grid-cols-1 md:grid-cols-2' : 'grid-cols-1 md:grid-cols-2 xl:grid-cols-3')">
                            <template x-for="size in sizes" :key="size.key">
                                <div class="flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-900">
                                                <span x-text="sizeLabel(size.key)"></span>
                                            </h3>
                                            <p class="text-xs text-gray-500">
                                                <span x-text="size.meta.n"></span>
                                                {{ __('admin.box_setup.boxes_suffix') }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-[11px] uppercase tracking-wide text-gray-500">
                                                {{ __('admin.box_setup.retail_sum') }}
                                            </p>
                                            <p class="text-sm font-semibold tabular-nums" :class="retailClass(size)">
                                                <span x-text="formatEur(retailSum(size))"></span>
                                            </p>
                                            <p
                                                class="text-[11px] text-gray-500"
                                                :title="{{ json_encode(__('admin.box_setup.target_max_explanation')) }}"
                                            >
                                                {{ __('admin.box_setup.target_short') }}:
                                                <span x-text="formatEur(size.meta.target)"></span>
                                                /
                                                {{ __('admin.box_setup.max_short') }}:
                                                <span x-text="formatEur(size.meta.max)"></span>
                                                <span class="ml-1 cursor-help text-gray-400" aria-hidden="true">ⓘ</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="relative mt-2 h-1.5 w-full rounded-full bg-gray-100">
                                        <div
                                            class="h-1.5 rounded-full"
                                            :class="progressClass(size)"
                                            :style="'width:'+ progressWidth(size) +'%'"
                                        ></div>
                                        <div
                                            class="absolute -top-0.5 h-2.5 w-0.5 bg-gray-500"
                                            :style="'left:'+ targetMarkerPct(size) +'%'"
                                            :title="{{ json_encode(__('admin.box_setup.target_short')) }} +': '+ formatEur(size.meta.target)"
                                            aria-hidden="true"
                                        ></div>
                                    </div>

                                    <ul class="mt-3 flex-1 divide-y divide-gray-100 text-xs">
                                        <template x-for="(line, idx) in size.lines" :key="size.key+'-'+idx">
                                            <li class="flex items-center gap-2 py-1.5">
                                                <input
                                                    type="hidden"
                                                    :name="'sizes['+size.key+']['+idx+'][product_id]'"
                                                    :value="line.product_id"
                                                />
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate font-medium text-gray-900" x-text="productName(line.product_id)"></div>
                                                    <div class="text-[11px] text-gray-500">
                                                        <span x-text="productUnit(line.product_id)"></span>
                                                        ·
                                                        <span x-text="formatEur(productPrice(line.product_id))"></span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <button
                                                        type="button"
                                                        class="rounded border border-gray-200 px-1.5 text-gray-600 hover:bg-gray-50"
                                                        @click="decQty(size.key, idx)"
                                                        aria-label="{{ __('admin.box_setup.decrease') }}"
                                                    >−</button>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        step="1"
                                                        class="w-14 rounded border-gray-300 text-center text-xs tabular-nums shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                                        :name="'sizes['+size.key+']['+idx+'][quantity]'"
                                                        x-model.number="line.quantity"
                                                    />
                                                    <button
                                                        type="button"
                                                        class="rounded border border-gray-200 px-1.5 text-gray-600 hover:bg-gray-50"
                                                        @click="incQty(size.key, idx)"
                                                        aria-label="{{ __('admin.box_setup.increase') }}"
                                                    >+</button>
                                                    <button
                                                        type="button"
                                                        class="ml-1 text-rose-600 hover:text-rose-800"
                                                        @click="removeLine(size.key, idx)"
                                                        aria-label="{{ __('admin.box_setup.remove_line') }}"
                                                    >×</button>
                                                </div>
                                            </li>
                                        </template>
                                        <template x-if="size.lines.length === 0">
                                            <li class="py-2 text-center text-gray-500">{{ __('admin.box_setup.size_lines_empty') }}</li>
                                        </template>
                                    </ul>

                                    <div class="mt-3 flex items-center gap-2">
                                        <select
                                            class="min-w-0 flex-1 rounded border-gray-300 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                            x-model="size.addProductId"
                                        >
                                            <option value="">{{ __('admin.box_setup.add_placeholder') }}</option>
                                            <template x-for="row in addableOptions(size)" :key="'add-'+size.key+'-'+row.product_id">
                                                <option :value="row.product_id" x-text="row.name + ' (' + formatEur(row.unit_price) + ')'"></option>
                                            </template>
                                        </select>
                                        <button
                                            type="button"
                                            class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="!size.addProductId"
                                            @click="addLine(size.key)"
                                        >
                                            {{ __('admin.box_setup.add_line') }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Per-customer panel: each row is the customer's resolved
                         box composition (size template − allergen-conflicting
                         items + auto-added eggs). Flagged customers (allergy
                         conflict, eggs missing, freeform note) must be OK'd or
                         adjusted before save unlocks. --}}
                    <template x-if="customers.length > 0">
                        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.per_customer_heading') }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.box_setup.per_customer_hint') }}</p>
                                </div>
                                <template x-if="anyCustomerBlocking()">
                                    <span class="inline-flex shrink-0 items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-900">
                                        {{ __('admin.box_setup.per_customer_blocking_pill') }}
                                    </span>
                                </template>
                            </div>

                            {{-- Aggregate total: sum of all customer boxes vs. recommended (target)
                                 vs. max (sales-price). Same green/amber/rose semantics as the
                                 size-template progress bars. --}}
                            <div class="mt-4 rounded-md border border-gray-100 bg-gray-50 p-3">
                                <div class="flex flex-wrap items-baseline justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.totals_heading') }}</p>
                                        <p class="mt-1 text-lg font-semibold tabular-nums" :class="customersTotalClass()">
                                            <span x-text="formatEur(customersTotal())"></span>
                                        </p>
                                    </div>
                                    <div class="text-right text-[11px] text-gray-600">
                                        <div>{{ __('admin.box_setup.totals_target_label') }}: <span class="tabular-nums" x-text="formatEur(customersTotalTarget())"></span></div>
                                        <div>{{ __('admin.box_setup.totals_max_label') }}: <span class="tabular-nums" x-text="formatEur(customersTotalMax())"></span></div>
                                    </div>
                                </div>
                                <div class="relative mt-3 h-1.5 w-full rounded-full bg-gray-200">
                                    <div
                                        class="h-1.5 rounded-full"
                                        :class="customersTotalProgressClass()"
                                        :style="'width:' + Math.max(0, Math.min(100, customersTotalMax() > 0 ? (customersTotal() / customersTotalMax()) * 100 : 0)) + '%'"
                                    ></div>
                                    <div
                                        class="absolute -top-0.5 h-2.5 w-0.5 bg-gray-500"
                                        :style="'left:' + Math.max(0, Math.min(100, customersTotalMax() > 0 ? (customersTotalTarget() / customersTotalMax()) * 100 : 0)) + '%'"
                                        aria-hidden="true"
                                    ></div>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <template x-for="customer in customers" :key="customer.user_id">
                                    <div :class="['rounded border', customerCardClass(customer)]">
                                        {{-- OK flag is submitted as customer_ok[user_id]=1 when checked. --}}
                                        <template x-if="customer.ok">
                                            <input type="hidden" :name="'customer_ok['+customer.user_id+']'" value="1" />
                                        </template>
                                        {{-- Row header with flags + OK toggle. --}}
                                        <div class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center gap-1 text-sm font-semibold text-gray-900 hover:text-emerald-700"
                                                        @click="customer.expanded = !customer.expanded"
                                                    >
                                                        <svg class="h-3 w-3 transition-transform" :class="customer.expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                                        <span x-text="customer.name"></span>
                                                    </button>
                                                    <span class="text-xs text-gray-500 break-all" x-text="customer.email"></span>
                                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-gray-700" x-text="sizeLabelByKey(customer.box_size)"></span>
                                                    <span class="inline-flex items-center gap-1.5 text-xs tabular-nums" :class="customerValueClass(customer)">
                                                        <span class="inline-block h-2 w-2 rounded-full" :class="customerValueDotClass(customer)" aria-hidden="true"></span>
                                                        <span x-text="formatEur(customerSum(customer))" class="font-semibold"></span>
                                                        <span class="text-[10px] text-gray-500" x-text="'/ ' + formatEur(customerMax(customer))"></span>
                                                    </span>
                                                </div>

                                                {{-- Preference + flag badges. --}}
                                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                    <template x-if="customer.veg_only">
                                                        <span class="inline-flex items-center rounded-full bg-chapter-produce/10 px-2 py-0.5 text-[11px] font-semibold text-chapter-produce">{{ __('admin.user_detail.badge_veg_only') }}</span>
                                                    </template>
                                                    <template x-if="customer.eggs">
                                                        <span class="inline-flex items-center rounded-full bg-chapter-eggdairy/10 px-2 py-0.5 text-[11px] font-semibold text-chapter-eggdairy">{{ __('admin.user_detail.badge_eggs_addon') }}</span>
                                                    </template>
                                                    <template x-for="allergen in customer.allergies" :key="allergen">
                                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-800" x-text="allergen"></span>
                                                    </template>
                                                    <template x-for="flag in customerLiveFlags(customer)" :key="flag">
                                                        <span class="inline-flex items-center rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-white" x-text="flagLabel(flag)"></span>
                                                    </template>
                                                </div>

                                                {{-- Freeform allergies note. --}}
                                                <template x-if="customer.note">
                                                    <p class="mt-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-900">
                                                        <span class="font-semibold">{{ __('admin.user_detail.field_allergies_note') }}:</span>
                                                        <span x-text="customer.note"></span>
                                                    </p>
                                                </template>
                                            </div>

                                            {{-- OK toggle (only renders when flagged). --}}
                                            <template x-if="customerFlagged(customer)">
                                                <label class="inline-flex shrink-0 items-center gap-2 rounded border border-emerald-300 bg-white px-3 py-1.5 text-xs font-medium text-emerald-800 cursor-pointer hover:bg-emerald-50">
                                                    <input type="checkbox" x-model="customer.ok" class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500" />
                                                    <span x-text="customer.ok ? @js(__('admin.box_setup.per_customer_ok_done')) : @js(__('admin.box_setup.per_customer_ok_label'))"></span>
                                                </label>
                                            </template>
                                        </div>

                                        {{-- Expanded composition. --}}
                                        <div x-show="customer.expanded" class="border-t border-gray-100 bg-white px-3 py-3" x-cloak>
                                            <template x-if="customer.removed_allergen_items.length > 0">
                                                <div class="mb-3 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                                                    <p class="font-semibold">{{ __('admin.box_setup.per_customer_removed_for_allergy') }}</p>
                                                    <ul class="mt-1 list-inside list-disc">
                                                        <template x-for="r in customer.removed_allergen_items" :key="r.product_id">
                                                            <li><span x-text="r.product_name"></span> — <span x-text="r.conflicts.join(', ')"></span></li>
                                                        </template>
                                                    </ul>
                                                    <p class="mt-1 text-[11px]">{{ __('admin.box_setup.per_customer_removed_hint') }}</p>
                                                </div>
                                            </template>
                                            <template x-if="customer.eggs_missing">
                                                <div class="mb-3 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                                                    <p>{{ __('admin.box_setup.per_customer_eggs_missing_hint') }}</p>
                                                </div>
                                            </template>
                                            <template x-if="customer.eggs_added">
                                                <div class="mb-3 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                                                    {{ __('admin.box_setup.per_customer_eggs_auto_added') }}
                                                </div>
                                            </template>

                                            <table class="min-w-full text-xs">
                                                <thead>
                                                    <tr class="text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                                        <th class="py-1 pe-2 w-6"></th>
                                                        <th class="py-1 pe-2">{{ __('admin.box_setup.col_product') }}</th>
                                                        <th class="py-1 pe-2">{{ __('admin.box_setup.col_allergens') }}</th>
                                                        <th class="py-1 pe-2">{{ __('admin.box_setup.col_unit_price') }}</th>
                                                        <th class="py-1 pe-2">{{ __('admin.box_setup.col_quantity') }}</th>
                                                        <th class="py-1 ps-2 text-right">{{ __('admin.box_setup.col_actions') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                    <template x-for="(line, idx) in customer.lines" :key="customer.user_id + '-reg-' + idx + '-' + line.product_id">
                                                        <tr :class="!productIsSafe(customer, line.product_id) ? 'bg-rose-50' : ''">
                                                            <td class="py-1 pe-2 align-top">
                                                                <template x-if="productIsSafe(customer, line.product_id)">
                                                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" :title="@js(__('admin.box_setup.allergen_safe_aria'))" aria-label="{{ __('admin.box_setup.allergen_safe_aria') }}">
                                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                                    </span>
                                                                </template>
                                                                <template x-if="!productIsSafe(customer, line.product_id)">
                                                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-100 text-rose-700" :title="@js(__('admin.box_setup.allergen_unsafe_aria'))" aria-label="{{ __('admin.box_setup.allergen_unsafe_aria') }}">
                                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M6 18L18 6"/></svg>
                                                                    </span>
                                                                </template>
                                                            </td>
                                                            <td class="py-1 pe-2">
                                                                <input type="hidden" :name="'customers['+customer.user_id+']['+idx+'][product_id]'" :value="line.product_id" />
                                                                <span x-text="productNameAny(line.product_id)" :class="productIsSafe(customer, line.product_id) ? 'font-medium text-gray-900' : 'font-semibold text-rose-900'"></span>
                                                                <span x-text="productUnit(line.product_id)" class="ms-1 text-[11px] text-gray-500"></span>
                                                            </td>
                                                            <td class="py-1 pe-2 text-[11px]">
                                                                <template x-if="allergensFor(line.product_id).length > 0">
                                                                    <div class="flex flex-wrap gap-1">
                                                                        <template x-for="a in allergensFor(line.product_id)" :key="line.product_id + '-' + a">
                                                                            <span :class="customer.allergies.includes(String(a)) ? 'bg-rose-200 text-rose-900' : 'bg-gray-100 text-gray-600'" class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider" x-text="a"></span>
                                                                        </template>
                                                                    </div>
                                                                </template>
                                                                <template x-if="allergensFor(line.product_id).length === 0">
                                                                    <span class="text-gray-400">—</span>
                                                                </template>
                                                            </td>
                                                            <td class="py-1 pe-2 tabular-nums text-gray-700" x-text="formatEur(productPriceAny(line.product_id))"></td>
                                                            <td class="py-1 pe-2">
                                                                <div class="flex items-center gap-1">
                                                                    <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="customerDecQty(customer, idx)">−</button>
                                                                    <input type="number" min="1" step="1" class="w-12 rounded border-gray-300 text-center text-xs tabular-nums shadow-sm" :name="'customers['+customer.user_id+']['+idx+'][quantity]'" x-model.number="line.quantity" />
                                                                    <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="customerIncQty(customer, idx)">+</button>
                                                                </div>
                                                            </td>
                                                            <td class="py-1 ps-2 text-right">
                                                                <button type="button" class="text-rose-600 hover:text-rose-800" @click="customerRemoveLine(customer, idx)">×</button>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                    <template x-if="customer.lines.length === 0">
                                                        <tr><td colspan="6" class="py-2 text-center text-gray-500">{{ __('admin.box_setup.per_customer_no_lines') }}</td></tr>
                                                    </template>
                                                </tbody>
                                                <tfoot>
                                                    <tr class="text-xs">
                                                        <td colspan="4" class="py-2 text-gray-600 font-semibold">{{ __('admin.box_setup.per_customer_summe_label') }}</td>
                                                        <td class="py-2 tabular-nums font-semibold" :class="customerValueClass(customer)" x-text="formatEur(customerSum(customer)) + ' / ' + formatEur(customerMax(customer))"></td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>

                                            <div class="mt-3 flex items-center gap-2">
                                                <select class="min-w-0 flex-1 rounded border-gray-300 text-xs shadow-sm" x-model="customer.addProductId">
                                                    <option value="">— {{ __('admin.box_setup.add_product_placeholder') }} —</option>
                                                    <template x-for="opt in customerAddableOptions(customer)" :key="opt.product_id">
                                                        <option :value="opt.product_id" x-text="(productIsSafe(customer, opt.product_id) ? '✓ ' : '✗ ') + opt.name"></option>
                                                    </template>
                                                </select>
                                                <button type="button" class="rounded bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800" @click="customerAddLine(customer)">{{ __('admin.box_setup.add_button') }}</button>
                                            </div>

                                            {{-- Eggs panel: separate slot for is_egg_addon products. --}}
                                            <template x-if="customer.eggs">
                                                <div class="mt-5 border-t-2 border-dashed border-amber-300 pt-4">
                                                    <h4 class="text-xs font-semibold uppercase tracking-wide text-chapter-eggdairy">{{ __('admin.box_setup.eggs_panel_heading') }}</h4>
                                                    <p class="mt-1 text-[11px] text-gray-500" x-text="@js(__('admin.box_setup.eggs_panel_hint'))"></p>

                                                    <template x-if="eggCandidates.length === 0">
                                                        <p class="mt-3 rounded border border-rose-200 bg-rose-50 px-2 py-1.5 text-[11px] text-rose-900">{{ __('admin.box_setup.eggs_panel_no_candidates') }}</p>
                                                    </template>

                                                    <template x-if="customer.eggsLines.length > 0">
                                                        <table class="mt-3 min-w-full text-xs">
                                                            <tbody class="divide-y divide-gray-100">
                                                                <template x-for="(line, idx) in customer.eggsLines" :key="customer.user_id + '-eggs-' + idx + '-' + line.product_id">
                                                                    <tr :class="!productIsSafe(customer, line.product_id) ? 'bg-rose-50' : ''">
                                                                        <td class="py-1 pe-2 align-top w-6">
                                                                            <template x-if="productIsSafe(customer, line.product_id)">
                                                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>
                                                                            </template>
                                                                            <template x-if="!productIsSafe(customer, line.product_id)">
                                                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-100 text-rose-700"><svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M6 18L18 6"/></svg></span>
                                                                            </template>
                                                                        </td>
                                                                        <td class="py-1 pe-2">
                                                                            <input type="hidden" :name="'customers['+customer.user_id+'][eggs_'+idx+'][product_id]'" :value="line.product_id" />
                                                                            <span x-text="productNameAny(line.product_id)" class="font-medium text-gray-900"></span>
                                                                            <template x-if="(eggCandidates.find(e => e.product_id === line.product_id) || {}).producer_name">
                                                                                <span class="ms-1 text-[11px] italic text-gray-500" x-text="'· ' + (eggCandidates.find(e => e.product_id === line.product_id) || {}).producer_name"></span>
                                                                            </template>
                                                                        </td>
                                                                        <td class="py-1 pe-2 tabular-nums text-gray-700" x-text="formatEur(productPriceAny(line.product_id))"></td>
                                                                        <td class="py-1 pe-2">
                                                                            <div class="flex items-center gap-1">
                                                                                <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="customerDecEggsQty(customer, idx)">−</button>
                                                                                <input type="number" min="1" step="1" class="w-12 rounded border-gray-300 text-center text-xs tabular-nums shadow-sm" :name="'customers['+customer.user_id+'][eggs_'+idx+'][quantity]'" x-model.number="line.quantity" />
                                                                                <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="customerIncEggsQty(customer, idx)">+</button>
                                                                            </div>
                                                                        </td>
                                                                        <td class="py-1 ps-2 text-right">
                                                                            <button type="button" class="text-rose-600 hover:text-rose-800" @click="customerRemoveEggsLine(customer, idx)">×</button>
                                                                        </td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                    </template>

                                                    <div class="mt-3 flex items-center gap-2">
                                                        <select class="min-w-0 flex-1 rounded border-amber-300 bg-amber-50 text-xs shadow-sm" x-model="customer.addEggsProductId" :disabled="eggsAddableOptions(customer).length === 0">
                                                            <option value="">— {{ __('admin.box_setup.eggs_panel_select_placeholder') }} —</option>
                                                            <template x-for="opt in eggsAddableOptions(customer)" :key="'add-egg-' + opt.product_id">
                                                                <option :value="opt.product_id" x-text="(productIsSafe(customer, opt.product_id) ? '✓ ' : '✗ ') + opt.name + (opt.producer_name ? ' (' + opt.producer_name + ')' : '')"></option>
                                                            </template>
                                                        </select>
                                                        <button type="button" class="rounded bg-chapter-eggdairy px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:bg-gray-300" :disabled="!customer.addEggsProductId" @click="customerAddEggs(customer)">{{ __('admin.box_setup.add_button') }}</button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="mt-6 flex items-center justify-between">
                        <p class="text-xs text-gray-500">
                            {{ __('admin.box_setup.save_hint') }}
                        </p>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300"
                            :disabled="anyCustomerBlocking()"
                            :title="anyCustomerBlocking() ? @js(__('admin.box_setup.save_blocked_title')) : ''"
                        >
                            <span x-show="!anyCustomerBlocking()">{{ __('admin.box_setup.save_button') }}</span>
                            <span x-show="anyCustomerBlocking()">{{ __('admin.box_setup.save_button_blocked') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('admin.operations.boxes.partials.box-setup-alpine')
</x-admin-layout>
