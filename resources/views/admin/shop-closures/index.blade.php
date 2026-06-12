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
             activeRanges: @js($activeRanges),
             toggleUrl: @js(route('app.admin.shop-closures.toggle')),
             indexUrl: @js(route('app.admin.shop-closures.index')),
             csrf: @js(csrf_token()),
         })">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-600">{{ __('admin.shop_closures.intro_calendar') }}</p>

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shop_closures.create_heading') }}</h2>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.shop_closures.create_range_hint') }}</p>
                </div>
                <form method="POST" action="{{ route('app.admin.shop-closures.store') }}" class="space-y-4 p-6">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="starts_on" class="block text-sm font-medium text-gray-700">{{ __('admin.shop_closures.field_starts_on') }}</label>
                            <input type="date" id="starts_on" name="starts_on" value="{{ old('starts_on', $tomorrow) }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <x-input-error :messages="$errors->get('starts_on')" class="mt-1" />
                        </div>
                        <div>
                            <label for="ends_on" class="block text-sm font-medium text-gray-700">{{ __('admin.shop_closures.field_ends_on') }}</label>
                            <input type="date" id="ends_on" name="ends_on" value="{{ old('ends_on') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.shop_closures.field_ends_on_hint') }}</p>
                            <x-input-error :messages="$errors->get('ends_on')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">{{ __('admin.shop_closures.field_reason') }}</label>
                        <input type="text" id="reason" name="reason" value="{{ old('reason') }}" maxlength="500"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>
                    <div>
                        <x-primary-button size="sm">{{ __('admin.shop_closures.submit_create') }}</x-primary-button>
                    </div>
                </form>
            </div>

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
                                            @click="toggleDay(cell.iso)"
                                            :disabled="loadingDate === cell.iso"
                                            :aria-pressed="cell.closed ? 'true' : 'false'"
                                            :aria-label="cell.iso"
                                            class="relative flex aspect-square items-center justify-center rounded-md text-sm tabular-nums transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:opacity-60"
                                            :class="{
                                                'bg-red-100 text-red-900 ring-1 ring-red-200 hover:bg-red-200': cell.closed && cell.in_month,
                                                'bg-white text-gray-800 hover:bg-gray-50 ring-1 ring-gray-100': !cell.closed && cell.in_month,
                                                'bg-gray-50 text-gray-400': !cell.in_month,
                                                'ring-2 ring-emerald-500 ring-offset-1': cell.is_today && cell.in_month,
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

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm" x-show="activeRanges.length > 0" x-cloak>
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shop_closures.section_ranges') }}</h2>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    <template x-for="range in activeRanges" :key="range.id">
                        <li class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-medium text-gray-900 tabular-nums">
                                    <span x-text="range.starts_on"></span>
                                    <span x-show="range.ends_on !== range.starts_on"> – <span x-text="range.ends_on"></span></span>
                                </p>
                                <p class="mt-1 text-gray-600" x-show="range.reason" x-text="range.reason"></p>
                                <p class="mt-1 text-xs text-gray-500" x-show="range.open_dates.length > 0">
                                    {{ __('admin.shop_closures.open_days_in_range') }}:
                                    <span x-text="range.open_dates.join(', ')"></span>
                                </p>
                            </div>
                            <form method="post"
                                  :action="`{{ url('/app/admin/shop-closures') }}/${range.id}?month=${calendar.month}`"
                                  onsubmit="return confirm(@js(__('admin.shop_closures.delete_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 underline underline-offset-2 hover:no-underline">
                                    {{ __('Delete') }}
                                </button>
                            </form>
                        </li>
                    </template>
                </ul>
            </div>

        </div>
    </div>

    <script>
    function closureCalendar(config) {
        return {
            calendar: config.initialCalendar,
            activeRanges: config.activeRanges,
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
                    this.activeRanges = data.active_ranges;
                } finally {
                    this.loadingDate = null;
                }
            },
        };
    }
    </script>
</x-admin-layout>
