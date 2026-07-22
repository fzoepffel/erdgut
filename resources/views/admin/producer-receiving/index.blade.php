<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('admin.producer_receiving.title') }}</h1>
                <span class="text-sm text-gray-500">{{ __('admin.producer_receiving.subtitle') }}</span>
            </div>
            {{-- Delivery-date window + customer type. Same semantics as the shop orders list. --}}
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.producer_receiving.filter_from') }}</label>
                    <input type="date" name="delivery_from" value="{{ $filter['delivery_from'] }}"
                           class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.producer_receiving.filter_to') }}</label>
                    <input type="date" name="delivery_to" value="{{ $filter['delivery_to'] }}"
                           class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.producer_receiving.filter_type') }}</label>
                    <select name="type" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="" @selected($filter['type'] === '')>{{ __('admin.producer_receiving.filter_type_all') }}</option>
                        <option value="b2c" @selected($filter['type'] === 'b2c')>{{ __('admin.producer_receiving.filter_type_b2c') }}</option>
                        <option value="b2b" @selected($filter['type'] === 'b2b')>{{ __('admin.producer_receiving.filter_type_b2b') }}</option>
                    </select>
                </div>
                <button type="submit"
                        class="inline-flex items-center rounded-sm bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700">
                    {{ __('admin.producer_receiving.filter_apply') }}
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10"
         x-data="producerReceiving({
            producers: @js($producers),
            toggleUrl: @js(route('app.admin.producer-orders.receipt.toggle')),
            receiveAllUrl: @js(route('app.admin.producer-orders.receive-all')),
            moveTargetsUrl: @js(route('app.admin.producer-orders.move-targets')),
            moveUrl: @js(route('app.admin.producer-orders.move')),
            resetMoveUrl: @js(route('app.admin.producer-orders.move.reset')),
            exportBase: @js(route('app.admin.shop-orders.farmer-export', array_filter([
                'delivery_from' => $filter['delivery_from'],
                'delivery_to' => $filter['delivery_to'],
                'type' => $filter['type'],
            ]))),
            filter: @js(array_filter([
                'delivery_from' => $filter['delivery_from'],
                'delivery_to' => $filter['delivery_to'],
                'type' => $filter['type'],
            ])),
            csrf: @js(csrf_token()),
         })">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">

            <p class="text-sm text-gray-600">{{ __('admin.producer_receiving.intro') }}</p>

            @if (count($producers) === 0)
                <div class="rounded-lg border border-gray-100 bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                    {{ __('admin.producer_receiving.no_orders') }}
                </div>
            @else
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {{-- Left: producer checklist --}}
                    <div class="lg:col-span-1">
                        <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                            <template x-for="p in producers" :key="p.id">
                                <button type="button"
                                        @click="selectedProducerId = p.id"
                                        class="flex w-full items-center justify-between gap-3 border-b border-gray-50 px-4 py-3 text-left transition last:border-b-0"
                                        :class="selectedProducerId === p.id ? 'bg-emerald-50' : 'hover:bg-gray-50'">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-gray-800" x-text="p.name"></span>
                                        <span class="mt-1 block h-1.5 overflow-hidden rounded-full bg-gray-100">
                                            <span class="block h-full rounded-full bg-emerald-500 transition-all"
                                                  :style="`width: ${p.total_count ? Math.round(p.received_count/p.total_count*100) : 0}%`"></span>
                                        </span>
                                    </span>
                                    <span class="shrink-0 font-mono text-xs"
                                          :class="p.done ? 'text-emerald-600' : 'text-gray-500'"
                                          x-text="p.received_count + ' / ' + p.total_count"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Right: selected producer's lines --}}
                    <div class="lg:col-span-2">
                        <template x-if="selected()">
                            <div class="rounded-lg border border-gray-100 bg-white shadow-sm">
                                <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="text-base font-semibold text-gray-800" x-text="selected().name"></h2>
                                        <p class="text-xs text-gray-500">
                                            <span x-text="selected().received_count + ' / ' + selected().total_count"></span>
                                            {{ __('admin.producer_receiving.received_suffix') }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a :href="exportHref(selected().id)"
                                           class="inline-flex items-center gap-1.5 rounded-sm border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                            {{ __('admin.producer_receiving.download_order') }}
                                        </a>
                                        <button type="button" @click="receiveAll(selected())"
                                                class="inline-flex items-center gap-1.5 rounded-sm bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                            {{ __('admin.producer_receiving.receive_all') }}
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <template x-for="row in selected().rows" :key="row.key">
                                        <div class="border-b border-gray-50 last:border-b-0">
                                            <div class="flex items-center gap-3 px-4 py-3 transition hover:bg-gray-50">
                                                <input type="checkbox" :checked="row.received"
                                                       @change="toggle(selected(), row)"
                                                       class="h-5 w-5 shrink-0 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                <span class="min-w-0 flex-1">
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="truncate text-sm font-medium text-gray-800" x-text="row.product_name"></span>
                                                        <span x-show="row.overridden"
                                                              class="inline-flex shrink-0 items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                                                            {{ __('admin.producer_receiving.moved_badge') }}
                                                        </span>
                                                    </span>
                                                    <span class="block text-xs text-gray-500">
                                                        <span x-show="row.variant_name" x-text="row.variant_name + ' · '"></span>
                                                        <span x-text="formatDate(row.date)"></span>
                                                    </span>
                                                    <span x-show="row.overridden && row.override"
                                                          class="mt-0.5 block text-xs text-amber-700"
                                                          x-text="movedFromLabel(row)"></span>
                                                    <span x-show="row.overridden && row.override && (row.override.price_differs || row.override.margin_negative)"
                                                          class="mt-0.5 block text-xs"
                                                          :class="row.override && row.override.margin_negative ? 'font-semibold text-rose-600' : 'text-amber-700'"
                                                          x-text="movedMarginLabel(row)"></span>
                                                </span>
                                                <span class="shrink-0 font-mono text-sm text-gray-700"
                                                      x-text="row.qty_display + ' ' + row.unit"></span>
                                                <div class="flex shrink-0 items-center gap-1">
                                                    <button type="button" x-show="row.movable && !row.overridden"
                                                            @click="openMove(row)"
                                                            class="inline-flex items-center gap-1 rounded-sm border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 transition hover:bg-amber-100">
                                                        {{ __('admin.producer_receiving.move_button') }}
                                                    </button>
                                                    <button type="button" x-show="row.overridden"
                                                            @click="resetMove(row)"
                                                            class="inline-flex items-center gap-1 rounded-sm border border-gray-300 bg-white px-2 py-1 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                                                        {{ __('admin.producer_receiving.move_reset') }}
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Inline move overlay: pick a farm (or look-alike product) that can cover the whole position. --}}
                                            <div x-show="openMoveKey === row.key" x-cloak
                                                 class="border-t border-gray-100 bg-gray-50 px-4 py-3">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <p class="text-xs font-semibold text-gray-600">{{ __('admin.producer_receiving.move_title') }}</p>
                                                    <button type="button" @click="closeMove()" class="text-xs text-gray-400 hover:text-gray-600">
                                                        {{ __('admin.producer_receiving.move_cancel') }}
                                                    </button>
                                                </div>
                                                <input type="text" x-model="moveSearch"
                                                       @input.debounce.300ms="searchMove(row)"
                                                       placeholder="{{ __('admin.producer_receiving.move_search_placeholder') }}"
                                                       class="mb-2 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                <p x-show="moveLoading" class="py-2 text-xs text-gray-400">{{ __('admin.producer_receiving.move_loading') }}</p>
                                                <p x-show="moveError" x-text="moveError" class="py-2 text-xs text-rose-600"></p>
                                                <p x-show="!moveLoading && !moveError && moveTargets.length === 0"
                                                   class="py-2 text-xs text-gray-400">{{ __('admin.producer_receiving.move_no_targets') }}</p>
                                                <ul class="max-h-64 space-y-1 overflow-y-auto" x-show="!moveLoading && moveTargets.length > 0">
                                                    <template x-for="t in moveTargets" :key="t.target_product_id + '-' + t.producer_id">
                                                        <li>
                                                            <button type="button" @click="applyMove(row, t)"
                                                                    class="flex w-full items-start justify-between gap-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-left transition hover:border-emerald-400 hover:bg-emerald-50">
                                                                <span class="min-w-0 flex-1">
                                                                    <span class="block truncate text-sm font-semibold text-gray-800" x-text="t.producer_name"></span>
                                                                    <span class="flex items-center gap-1.5">
                                                                        <span class="truncate text-xs text-gray-500" x-text="t.product_name"></span>
                                                                        <span x-show="!t.same_product"
                                                                              class="inline-flex shrink-0 items-center rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700">
                                                                            {{ __('admin.producer_receiving.move_different_product') }}
                                                                        </span>
                                                                    </span>
                                                                    {{-- Buying price + resulting margin (customer keeps the agreed price). --}}
                                                                    <span x-show="t.buying_price_label" class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                                                        <span class="text-gray-500">
                                                                            {{ __('admin.producer_receiving.move_buying_price') }}
                                                                            <span class="font-medium text-gray-700" x-text="t.buying_price_label"></span>
                                                                        </span>
                                                                        <span :class="t.margin_negative ? 'font-semibold text-rose-600' : 'text-gray-500'">
                                                                            {{ __('admin.producer_receiving.move_margin') }}
                                                                            <span x-text="t.margin_label"></span>
                                                                        </span>
                                                                    </span>
                                                                    <span x-show="t.price_differs && !t.margin_negative" class="mt-0.5 block text-xs text-amber-700">
                                                                        {{ __('admin.producer_receiving.move_price_differs_warning') }}
                                                                    </span>
                                                                    <span x-show="t.margin_negative" class="mt-0.5 block text-xs font-semibold text-rose-600">
                                                                        {{ __('admin.producer_receiving.move_margin_negative_warning') }}
                                                                    </span>
                                                                </span>
                                                                <span class="shrink-0 self-start font-mono text-xs text-gray-400" x-show="t.capacity > 0" x-text="capacityLabel(t)"></span>
                                                            </button>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="!selected()">
                            <div class="rounded-lg border border-dashed border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
                                {{ __('admin.producer_receiving.select_producer') }}
                            </div>
                        </template>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        function producerReceiving(config) {
            return {
                producers: config.producers,
                selectedProducerId: config.producers.length ? config.producers[0].id : null,

                openMoveKey: null,
                moveTargets: [],
                moveSearch: '',
                moveLoading: false,
                moveError: '',

                selected() {
                    return this.producers.find(p => p.id === this.selectedProducerId) || null;
                },

                movedFromLabel(row) {
                    if (!row.override) return '';
                    return '{{ __('admin.producer_receiving.moved_from') }}'.replace(':product', row.override.source_product_name);
                },

                movedMarginLabel(row) {
                    const o = row.override;
                    if (!o || !o.margin_label) return '';
                    return '{{ __('admin.producer_receiving.moved_margin_note') }}'
                        .replace(':margin', o.margin_label)
                        .replace(':buying', o.buying_price_label || '');
                },

                capacityLabel(t) {
                    return '{{ __('admin.producer_receiving.move_capacity') }}'.replace(':qty', t.capacity);
                },

                openMove(row) {
                    this.openMoveKey = row.key;
                    this.moveSearch = '';
                    this.moveError = '';
                    this.moveTargets = [];
                    this.loadTargets(row, '');
                },

                closeMove() {
                    this.openMoveKey = null;
                    this.moveTargets = [];
                    this.moveSearch = '';
                    this.moveError = '';
                },

                searchMove(row) {
                    this.loadTargets(row, this.moveSearch);
                },

                async loadTargets(row, search) {
                    this.moveLoading = true;
                    this.moveError = '';
                    try {
                        const params = new URLSearchParams({
                            product_id: row.product_id,
                            variant_id: row.variant_id,
                            delivery_date: row.date,
                            current_producer_id: this.selectedProducerId,
                        });
                        if (search) params.set('search', search);
                        const res = await fetch(config.moveTargetsUrl + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('failed');
                        const data = await res.json();
                        // Ignore stale responses if the overlay moved on.
                        if (this.openMoveKey !== row.key) return;
                        this.moveTargets = data.targets || [];
                    } catch (e) {
                        this.moveError = '{{ __('admin.producer_receiving.move_failed') }}';
                    } finally {
                        this.moveLoading = false;
                    }
                },

                async applyMove(row, target) {
                    this.moveLoading = true;
                    this.moveError = '';
                    try {
                        const res = await fetch(config.moveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                product_id: row.product_id,
                                variant_id: row.variant_id,
                                delivery_date: row.date,
                                target_product_id: target.target_product_id,
                                target_producer_id: target.producer_id,
                            }),
                        });
                        if (!res.ok) throw new Error('failed');
                        window.location.reload();
                    } catch (e) {
                        this.moveLoading = false;
                        this.moveError = '{{ __('admin.producer_receiving.move_failed') }}';
                    }
                },

                async resetMove(row) {
                    try {
                        const res = await fetch(config.resetMoveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                product_id: row.override ? row.override.source_product_id : row.product_id,
                                variant_id: row.override ? row.override.source_variant_id : row.variant_id,
                                delivery_date: row.date,
                            }),
                        });
                        if (!res.ok) throw new Error('failed');
                        window.location.reload();
                    } catch (e) {
                        alert('{{ __('admin.producer_receiving.move_failed') }}');
                    }
                },

                recount(p) {
                    p.received_count = p.rows.filter(r => r.received).length;
                    p.done = p.total_count > 0 && p.received_count === p.total_count;
                },

                exportHref(producerId) {
                    return config.exportBase + (config.exportBase.includes('?') ? '&' : '?') + 'producer_id=' + producerId;
                },

                formatDate(iso) {
                    const [y, m, d] = iso.split('-');
                    return `${d}.${m}.${y}`;
                },

                async toggle(p, row) {
                    // Optimistic flip, rollback on failure.
                    const next = !row.received;
                    row.received = next;
                    this.recount(p);
                    try {
                        const res = await fetch(config.toggleUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                producer_id: p.id,
                                product_id: row.product_id,
                                variant_id: row.variant_id,
                                delivery_date: row.date,
                            }),
                        });
                        if (!res.ok) throw new Error('failed');
                        const data = await res.json();
                        row.received = data.received;
                        this.recount(p);
                    } catch (e) {
                        row.received = !next;
                        this.recount(p);
                    }
                },

                async receiveAll(p) {
                    const prev = p.rows.map(r => r.received);
                    p.rows.forEach(r => { r.received = true; });
                    this.recount(p);
                    try {
                        const params = new URLSearchParams(config.filter);
                        params.set('producer_id', p.id);
                        const res = await fetch(config.receiveAllUrl + '?' + params.toString(), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        if (!res.ok) throw new Error('failed');
                    } catch (e) {
                        p.rows.forEach((r, i) => { r.received = prev[i]; });
                        this.recount(p);
                    }
                },
            };
        }
    </script>
</x-admin-layout>
