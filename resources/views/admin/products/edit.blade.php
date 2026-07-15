<x-admin-layout>
    <x-slot name="breadcrumbs">
        <x-admin.breadcrumbs :items="[
            ['label' => __('admin.shell.overview'), 'href' => route('app.admin.dashboard')],
            ['label' => __('Products'), 'href' => route('app.admin.products.index')],
            ['label' => $product->name],
        ]" />
    </x-slot>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit product') }}: {{ $product->name }}
            </h1>
            <a href="{{ route('app.admin.products.pricing', $product->id) }}"
               class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                {{ __('admin.product_pricing.nav_link') }} &rarr;
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @include('admin.products._form', [
                        'product' => $product,
                        'producers' => $producers,
                        'categories' => $categories,
                        'action' => route('app.admin.products.update', $product),
                        'method' => 'put',
                    ])
                </div>
            </div>

            <p class="text-xs text-gray-500">
                {{ __('admin.product_schedules.moved_to_weekly_catalog_hint') }}
                <a href="{{ route('app.admin.inventory.index') }}" class="font-medium text-emerald-700 hover:underline">{{ __('admin.weekly_catalog.nav_label') }}</a>.
            </p>

            @if (($mergeTargets ?? null) !== null && $mergeTargets !== [])
                {{-- Danger zone: dissolve this duplicate into another product. --}}
                <div class="rounded-lg border border-red-200 bg-red-50 p-6" data-testid="product-merge-card">
                    <h2 class="text-base font-semibold text-red-900">{{ __('admin.products.merge.title') }}</h2>
                    <p class="mt-2 text-sm text-red-800">{{ __('admin.products.merge.intro') }}</p>

                    <form method="get" action="{{ route('app.admin.products.merge.confirm', $product) }}" class="mt-4">
                        <div
                            x-data='{
                                options: {!! json_encode($mergeTargets, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
                                selected: null,
                                query: "",
                                open: false,
                                highlight: 0,
                                get filtered() {
                                    const q = this.query.trim().toLocaleLowerCase();
                                    if (q === "") return this.options;
                                    return this.options.filter(o =>
                                        o.name.toLocaleLowerCase().includes(q) || (o.producer || "").toLocaleLowerCase().includes(q));
                                },
                                pick(option) {
                                    this.selected = option;
                                    this.query = "";
                                    this.open = false;
                                },
                                onKey(ev) {
                                    const list = this.filtered;
                                    if (ev.key === "ArrowDown") {
                                        ev.preventDefault();
                                        this.open = true;
                                        this.highlight = Math.min(list.length - 1, this.highlight + 1);
                                    } else if (ev.key === "ArrowUp") {
                                        ev.preventDefault();
                                        this.highlight = Math.max(0, this.highlight - 1);
                                    } else if (ev.key === "Enter") {
                                        if (this.open && list[this.highlight]) {
                                            ev.preventDefault();
                                            this.pick(list[this.highlight]);
                                        }
                                    } else if (ev.key === "Escape") {
                                        this.open = false;
                                    }
                                },
                            }'
                            x-on:click.outside="open = false"
                            class="relative"
                        >
                            <label for="merge_winner_search" class="block text-sm font-medium text-red-900">{{ __('admin.products.merge.target_label') }}</label>

                            <template x-if="selected">
                                <span class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-red-300 bg-white px-2.5 py-1 text-sm text-red-900">
                                    <span x-text="selected.name + (selected.producer ? ' (' + selected.producer + ')' : '')"></span>
                                    <button type="button" x-on:click="selected = null" class="text-red-600 hover:text-red-800" aria-label="{{ __('admin.products.merge.cancel') }}">&times;</button>
                                    <input type="hidden" name="winner_id" x-bind:value="selected.id" />
                                </span>
                            </template>

                            <input
                                id="merge_winner_search"
                                type="text"
                                x-show="!selected"
                                class="mt-2 block w-full rounded-md border-red-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                                x-model="query"
                                x-on:input="open = true; highlight = 0"
                                x-on:focus="open = true"
                                x-on:keydown="onKey($event)"
                                placeholder="{{ __('admin.products.merge.search_placeholder') }}"
                                autocomplete="off"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                x-bind:aria-expanded="open ? 'true' : 'false'"
                                aria-controls="merge-winner-listbox"
                                data-testid="product-merge-combobox"
                            />

                            <ul
                                id="merge-winner-listbox"
                                role="listbox"
                                x-show="open && !selected && filtered.length > 0"
                                x-cloak
                                class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg"
                            >
                                <template x-for="(option, idx) in filtered" :key="option.id">
                                    <li
                                        role="option"
                                        x-bind:class="idx === highlight ? 'bg-red-50 text-red-900' : 'text-gray-700 hover:bg-gray-50'"
                                        class="cursor-pointer px-3 py-2 text-sm"
                                        x-on:mouseenter="highlight = idx"
                                        x-on:click="pick(option)"
                                    >
                                        <span x-text="option.name"></span>
                                        <span class="text-xs text-gray-500" x-show="option.producer" x-text="' (' + option.producer + ')'"></span>
                                    </li>
                                </template>
                            </ul>

                            <div class="mt-4">
                                <x-danger-button type="submit" x-bind:disabled="!selected">
                                    {{ __('admin.products.merge.submit') }}
                                </x-danger-button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
