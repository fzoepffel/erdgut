<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.shop_closures.title') }}
            </h1>
            <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('shop.admin.nav_label') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12"
         x-data="closureCalendar({
             initialCalendar: @js($calendar),
             toggleUrl: @js(route('app.admin.shop-closures.toggle')),
             indexUrl: @js(route('app.admin.shop-closures.index')),
             csrf: @js(csrf_token()),
         })">
        <div class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6 lg:px-8">

            <p class="text-sm text-gray-600">{{ __('admin.shop_closures.intro_calendar') }}</p>

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-4 sm:px-6">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shop_closures.calendar_heading') }}</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.shop_closures.calendar_hint') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="goMonth(calendar.prev_month)"
                                class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                aria-label="{{ __('admin.shop_closures.prev_month') }}">
                            &larr;
                        </button>
                        <span class="min-w-[9rem] text-center text-sm font-medium text-gray-900" x-text="calendar.label"></span>
                        <button type="button"
                                @click="goMonth(calendar.next_month)"
                                class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                                aria-label="{{ __('admin.shop_closures.next_month') }}">
                            &rarr;
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                        <template x-for="label in calendar.weekdays" :key="label">
                            <div x-text="label"></div>
                        </template>
                    </div>

                    <div class="space-y-1">
                        <template x-for="(week, wi) in calendar.weeks" :key="wi">
                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="cell in week" :key="cell.iso">
                                    <button type="button"
                                            @click="!cell.is_disabled && toggleDay(cell.iso)"
                                            :disabled="cell.is_disabled || loadingDate === cell.iso"
                                            :aria-pressed="cell.closed ? 'true' : 'false'"
                                            :aria-disabled="cell.is_disabled ? 'true' : 'false'"
                                            :aria-label="cell.iso"
                                            class="relative flex aspect-square items-center justify-center rounded-md text-sm tabular-nums transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:cursor-not-allowed"
                                            :class="{
                                                'bg-red-100 text-red-900 ring-1 ring-red-200 hover:bg-red-200': cell.closed && cell.in_month && !cell.is_disabled,
                                                'bg-white text-gray-800 hover:bg-gray-50 ring-1 ring-gray-100': !cell.closed && cell.in_month && !cell.is_disabled,
                                                'bg-gray-100 text-gray-300 ring-1 ring-gray-100': cell.is_disabled && cell.in_month,
                                                'bg-gray-50 text-gray-300': !cell.in_month || (cell.is_disabled && !cell.in_month),
                                                'ring-2 ring-emerald-500 ring-offset-1': cell.is_today && cell.in_month && !cell.is_disabled,
                                                'opacity-60': loadingDate === cell.iso,
                                            }">
                                        <span x-text="cell.day"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-red-100 ring-1 ring-red-200"></span>
                            {{ __('admin.shop_closures.legend_closed') }}
                        </span>
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded bg-white ring-1 ring-gray-100"></span>
                            {{ __('admin.shop_closures.legend_open') }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
    function closureCalendar(config) {
        return {
            calendar: config.initialCalendar,
            toggleUrl: config.toggleUrl,
            loadingDate: null,

            goMonth(month) {
                window.location.href = config.indexUrl + '?month=' + encodeURIComponent(month);
            },

            async toggleDay(iso) {
                if (this.loadingDate) {
                    return;
                }

                this.loadingDate = iso;

                try {
                    const response = await fetch(this.toggleUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': config.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            date: iso,
                            month: this.calendar.month,
                        }),
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    this.calendar = data.calendar;
                } finally {
                    this.loadingDate = null;
                }
            },
        };
    }
    </script>
</x-admin-layout>
