<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('admin.packing.title') }}</h1>
            <div class="flex items-center gap-2">
                <a href="?date={{ $prevDate }}" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50">&larr;</a>
                <span class="min-w-[10rem] text-center text-sm font-medium text-gray-900">{{ $date->translatedFormat('l, d.m.Y') }}</span>
                <a href="?date={{ $nextDate }}" class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50">&rarr;</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10"
         x-data="packing({
            products: @js($products),
            clients: @js($clients),
            orders: @js($orders),
            toggleUrlBase: @js(route('app.admin.packing.toggle', ['item' => '__ID__'])),
            productUrl: @js(route('app.admin.packing.product')),
            orderPackUrl: @js(route('app.admin.packing.order')),
            orderUrlBase: @js(route('app.admin.shop-orders.show', ['shopOrder' => '__ID__'])),
            date: @js($date->toDateString()),
            csrf: @js(csrf_token()),
         })">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-600">{{ __('admin.packing.intro') }}</p>
                @if (count($products) > 0)
                    {{-- Grouping toggle: pack product-by-product, or client-by-client. --}}
                    <div class="inline-flex shrink-0 rounded-md border border-gray-200 bg-white p-0.5 text-sm">
                        <button type="button" @click="mode = 'product'"
                                class="rounded px-3 py-1 font-medium transition"
                                :class="mode === 'product' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-50'">{{ __('admin.packing.view_by_product') }}</button>
                        <button type="button" @click="mode = 'client'"
                                class="rounded px-3 py-1 font-medium transition"
                                :class="mode === 'client' ? 'bg-emerald-600 text-white' : 'text-gray-600 hover:bg-gray-50'">{{ __('admin.packing.view_by_client') }}</button>
                    </div>
                @endif
            </div>

            @if (count($products) === 0)
                <div class="rounded-lg border border-gray-100 bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                    {{ __('admin.packing.no_orders') }}
                </div>
            @else
                {{-- Day progress --}}
                <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-800" x-text="progressLabel()"></span>
                        <span class="font-mono text-xs text-gray-500" x-text="dayPacked() + ' / ' + dayTotal()"></span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" :style="`width: ${dayTotal() ? Math.round(dayPacked()/dayTotal()*100) : 0}%`"></div>
                    </div>
                </div>

                <div x-show="mode === 'product'" class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {{-- Left: product checklist --}}
                    <div class="lg:col-span-1">
                        <h2 class="mb-2 font-mono text-[0.7rem] uppercase tracking-widest text-gray-500">{{ __('admin.packing.products_heading') }}</h2>
                        <div class="space-y-1.5">
                            <template x-for="p in products" :key="p.key">
                                <button type="button" @click="selectedKey = p.key"
                                        class="flex w-full items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm transition"
                                        :class="{
                                            'border-emerald-600 bg-emerald-50': selectedKey === p.key,
                                            'border-gray-200 bg-white hover:bg-gray-50': selectedKey !== p.key,
                                            'opacity-60': p.done,
                                        }">
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span x-show="p.done" class="text-emerald-600">✓</span>
                                            <span class="truncate font-medium text-gray-900" x-text="p.name"></span>
                                        </span>
                                        <span class="block text-[0.7rem] text-gray-500" x-text="p.total_qty_label + (p.producer ? ' · ' + p.producer : '')"></span>
                                    </span>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 font-mono text-[0.65rem]"
                                          :class="p.done ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                          x-text="p.packed_count + '/' + p.total_count"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Middle: selected product's order lines --}}
                    <div class="lg:col-span-2">
                        <template x-if="!selected()">
                            <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center text-sm text-gray-400">{{ __('admin.packing.select_product') }}</div>
                        </template>
                        <template x-if="selected()">
                            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                                <div class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="font-semibold text-gray-900" x-text="selected().name"></p>
                                        <p class="text-xs text-gray-500" x-text="'{{ __('admin.packing.order_lines_heading') }} · ' + selected().total_qty_label"></p>
                                    </div>
                                    <button type="button" @click="packAll(selected())"
                                            x-show="!selected().done && !selected().is_custom"
                                            class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700"
                                            x-text="'{{ __('admin.packing.pack_all', ['count' => '__C__']) }}'.replace('__C__', selected().total_count - selected().packed_count)"></button>
                                </div>
                                <ul class="divide-y divide-gray-100">
                                    <template x-for="line in selected().lines" :key="line.item_id">
                                        <li class="flex items-center justify-between gap-3 px-4 py-3" :class="line.packed ? 'bg-emerald-50/40' : ''">
                                            <div class="min-w-0">
                                                <p class="flex items-center gap-1.5 text-sm font-medium text-gray-900">
                                                    <span class="font-mono text-gray-500" x-text="'#' + line.order_id"></span>
                                                    <span x-show="line.b2b" class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                                                    <span x-text="line.customer"></span>
                                                </p>
                                                <p class="text-[0.7rem] text-gray-500">
                                                    <span x-text="line.fulfillment"></span>
                                                    <template x-if="line.window"><span x-text="' · ' + line.window"></span></template>
                                                </p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-3">
                                                <span class="font-mono text-sm tabular-nums text-gray-800" x-text="line.qty_label"></span>
                                                <button type="button" @click="toggle(line)"
                                                        class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
                                                        :class="line.packed ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                                                        x-text="line.packed ? '✓ {{ __('admin.packing.packed') }}' : '{{ __('admin.packing.pack') }}'"></button>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- By-client grouping: pick a client, pack every product in their order. --}}
                <div x-show="mode === 'client'" class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    {{-- Left: client checklist --}}
                    <div class="lg:col-span-1">
                        <h2 class="mb-2 font-mono text-[0.7rem] uppercase tracking-widest text-gray-500">{{ __('admin.packing.clients_heading') }}</h2>
                        <div class="space-y-1.5">
                            <template x-for="c in clients" :key="c.key">
                                <button type="button" @click="selectedClientKey = c.key"
                                        class="flex w-full items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm transition"
                                        :class="{
                                            'border-emerald-600 bg-emerald-50': selectedClientKey === c.key,
                                            'border-gray-200 bg-white hover:bg-gray-50': selectedClientKey !== c.key,
                                            'opacity-60': c.done,
                                        }">
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span x-show="c.done" class="text-emerald-600">✓</span>
                                            <span x-show="c.b2b" class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                                            <span class="truncate font-medium text-gray-900" x-text="c.customer"></span>
                                        </span>
                                        <span class="block text-[0.7rem] text-gray-500" x-text="'#' + c.id + ' · ' + c.fulfillment"></span>
                                    </span>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 font-mono text-[0.65rem]"
                                          :class="c.done ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                          x-text="c.packed_count + '/' + c.total_count"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Middle: selected client's product lines --}}
                    <div class="lg:col-span-2">
                        <template x-if="!selectedClient()">
                            <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center text-sm text-gray-400">{{ __('admin.packing.select_client') }}</div>
                        </template>
                        <template x-if="selectedClient()">
                            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                                <div class="flex items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-1.5 font-semibold text-gray-900">
                                            <span class="font-mono text-gray-500" x-text="'#' + selectedClient().id"></span>
                                            <span x-show="selectedClient().b2b" class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                                            <span x-text="selectedClient().customer"></span>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <span x-text="selectedClient().fulfillment"></span>
                                            <template x-if="selectedClient().window"><span x-text="' · ' + selectedClient().window"></span></template>
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <a :href="orderUrlBase.replace('__ID__', selectedClient().id)" x-show="!selectedClient().b2b"
                                           class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('admin.packing.open_order') }}</a>
                                        <button type="button" @click="packClient(selectedClient())"
                                                x-show="!selectedClient().done"
                                                class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700"
                                                x-text="'{{ __('admin.packing.pack_all', ['count' => '__C__']) }}'.replace('__C__', selectedClient().total_count - selectedClient().packed_count)"></button>
                                    </div>
                                </div>
                                <ul class="divide-y divide-gray-100">
                                    <template x-for="line in selectedClient().lines" :key="line.item_id">
                                        <li class="flex items-center justify-between gap-3 px-4 py-3" :class="line.packed ? 'bg-emerald-50/40' : ''">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-gray-900" x-text="line.name"></p>
                                                <p class="text-[0.7rem] text-gray-500" x-text="line.producer"></p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-3">
                                                <span class="font-mono text-sm tabular-nums text-gray-800" x-text="line.qty_label"></span>
                                                <button type="button" @click="toggle(line)"
                                                        class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
                                                        :class="line.packed ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                                                        x-text="line.packed ? '✓ {{ __('admin.packing.packed') }}' : '{{ __('admin.packing.pack') }}'"></button>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Orders readiness strip --}}
                <div>
                    <h2 class="mb-2 font-mono text-[0.7rem] uppercase tracking-widest text-gray-500">{{ __('admin.packing.orders_heading') }}</h2>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="o in orders" :key="o.key">
                            <a :href="o.url"
                               class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm transition hover:bg-gray-50"
                               :class="o.ready ? 'border-emerald-300 bg-emerald-50' : 'border-gray-200 bg-white'">
                                <span class="font-mono text-gray-500" x-text="'#' + o.id"></span>
                                <span x-show="o.b2b" class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                                <span class="text-gray-800" x-text="o.customer"></span>
                                <span x-show="o.ready" class="rounded-full bg-emerald-600 px-2 py-0.5 font-mono text-[0.6rem] uppercase tracking-wider text-white">{{ __('admin.packing.ready') }}</span>
                                <span x-show="!o.ready" class="font-mono text-[0.65rem] text-gray-500" x-text="o.packed_count + '/' + o.total_count"></span>
                            </a>
                        </template>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
    function packing(config) {
        return {
            products: config.products,
            clients: config.clients,
            orders: config.orders,
            toggleUrlBase: config.toggleUrlBase,
            productUrl: config.productUrl,
            orderPackUrl: config.orderPackUrl,
            orderUrlBase: config.orderUrlBase,
            date: config.date,
            csrf: config.csrf,
            mode: (localStorage.getItem('packingMode') === 'client') ? 'client' : 'product',
            selectedKey: config.products.find((p) => !p.done)?.key ?? config.products[0]?.key ?? null,
            selectedClientKey: config.clients.find((c) => !c.done)?.key ?? config.clients[0]?.key ?? null,

            init() {
                this.$watch('mode', (v) => localStorage.setItem('packingMode', v));
            },

            selected() { return this.products.find((p) => p.key === this.selectedKey) ?? null; },
            selectedClient() { return this.clients.find((c) => c.key === this.selectedClientKey) ?? null; },
            dayTotal() { return this.products.reduce((n, p) => n + p.total_count, 0); },
            dayPacked() { return this.products.reduce((n, p) => n + p.packed_count, 0); },
            progressLabel() {
                return '{{ __('admin.packing.day_progress', ['packed' => '__P__', 'total' => '__T__']) }}'
                    .replace('__P__', this.dayPacked()).replace('__T__', this.dayTotal());
            },

            recountProduct(p) {
                p.packed_count = p.lines.filter((l) => l.packed).length;
                p.done = p.packed_count >= p.total_count;
            },
            recountClient(c) {
                c.packed_count = c.lines.filter((l) => l.packed).length;
                c.done = c.packed_count >= c.total_count;
            },
            applyOrder(updated) {
                const o = this.orders.find((x) => x.key === updated.key);
                if (o) { o.packed_count = updated.packed_count; o.total_count = updated.total_count; o.ready = updated.ready; }
            },

            // The same order line lives in a product group AND a client group as
            // two separate objects; keep both projections consistent so the
            // counts never drift when the user switches grouping.
            setLinePacked(itemId, packed) {
                this.products.forEach((p) => {
                    let hit = false;
                    p.lines.forEach((l) => { if (l.item_id === itemId) { l.packed = packed; hit = true; } });
                    if (hit) this.recountProduct(p);
                });
                this.clients.forEach((c) => {
                    let hit = false;
                    c.lines.forEach((l) => { if (l.item_id === itemId) { l.packed = packed; hit = true; } });
                    if (hit) this.recountClient(c);
                });
            },

            toggle(line) {
                const prev = line.packed;
                this.setLinePacked(line.item_id, !prev); // optimistic, both views
                fetch(this.toggleUrlBase.replace('__ID__', line.item_id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                })
                    .then((r) => { if (!r.ok) throw new Error(); return r.json(); })
                    .then((d) => { this.setLinePacked(line.item_id, d.packed); this.applyOrder(d.order); })
                    .catch(() => { this.setLinePacked(line.item_id, prev); });
            },

            packAll(p) {
                p.lines.forEach((l) => this.setLinePacked(l.item_id, true)); // optimistic, both views
                fetch(this.productUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ date: this.date, product_id: p.product_id, variant_id: p.variant_id }),
                })
                    .then((r) => { if (!r.ok) throw new Error(); return r.json(); })
                    .then((d) => { (d.orders || []).forEach((o) => this.applyOrder(o)); })
                    .catch(() => { /* leave optimistic state; a reload reconciles */ });
            },

            packClient(c) {
                c.lines.forEach((l) => this.setLinePacked(l.item_id, true)); // optimistic, both views
                fetch(this.orderPackUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ order: c.key }),
                })
                    .then((r) => { if (!r.ok) throw new Error(); return r.json(); })
                    .then((d) => { this.applyOrder(d.order); })
                    .catch(() => { /* leave optimistic state; a reload reconciles */ });
            },
        };
    }
    </script>
</x-admin-layout>
