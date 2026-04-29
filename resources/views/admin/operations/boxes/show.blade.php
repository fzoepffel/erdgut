{{-- Single-box editor: live, reactive, single-save. Mirrors the per-customer
     card from Box-Setup (allergen ✓/✗, ± qty buttons, eggs panel, OK toggle,
     value chip with traffic light) so admins fine-tuning one saved box get
     the same affordances as during the weekly planner pass. --}}
<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_setup.box_detail_title', ['id' => $box->id]) }}
            </h2>
            <div class="flex items-center gap-4">
                @if ($boxSetupUrl)
                    <a href="{{ $boxSetupUrl }}" class="text-sm font-medium text-emerald-700 hover:underline">
                        {{ __('admin.box_setup.cta_open_in_box_setup') }}
                    </a>
                @endif
                <a href="{{ route('app.admin.boxes.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                    {{ __('admin.box_setup.back_to_overview') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($box->isTrial())
                <div class="rounded-md border border-violet-300 bg-violet-50 px-3 py-2 text-sm text-violet-900" data-testid="admin-box-trial-badge">
                    <span class="font-semibold">{{ __('admin.box_setup.kind_trial_badge') }}</span> —
                    <a class="underline" href="{{ route('app.admin.orders.show', $box->order_id) }}">{{ __('Open order #:id', ['id' => $box->order_id]) }}</a>
                </div>
            @endif

            {{-- Customer header — read-only context. The composition card below
                 is the working surface. --}}
            <section class="rounded-lg border border-gray-100 bg-white p-4 text-sm shadow-sm">
                <div class="flex flex-col gap-2 border-b border-gray-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        @if ($box->user)
                            <a href="{{ route('app.admin.users.show', $box->user) }}" class="font-semibold text-gray-900 hover:text-emerald-700 hover:underline">
                                {{ $box->user->name ?: $box->user->email }}
                            </a>
                            @if ($box->user->name)
                                <span class="ms-1 text-xs text-gray-500">· {{ $box->user->email }}</span>
                            @endif
                        @else
                            <span class="font-semibold text-gray-500">—</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :value="$box->status" type="box" />
                        @if ($boxSize !== '')
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-gray-700">{{ __('admin.box_setup.size_'.$boxSize) }}</span>
                        @endif
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.week_start') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $box->week_start_date?->translatedFormat('d.m.Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.modification_deadline') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ $box->modification_deadline_at?->translatedFormat('d.m.Y H:i') ?? '—' }}</dd>
                    </div>
                    @if ($okState['flag'])
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.ok_at_label') }}</dt>
                            <dd class="mt-1 text-emerald-800">
                                {{ $okState['at'] }}@if ($okState['by']) · {{ $okState['by'] }}@endif
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($eggsAddOn || $vegOnly || $allergiesSnapshot !== [])
                    <div class="mt-4 flex flex-wrap items-start gap-2">
                        @if ($vegOnly)
                            <span class="inline-flex items-center rounded-full bg-chapter-produce/10 px-3 py-1 text-xs font-semibold text-chapter-produce">{{ __('admin.user_detail.badge_veg_only') }}</span>
                        @endif
                        @if ($eggsAddOn)
                            <span class="inline-flex items-center rounded-full bg-chapter-eggdairy/10 px-3 py-1 text-xs font-semibold text-chapter-eggdairy">{{ __('admin.user_detail.badge_eggs_addon') }}</span>
                        @endif
                        @foreach ($allergiesSnapshot as $allergen)
                            <span class="inline-flex items-center rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-800">
                                {{ __('admin.user_detail.allergy_prefix') }}: {{ __('allergens.'.$allergen) }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if ($allergiesNote !== '')
                    <div class="mt-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-900">{{ __('admin.user_detail.field_allergies_note') }}</p>
                        <p class="mt-1">{{ $allergiesNote }}</p>
                    </div>
                @endif
            </section>

            @if (! $adminCanMutateItems)
                <div class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between" role="status">
                    <p>{{ __('admin.box_items.read_only_notice') }}</p>
                    <form method="POST" action="{{ route('app.admin.boxes.override-edit', $box) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        @csrf
                        <input type="text" name="reason" maxlength="500" placeholder="{{ __('admin.box_items.override_reason_placeholder') }}" class="rounded-md border-amber-300 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600" />
                        <input type="number" name="minutes" min="5" max="240" value="30" class="w-20 rounded-md border-amber-300 text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600" aria-label="{{ __('admin.box_items.override_minutes_label') }}" />
                        <button type="submit" class="inline-flex items-center justify-center rounded-md bg-amber-700 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-800" onclick="return confirm('{{ __('admin.box_items.override_confirm') }}')">
                            {{ __('admin.box_items.override_open') }}
                        </button>
                    </form>
                </div>
            @elseif ($box->hasActiveAdminEditOverride())
                <div class="rounded-md border border-amber-300 bg-amber-100 px-4 py-3 text-sm text-amber-900" role="status">
                    {{ __('admin.box_items.override_active', ['until' => $box->admin_edit_override_until?->translatedFormat('d.m.Y H:i')]) }}
                </div>
            @endif

            {{-- Composition + Wochen-Bestand share the same Alpine x-data so
                 the stock panel updates instantly as the admin retunes lines.
                 The single x-data also drives the table, the add dropdowns,
                 the eggs panel, the live total / progress bar, the OK
                 toggle, and the single Speichern button. --}}
            <div x-data="boxEditor(@js($initialState))" x-cloak class="space-y-4">

                {{-- Wochen-Bestand panel. Each row's Verteilt is the sum of
                     qty across all OTHER boxes for the week, plus the live
                     contribution of THIS box (regular + eggs). Rest goes
                     negative the moment the admin's edits push past planned. --}}
                @if ($box->week_start_date !== null)
                    <section class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.stock_heading') }}</h3>
                            <p class="text-xs font-semibold" :class="anyOver ? 'text-rose-700' : 'text-gray-500'">
                                <template x-if="anyOver">
                                    <span>{{ __('admin.box_setup.over_planned_warning') }}</span>
                                </template>
                                <template x-if="!anyOver">
                                    <span class="text-gray-500">{{ __('admin.box_items.weekly_stock_hint') }}</span>
                                </template>
                            </p>
                        </div>
                        <div class="mt-3 max-h-80 overflow-auto">
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
                                        <tr :class="row.remaining < 0 ? 'bg-rose-50' : ''">
                                            <td class="py-1 pr-2">
                                                <span class="truncate font-medium text-gray-900" x-text="row.name"></span>
                                                <div class="text-[10px] text-gray-500">
                                                    <span x-text="row.unit"></span>
                                                    ·
                                                    <span x-text="formatEur(row.unit_price)"></span>
                                                </div>
                                            </td>
                                            <td class="py-1 text-right tabular-nums text-gray-700" x-text="formatNumber(row.planned)"></td>
                                            <td class="py-1 text-right tabular-nums" :class="row.remaining < 0 ? 'font-semibold text-rose-700' : 'text-gray-700'" x-text="formatNumber(row.used_total)"></td>
                                            <td class="py-1 text-right tabular-nums font-semibold" :class="row.remaining < 0 ? 'text-rose-700' : 'text-emerald-700'" x-text="formatNumber(row.remaining)"></td>
                                        </tr>
                                    </template>
                                    <template x-if="stockRows.length === 0">
                                        <tr><td colspan="4" class="py-3 text-center text-gray-500">{{ __('admin.box_setup.stock_empty') }}</td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

            <section class="rounded-lg border border-gray-100 bg-white shadow-sm">
                <form method="POST" action="{{ route('app.admin.boxes.update', $box) }}">
                    @csrf
                    @method('PATCH')
                    {{-- OK flag is submitted as ok=1 when checked. --}}
                    <template x-if="ok">
                        <input type="hidden" name="ok" value="1" />
                    </template>
                    {{-- Hidden line inputs are emitted from the loops below. --}}

                    <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.box_setup.items_heading') }}</h3>
                            {{-- Live value chip with traffic-light coloring (same semantics as Box-Setup). --}}
                            <span class="inline-flex items-center gap-1.5 text-sm tabular-nums" :class="valueClass()">
                                <span class="inline-block h-2 w-2 rounded-full" :class="valueDotClass()" aria-hidden="true"></span>
                                <span class="font-semibold" x-text="formatEur(retailSum())"></span>
                                <span class="text-xs text-gray-500" x-text="'/ ' + formatEur(sizeMax)"></span>
                            </span>
                        </div>
                        @if ($adminCanMutateItems)
                            <label class="inline-flex shrink-0 items-center gap-2 rounded border border-emerald-300 bg-white px-3 py-1.5 text-xs font-medium text-emerald-800 cursor-pointer hover:bg-emerald-50">
                                <input type="checkbox" x-model="ok" class="rounded border-emerald-400 text-emerald-600 focus:ring-emerald-500" />
                                <span x-text="ok ? @js(__('admin.box_setup.per_customer_ok_done')) : @js(__('admin.box_setup.per_customer_ok_label'))"></span>
                            </label>
                        @endif
                    </div>

                    {{-- Live progress bar — fills toward target, dot at the recommended target. --}}
                    <div class="border-b border-gray-100 px-4 py-2">
                        <div class="relative h-1.5 w-full rounded-full bg-gray-200">
                            <div
                                class="h-1.5 rounded-full"
                                :class="progressClass()"
                                :style="'width:' + Math.max(0, Math.min(100, sizeMax > 0 ? (retailSum() / sizeMax) * 100 : 0)) + '%'"
                            ></div>
                            <div
                                class="absolute -top-0.5 h-2.5 w-0.5 bg-gray-500"
                                :style="'left:' + Math.max(0, Math.min(100, sizeMax > 0 ? (sizeTarget / sizeMax) * 100 : 0)) + '%'"
                                aria-hidden="true"
                                :title="@js(__('admin.box_setup.target_short')) + ': ' + formatEur(sizeTarget)"
                            ></div>
                        </div>
                        <p class="mt-1 flex justify-between text-[11px] text-gray-500">
                            <span>{{ __('admin.box_setup.target_short') }}: <span class="tabular-nums" x-text="formatEur(sizeTarget)"></span></span>
                            <span>{{ __('admin.box_setup.totals_max_label') }}: <span class="tabular-nums" x-text="formatEur(sizeMax)"></span></span>
                        </p>
                    </div>

                    {{-- Items table — regular lines. Allergen ✓/✗, name, unit price, ± qty, line total, is_extra toggle, remove. --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-xs">
                            <thead>
                                <tr class="text-left text-[11px] font-medium uppercase tracking-wide text-gray-500">
                                    <th class="w-6 px-3 py-2"></th>
                                    <th class="px-3 py-2">{{ __('admin.box_setup.col_product') }}</th>
                                    <th class="px-3 py-2">{{ __('admin.box_setup.col_allergens') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('admin.box_setup.col_unit_price') }}</th>
                                    <th class="px-3 py-2">{{ __('admin.box_setup.col_quantity') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('admin.box_items.col_line_total') }}</th>
                                    <th class="px-3 py-2 text-center">{{ __('admin.box_setup.col_extra') }}</th>
                                    @if ($adminCanMutateItems)
                                        <th class="px-3 py-2 text-right">{{ __('admin.box_items.col_actions') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(line, idx) in regularLines" :key="'reg-' + idx + '-' + line.product_id">
                                    <tr :class="!productIsSafe(line.product_id) ? 'bg-rose-50' : ''">
                                        <td class="px-3 py-2 align-top">
                                            <input type="hidden" :name="'lines[' + idx + '][product_id]'" :value="line.product_id" />
                                            <input type="hidden" :name="'lines[' + idx + '][quantity]'" :value="line.quantity" />
                                            <input type="hidden" :name="'lines[' + idx + '][is_extra]'" :value="line.is_extra ? '1' : '0'" />
                                            <template x-if="productIsSafe(line.product_id)">
                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" :title="@js(__('admin.box_setup.allergen_safe_aria'))">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                </span>
                                            </template>
                                            <template x-if="!productIsSafe(line.product_id)">
                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-100 text-rose-700" :title="conflictTitle(line.product_id)">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 6l12 12M6 18L18 6"/></svg>
                                                </span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span :class="productIsSafe(line.product_id) ? 'font-medium text-gray-900' : 'font-semibold text-rose-900'" x-text="productName(line.product_id)"></span>
                                            <span class="ms-1 text-[11px] text-gray-500" x-text="productUnit(line.product_id)"></span>
                                            <template x-if="!productIsSafe(line.product_id)">
                                                <p class="mt-0.5 text-[10px] font-medium text-rose-700" data-testid="admin-box-item-conflict-chip" x-text="@js(__('admin.box_items.conflict_with_label')) + ': ' + productConflicts(line.product_id).join(', ')"></p>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2 text-[11px]">
                                            <template x-if="allergensFor(line.product_id).length > 0">
                                                <div class="flex flex-wrap gap-1">
                                                    <template x-for="a in allergensFor(line.product_id)" :key="line.product_id + '-' + a">
                                                        <span :class="allergies.includes(String(a)) ? 'bg-rose-200 text-rose-900' : 'bg-gray-100 text-gray-600'" class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider" x-text="a"></span>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="allergensFor(line.product_id).length === 0">
                                                <span class="text-gray-400">—</span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-700" x-text="formatEur(productPrice(line.product_id))"></td>
                                        <td class="px-3 py-2">
                                            @if ($adminCanMutateItems)
                                                <div class="flex items-center gap-1">
                                                    <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="decQty(idx)">−</button>
                                                    <input type="number" min="1" step="1" class="w-12 rounded border-gray-300 text-center text-xs tabular-nums shadow-sm" x-model.number="line.quantity" />
                                                    <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="incQty(idx)">+</button>
                                                </div>
                                            @else
                                                <span class="tabular-nums text-gray-700" x-text="line.quantity"></span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-900" x-text="formatEur(productPrice(line.product_id) * Math.max(0, line.quantity || 0))"></td>
                                        <td class="px-3 py-2 text-center">
                                            @if ($adminCanMutateItems)
                                                <input type="checkbox" x-model="line.is_extra" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" :title="@js(__('admin.box_items.is_extra_hint'))" />
                                            @else
                                                <span x-text="line.is_extra ? @js(__('admin.box_setup.yes')) : @js(__('admin.box_setup.no'))"></span>
                                            @endif
                                        </td>
                                        @if ($adminCanMutateItems)
                                            <td class="px-3 py-2 text-right">
                                                <button type="button" class="text-rose-600 hover:text-rose-800" @click="removeLine(idx, productName(line.product_id))" :title="@js(__('admin.box_items.remove_line'))">×</button>
                                            </td>
                                        @endif
                                    </tr>
                                </template>
                                <template x-if="regularLines.length === 0">
                                    <tr><td :colspan="{{ $adminCanMutateItems ? 8 : 7 }}" class="px-3 py-3 text-center text-gray-500">{{ __('admin.box_setup.items_empty') }}</td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    @if ($adminCanMutateItems)
                        <div class="border-t border-gray-100 px-4 py-3">
                            <p class="mb-2 text-[11px] text-amber-800" data-testid="admin-box-force-include-hint">{{ __('admin.box_items.force_include_hint') }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <select class="min-w-0 flex-1 rounded border-gray-300 text-xs shadow-sm" x-model="addProductId">
                                    <option value="">— {{ __('admin.box_setup.add_product_placeholder') }} —</option>
                                    <template x-for="opt in addableOptions()" :key="'add-' + opt.product_id">
                                        <option :value="opt.product_id" x-text="(productIsSafe(opt.product_id) ? '✓ ' : '✗ ') + opt.name + ' · ' + formatEur(opt.unit_price) + (opt.unit ? ' / ' + opt.unit : '') + (opt.missing_stock ? ' · ' + @js(__('admin.box_setup.stock_out_of_week')) : '')"></option>
                                    </template>
                                </select>
                                <button type="button" class="rounded bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300" :disabled="!addProductId" @click="addLine()">{{ __('admin.box_setup.add_button') }}</button>
                            </div>
                        </div>
                    @endif

                    {{-- Eggs panel — separate slot for is_egg_addon products,
                         only rendered if the customer paid for the add-on. --}}
                    @if ($eggsAddOn)
                        <div class="border-t-2 border-dashed border-amber-300 px-4 py-3">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-chapter-eggdairy">{{ __('admin.box_setup.eggs_panel_heading') }}</h4>
                            <p class="mt-1 text-[11px] text-gray-500">{{ __('admin.box_setup.eggs_panel_hint') }}</p>

                            <template x-if="eggsLines.length === 0">
                                <p class="mt-3 rounded border border-rose-200 bg-rose-50 px-2 py-1.5 text-[11px] text-rose-900">{{ __('admin.box_setup.per_customer_eggs_missing_hint') }}</p>
                            </template>

                            <template x-if="eggsLines.length > 0">
                                <table class="mt-3 min-w-full text-xs">
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="(line, idx) in eggsLines" :key="'eggs-' + idx + '-' + line.product_id">
                                            <tr>
                                                <td class="w-6 px-3 py-2 align-top">
                                                    <input type="hidden" :name="'lines[eggs_' + idx + '][product_id]'" :value="line.product_id" />
                                                    <input type="hidden" :name="'lines[eggs_' + idx + '][quantity]'" :value="line.quantity" />
                                                    <input type="hidden" :name="'lines[eggs_' + idx + '][is_extra]'" :value="line.is_extra ? '1' : '0'" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span class="font-medium text-gray-900" x-text="productName(line.product_id)"></span>
                                                    <template x-if="(eggCandidates.find(e => e.product_id === line.product_id) || {}).producer_name">
                                                        <span class="ms-1 text-[11px] italic text-gray-500" x-text="'· ' + (eggCandidates.find(e => e.product_id === line.product_id) || {}).producer_name"></span>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2 text-right tabular-nums text-gray-700" x-text="formatEur(productPrice(line.product_id))"></td>
                                                <td class="px-3 py-2">
                                                    @if ($adminCanMutateItems)
                                                        <div class="flex items-center gap-1">
                                                            <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="decEggsQty(idx)">−</button>
                                                            <input type="number" min="1" step="1" class="w-12 rounded border-gray-300 text-center text-xs tabular-nums shadow-sm" x-model.number="line.quantity" />
                                                            <button type="button" class="rounded border border-gray-200 px-1 text-gray-600 hover:bg-gray-50" @click="incEggsQty(idx)">+</button>
                                                        </div>
                                                    @else
                                                        <span class="tabular-nums text-gray-700" x-text="line.quantity"></span>
                                                    @endif
                                                </td>
                                                @if ($adminCanMutateItems)
                                                    <td class="px-3 py-2 text-right">
                                                        <button type="button" class="text-rose-600 hover:text-rose-800" @click="removeEggsLine(idx, productName(line.product_id))" :title="@js(__('admin.box_items.remove_line'))">×</button>
                                                    </td>
                                                @endif
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>

                            @if ($adminCanMutateItems)
                                <div class="mt-3 flex items-center gap-2">
                                    <select class="min-w-0 flex-1 rounded border-amber-300 bg-amber-50 text-xs shadow-sm" x-model="addEggsProductId" :disabled="eggsAddableOptions().length === 0">
                                        <option value="">— {{ __('admin.box_setup.eggs_panel_select_placeholder') }} —</option>
                                        <template x-for="opt in eggsAddableOptions()" :key="'add-egg-' + opt.product_id">
                                            <option :value="opt.product_id" x-text="opt.name + (opt.producer_name ? ' (' + opt.producer_name + ')' : '')"></option>
                                        </template>
                                    </select>
                                    <button type="button" class="rounded bg-chapter-eggdairy px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:bg-gray-300" :disabled="!addEggsProductId" @click="addEggs()">{{ __('admin.box_setup.add_button') }}</button>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($adminCanMutateItems)
                        <div class="flex flex-col gap-2 border-t border-gray-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-gray-500">{{ __('admin.box_items.save_hint') }}</p>
                            <button type="submit" class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                {{ __('admin.box_items.save_box') }}
                            </button>
                        </div>
                    @endif
                </form>
            </section>
            </div>{{-- /x-data wrapper --}}

            {{-- Secondary admin tools, kept compact below the main editor. --}}
            <details class="rounded-lg border border-gray-100 bg-white p-3 text-sm shadow-sm">
                <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-gray-600">{{ __('admin.box_setup.advanced_tools_heading') }}</summary>
                <div class="mt-3 space-y-3">
                    <form method="post" action="{{ route('app.admin.boxes.meta.update', $box) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @method('patch')
                        <label class="flex flex-col gap-1">
                            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.box_setup.next_update_label') }}</span>
                            <input
                                type="datetime-local"
                                name="next_update_expected_at"
                                value="{{ old('next_update_expected_at', $box->next_update_expected_at?->format('Y-m-d\TH:i')) }}"
                                class="rounded-md border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            />
                        </label>
                        <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-500">
                            {{ __('admin.box_setup.next_update_save') }}
                        </button>
                        <p class="basis-full text-[11px] text-gray-500">{{ __('admin.box_setup.next_update_hint') }}</p>
                    </form>
                </div>
            </details>

            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.operations.emails.resend.section_heading') }}</h3>
                <p class="mt-1 text-xs text-gray-600">{{ __('admin.operations.emails.resend.section_intro') }}</p>
                @if (session('status') === 'admin-email-resent')
                    <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-900">{{ __('admin.operations.emails.resend.flash_resent') }}</p>
                @elseif (session('status') === 'admin-email-resend-not-found')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-900">{{ __('admin.operations.emails.resend.flash_not_found') }}</p>
                @endif
                <div class="mt-3 space-y-3">
                    <x-admin.email-resend context="box" :entity-id="$box->id" />
                    @if ($box->subscription_id)
                        <x-admin.email-resend context="subscription" :entity-id="$box->subscription_id" />
                    @endif
                </div>
            </div>

            <x-admin.audit-timeline entity-type="box" :entity-id="$box->id" />
        </div>
    </div>

    {{-- Alpine factory registered inline (the admin layout doesn't expose
         a @stack('scripts')) — keeps everything for this page in one place
         alongside the markup it drives. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('boxEditor', (initial) => ({
                allergies: (initial.allergies || []).map(String),
                eggsAddOn: !!initial.eggs_addon,
                boxSize: String(initial.box_size || ''),
                sizeMax: Number(initial.size_max || 0),
                sizeTarget: Number(initial.size_target || 0),
                marginRate: Number(initial.margin_rate || 0),
                eggsAddOnEur: Number(initial.eggs_add_on_eur || 0),
                catalogById: Object.fromEntries(
                    (initial.catalog || []).map(p => [Number(p.product_id), p])
                ),
                catalog: initial.catalog || [],
                eggCandidates: initial.egg_candidates || [],
                regularLines: (initial.regular_lines || []).map(l => ({
                    product_id: Number(l.product_id),
                    quantity: Number(l.quantity),
                    is_extra: !!l.is_extra,
                })),
                eggsLines: (initial.eggs_lines || []).map(l => ({
                    product_id: Number(l.product_id),
                    quantity: Number(l.quantity),
                    is_extra: !!l.is_extra,
                })),
                ok: !!initial.ok,
                addProductId: '',
                addEggsProductId: '',
                // Weekly stock for the in-page Wochen-Bestand panel. Each row
                // carries `planned` and `baseline_used` (= sum of qty in
                // OTHER boxes for the week, excluding this one). The
                // `stockRows` getter adds the live composition on top.
                weeklyStock: (initial.weekly_stock || []).map(r => ({
                    product_id: Number(r.product_id),
                    name: String(r.name),
                    unit: String(r.unit ?? ''),
                    unit_price: Number(r.unit_price ?? 0),
                    planned: Number(r.planned ?? 0),
                    baseline_used: Number(r.baseline_used ?? 0),
                })),

                // Lookup helpers (catalog is keyed by product_id; eggs candidates supplement
                // for products that may have been removed from the visible catalog).
                productInfo(pid) {
                    return this.catalogById[Number(pid)]
                        || this.eggCandidates.find(e => Number(e.product_id) === Number(pid))
                        || {};
                },
                productName(pid) { return this.productInfo(pid).name || '—'; },
                productUnit(pid) { return this.productInfo(pid).unit || ''; },
                productPrice(pid) { return Number(this.productInfo(pid).unit_price || 0); },
                allergensFor(pid) { return (this.productInfo(pid).allergens || []).map(String); },
                productConflicts(pid) {
                    const have = new Set(this.allergies.map(String));
                    return this.allergensFor(pid).filter(a => have.has(String(a)));
                },
                productIsSafe(pid) { return this.productConflicts(pid).length === 0; },
                conflictTitle(pid) {
                    const conflicts = this.productConflicts(pid);
                    return @js(__('admin.box_items.conflict_with_label')) + ': ' + conflicts.join(', ');
                },

                // Reactive totals.
                retailSum() {
                    const sum = (rows) => rows.reduce((acc, l) => acc + this.productPrice(l.product_id) * Math.max(0, l.quantity || 0), 0);
                    return sum(this.regularLines) + sum(this.eggsLines);
                },
                valueClass() {
                    const r = this.retailSum();
                    if (this.sizeMax > 0 && r > this.sizeMax + 0.005) return 'text-rose-700';
                    if (this.sizeTarget > 0 && r > this.sizeTarget + 0.005) return 'text-amber-700';
                    return 'text-emerald-700';
                },
                valueDotClass() {
                    const r = this.retailSum();
                    if (this.sizeMax > 0 && r > this.sizeMax + 0.005) return 'bg-rose-500';
                    if (this.sizeTarget > 0 && r > this.sizeTarget + 0.005) return 'bg-amber-400';
                    return 'bg-emerald-500';
                },
                progressClass() {
                    const r = this.retailSum();
                    if (this.sizeMax > 0 && r > this.sizeMax + 0.005) return 'bg-rose-500';
                    if (this.sizeTarget > 0 && r > this.sizeTarget + 0.005) return 'bg-amber-400';
                    return 'bg-emerald-500';
                },
                formatEur(value) {
                    return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(Number(value) || 0);
                },
                formatNumber(value) {
                    const n = Number(value) || 0;
                    return Number.isInteger(n) ? String(n) : n.toFixed(2);
                },

                // Live "Verteilt" calculation per product:
                //   baseline (= other boxes for the week) + this box's regular + eggs.
                // Same shape as Box-Setup's stockRows so admins recognise the panel.
                get stockRows() {
                    const used = {};
                    for (const line of this.regularLines) {
                        const pid = Number(line.product_id);
                        used[pid] = (used[pid] || 0) + Math.max(0, Number(line.quantity) || 0);
                    }
                    for (const line of this.eggsLines) {
                        const pid = Number(line.product_id);
                        used[pid] = (used[pid] || 0) + Math.max(0, Number(line.quantity) || 0);
                    }
                    return this.weeklyStock.map(row => {
                        const total = row.baseline_used + (used[row.product_id] || 0);
                        return {
                            ...row,
                            used_total: Math.round(total * 100) / 100,
                            remaining: Math.round((row.planned - total) * 100) / 100,
                        };
                    });
                },
                get anyOver() {
                    return this.stockRows.some(r => r.remaining < 0);
                },

                // Add / remove / qty helpers.
                addableOptions() {
                    // Exclude eggs (they live in the eggs panel) and products already in the regular list.
                    const used = new Set(this.regularLines.map(l => Number(l.product_id)));
                    return this.catalog.filter(p => !p.is_egg_addon && !used.has(Number(p.product_id)));
                },
                eggsAddableOptions() {
                    const used = new Set(this.eggsLines.map(l => Number(l.product_id)));
                    return this.eggCandidates.filter(e => !used.has(Number(e.product_id)));
                },
                addLine() {
                    if (!this.addProductId) return;
                    const pid = Number(this.addProductId);
                    if (!pid) return;
                    const existingIdx = this.regularLines.findIndex(l => Number(l.product_id) === pid);
                    if (existingIdx >= 0) {
                        this.regularLines[existingIdx].quantity += 1;
                    } else {
                        this.regularLines.push({ product_id: pid, quantity: 1, is_extra: false });
                    }
                    this.addProductId = '';
                },
                addEggs() {
                    if (!this.addEggsProductId) return;
                    const pid = Number(this.addEggsProductId);
                    if (!pid) return;
                    const existingIdx = this.eggsLines.findIndex(l => Number(l.product_id) === pid);
                    if (existingIdx >= 0) {
                        this.eggsLines[existingIdx].quantity += 1;
                    } else {
                        this.eggsLines.push({ product_id: pid, quantity: 1, is_extra: false });
                    }
                    this.addEggsProductId = '';
                },
                incQty(idx) {
                    this.regularLines[idx].quantity = Number(this.regularLines[idx].quantity || 0) + 1;
                },
                decQty(idx) {
                    const next = Number(this.regularLines[idx].quantity || 0) - 1;
                    this.regularLines[idx].quantity = next < 1 ? 1 : next;
                },
                incEggsQty(idx) {
                    this.eggsLines[idx].quantity = Number(this.eggsLines[idx].quantity || 0) + 1;
                },
                decEggsQty(idx) {
                    const next = Number(this.eggsLines[idx].quantity || 0) - 1;
                    this.eggsLines[idx].quantity = next < 1 ? 1 : next;
                },
                removeLine(idx, name) {
                    if (!confirm(@js(__('admin.box_items.remove_confirm')) + ' ' + (name || ''))) return;
                    this.regularLines.splice(idx, 1);
                },
                removeEggsLine(idx, name) {
                    if (!confirm(@js(__('admin.box_items.remove_eggs_confirm')) + ' ' + (name || ''))) return;
                    this.eggsLines.splice(idx, 1);
                },
            }));
        });
    </script>
</x-admin-layout>
