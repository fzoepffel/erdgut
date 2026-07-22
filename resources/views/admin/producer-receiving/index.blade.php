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
                                        <label class="flex cursor-pointer items-center gap-3 border-b border-gray-50 px-4 py-3 transition last:border-b-0 hover:bg-gray-50">
                                            <input type="checkbox" :checked="row.received"
                                                   @change="toggle(selected(), row)"
                                                   class="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-gray-800" x-text="row.product_name"></span>
                                                <span class="block text-xs text-gray-500">
                                                    <span x-show="row.variant_name" x-text="row.variant_name + ' · '"></span>
                                                    <span x-text="formatDate(row.date)"></span>
                                                </span>
                                            </span>
                                            <span class="shrink-0 font-mono text-sm text-gray-700"
                                                  x-text="row.qty_display + ' ' + row.unit"></span>
                                        </label>
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

                selected() {
                    return this.producers.find(p => p.id === this.selectedProducerId) || null;
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
