@php
    $initialState = [
        'margin_rate' => $data['margin_rate'],
        'stock' => $data['stock'],
        'sizes' => [],
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

                    {{-- Per-customer panel: surfaces each subscriber for this
                         week with their preferences as badges. Replaces the
                         "size template replicates blindly" assumption with
                         visible per-customer differences before save.
                         (W2-31 visibility piece — auto-projection lands in a
                         follow-up PR.) --}}
                    @if (! empty($subsBySize) && $subsBySize->isNotEmpty())
                        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.per_customer_heading') }}</h3>
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.box_setup.per_customer_hint') }}</p>

                            <div class="mt-4 space-y-5">
                                @foreach ($data['size_blocks'] as $block)
                                    @php
                                        $size = $block['box_size'];
                                        $subsForSize = $subsBySize->get($size, collect());
                                    @endphp
                                    @if ($subsForSize->isEmpty())
                                        @continue
                                    @endif
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">
                                            {{ __(ucfirst($size)) }} <span class="ms-1 text-gray-400">· {{ trans_choice('admin.box_setup.per_customer_subscriber_count', $subsForSize->count(), ['count' => $subsForSize->count()]) }}</span>
                                        </p>
                                        <ul class="mt-2 divide-y divide-gray-100 rounded border border-gray-100">
                                            @foreach ($subsForSize as $sub)
                                                @php
                                                    $eggs = (bool) data_get($sub->preferences, 'eggs_dairy_add_on', false);
                                                    $vegOnly = (bool) data_get($sub->preferences, 'veg_only', false);
                                                    $allergies = (array) ($sub->allergies ?? []);
                                                    $allergiesNote = (string) ($sub->allergies_note ?? '');
                                                @endphp
                                                <li class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <div class="min-w-0">
                                                        @if ($sub->user)
                                                            <a href="{{ route('app.admin.users.show', $sub->user) }}" class="text-sm font-medium text-gray-900 hover:text-emerald-700 hover:underline">
                                                                {{ $sub->user->name ?: $sub->user->email }}
                                                            </a>
                                                            @if ($sub->user->name)
                                                                <span class="ms-1 text-xs text-gray-500 break-all">· {{ $sub->user->email }}</span>
                                                            @endif
                                                        @else
                                                            <span class="text-sm text-gray-500">—</span>
                                                        @endif
                                                        @if ($allergiesNote !== '')
                                                            <p class="mt-1 text-xs text-amber-800"><span class="font-semibold">{{ __('admin.user_detail.field_allergies_note') }}:</span> {{ $allergiesNote }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        @if (! $eggs && ! $vegOnly && empty($allergies))
                                                            <span class="text-[11px] text-gray-400">{{ __('admin.box_setup.per_customer_standard') }}</span>
                                                        @endif
                                                        @if ($vegOnly)
                                                            <span class="inline-flex items-center rounded-full bg-chapter-produce/10 px-2 py-0.5 text-[11px] font-semibold text-chapter-produce">{{ __('admin.user_detail.badge_veg_only') }}</span>
                                                        @endif
                                                        @if ($eggs)
                                                            <span class="inline-flex items-center rounded-full bg-chapter-eggdairy/10 px-2 py-0.5 text-[11px] font-semibold text-chapter-eggdairy">{{ __('admin.user_detail.badge_eggs_addon') }}</span>
                                                        @endif
                                                        @foreach ($allergies as $allergen)
                                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-800">
                                                                {{ __('allergens.'.$allergen) }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 flex items-center justify-between">
                        <p class="text-xs text-gray-500">
                            {{ __('admin.box_setup.save_hint') }}
                        </p>
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                        >
                            {{ __('admin.box_setup.save_button') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('admin.operations.boxes.partials.box-setup-alpine')
</x-admin-layout>
