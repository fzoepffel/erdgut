{{--
    Cmd+K admin quick-jump.

    Mounted once per admin page from layouts/admin.blade.php. Stays
    closed until Cmd+K / Ctrl+K (or click of the trigger button in the
    top bar). Fetches /app/admin/search on every input (200 ms debounce)
    and renders grouped results that can be navigated with ↑↓ and
    activated with Enter.
--}}
<div
    x-data="adminSearchPalette()"
    x-on:keydown.window.prevent.meta.k="open()"
    x-on:keydown.window.prevent.ctrl.k="open()"
    x-on:open-admin-search-palette.window="open()"
    x-cloak
>
    <template x-teleport="body">
        <div
            x-show="isOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center bg-gray-900/40 p-4 pt-[10vh]"
            x-on:click.self="close()"
            x-on:keydown.escape.window="close()"
            style="display:none;"
        >
            <div class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="border-b border-gray-100 px-3 py-2">
                    <input
                        type="search"
                        x-model="query"
                        x-on:input.debounce.200ms="search()"
                        x-on:keydown.arrow-down.prevent="moveSelection(1)"
                        x-on:keydown.arrow-up.prevent="moveSelection(-1)"
                        x-on:keydown.enter.prevent="activateSelection()"
                        x-ref="input"
                        placeholder="{{ __('admin.search.placeholder') }}"
                        autocomplete="off"
                        class="block w-full border-0 bg-transparent text-base text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0"
                    />
                </div>

                <div class="max-h-[60vh] overflow-y-auto">
                    <template x-if="loading">
                        <p class="px-4 py-6 text-sm text-gray-500">{{ __('admin.search.loading') }}</p>
                    </template>

                    <template x-if="! loading && query.length > 0 && totalCount() === 0">
                        <p class="px-4 py-6 text-sm text-gray-500">{{ __('admin.search.no_results') }}</p>
                    </template>

                    <template x-if="! loading && query.length === 0">
                        <p class="px-4 py-6 text-sm text-gray-500">{{ __('admin.search.empty_hint') }}</p>
                    </template>

                    <template x-for="group in groups()" :key="group.key">
                        <div x-show="group.items.length > 0" class="border-t border-gray-100 first:border-t-0">
                            <p class="bg-gray-50 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-500" x-text="group.label"></p>
                            <ul>
                                <template x-for="(item, idx) in group.items" :key="group.key + '-' + item.id">
                                    <li>
                                        <a
                                            :href="item.url"
                                            x-on:mouseenter="selectFlat(group.key, idx)"
                                            :class="isSelected(group.key, idx)
                                                ? 'bg-amber-50'
                                                : 'hover:bg-gray-50'"
                                            class="flex items-center justify-between gap-3 px-4 py-2 text-sm focus:outline-none"
                                        >
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate font-medium text-gray-900" x-text="item.label"></span>
                                                <template x-if="item.sublabel">
                                                    <span class="block truncate text-xs text-gray-500" x-text="item.sublabel"></span>
                                                </template>
                                            </span>
                                            <template x-if="item.badge">
                                                <span class="inline-flex shrink-0 items-center rounded bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-700" x-text="item.badge"></span>
                                            </template>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>

                <div class="border-t border-gray-100 bg-gray-50 px-4 py-2 text-[11px] text-gray-500">
                    <span class="font-mono">↑↓</span> {{ __('admin.search.hint_navigate') }}
                    <span class="ml-3 font-mono">↵</span> {{ __('admin.search.hint_open') }}
                    <span class="ml-3 font-mono">esc</span> {{ __('admin.search.hint_close') }}
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    function adminSearchPalette() {
        return {
            isOpen: false,
            query: '',
            loading: false,
            results: { users: [], subscriptions: [], deliveries: [], producers: [], products: [] },
            selectedKey: null,
            selectedIdx: 0,
            groupOrder: ['users', 'subscriptions', 'deliveries', 'producers', 'products'],
            groupLabels: {
                users: @js(__('admin.search.group_users')),
                subscriptions: @js(__('admin.search.group_subscriptions')),
                deliveries: @js(__('admin.search.group_deliveries')),
                producers: @js(__('admin.search.group_producers')),
                products: @js(__('admin.search.group_products')),
            },
            open() {
                this.isOpen = true;
                this.$nextTick(() => this.$refs.input?.focus());
            },
            close() {
                this.isOpen = false;
            },
            groups() {
                return this.groupOrder.map((key) => ({
                    key,
                    label: this.groupLabels[key],
                    items: this.results[key] ?? [],
                }));
            },
            totalCount() {
                return this.groupOrder.reduce((acc, key) => acc + (this.results[key]?.length ?? 0), 0);
            },
            isSelected(key, idx) {
                return this.selectedKey === key && this.selectedIdx === idx;
            },
            selectFlat(key, idx) {
                this.selectedKey = key;
                this.selectedIdx = idx;
            },
            firstSelectable() {
                for (const key of this.groupOrder) {
                    if ((this.results[key] ?? []).length > 0) {
                        return { key, idx: 0 };
                    }
                }
                return null;
            },
            moveSelection(delta) {
                // Build a flat sequence of (key, idx) pairs in display order.
                const flat = [];
                for (const key of this.groupOrder) {
                    (this.results[key] ?? []).forEach((_, i) => flat.push({ key, idx: i }));
                }
                if (flat.length === 0) {
                    return;
                }
                let pos = flat.findIndex((p) => p.key === this.selectedKey && p.idx === this.selectedIdx);
                if (pos === -1) {
                    pos = 0;
                }
                pos = (pos + delta + flat.length) % flat.length;
                const next = flat[pos];
                this.selectedKey = next.key;
                this.selectedIdx = next.idx;
            },
            activateSelection() {
                if (this.selectedKey === null) {
                    return;
                }
                const item = (this.results[this.selectedKey] ?? [])[this.selectedIdx];
                if (item?.url) {
                    window.location.href = item.url;
                }
            },
            async search() {
                const q = this.query.trim();
                if (q === '') {
                    this.results = { users: [], subscriptions: [], deliveries: [], producers: [], products: [] };
                    this.selectedKey = null;
                    return;
                }
                this.loading = true;
                try {
                    const res = await fetch('{{ route('app.admin.search') }}?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) {
                        return;
                    }
                    this.results = await res.json();
                    const first = this.firstSelectable();
                    this.selectedKey = first?.key ?? null;
                    this.selectedIdx = first?.idx ?? 0;
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
