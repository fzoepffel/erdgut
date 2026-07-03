<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.heading') }}</h1>
                <span class="text-sm text-gray-500">{{ __('shop.admin.subheading', ['count' => $orders->total()]) }}</span>
            </div>
            <div class="flex items-center gap-2"
                 x-data="{
                     open: false,
                     producers: [],
                     loading: false,
                     picked: '',
                     async openPicker() {
                         this.open = !this.open;
                         if (this.open && this.producers.length === 0) {
                             this.loading = true;
                             try {
                                 const params = new URLSearchParams(@js(request()->query() ?: new \stdClass));
                                 const res = await fetch(@js(route('app.admin.shop-orders.farmer-export.producers')) + '?' + params.toString(), {
                                     headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                 });
                                 const data = await res.json();
                                 this.producers = Array.isArray(data) ? data : [];
                             } catch (e) {
                                 this.producers = [];
                             } finally {
                                 this.loading = false;
                             }
                         }
                     },
                     downloadHref() {
                         if (!this.picked) return '#';
                         const params = new URLSearchParams(@js(request()->query() ?: new \stdClass));
                         params.set('producer_id', this.picked);
                         return @js(route('app.admin.shop-orders.farmer-export')) + '?' + params.toString();
                     }
                 }"
                 @click.outside="open = false"
            >
                <a href="{{ route('app.admin.restaurant-orders.index', ['status' => 'submitted']) }}"
                   class="relative inline-flex items-center gap-1.5 rounded-sm border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100"
                   data-testid="shop-admin-requests-link">
                    {{ __('restaurant.admin_requests_link') }}
                    @if ($openRequests > 0)
                        <span class="rounded-full bg-amber-600 px-2 py-0.5 font-mono text-[11px] font-bold text-white">{{ $openRequests }}</span>
                    @endif
                </a>
                <a href="{{ route('app.admin.shop-orders.export', request()->query()) }}"
                   class="inline-flex items-center gap-1.5 rounded-sm border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                   data-testid="shop-admin-export-link">
                    {{ __('shop.admin.action_export') }}
                </a>
                <div class="relative">
                    <button type="button"
                            @click="openPicker()"
                            class="inline-flex items-center gap-1.5 rounded-sm border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                            data-testid="shop-admin-farmer-export-button">
                        {{ __('shop.admin.action_farmer_export') }}
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 z-30 mt-2 w-80 rounded-md border border-gray-200 bg-white p-4 shadow-lg"
                         data-testid="shop-admin-farmer-export-panel">
                        <label class="mb-1 block text-xs font-medium text-gray-600">
                            {{ __('shop.admin.farmer_export_pick_label') }}
                        </label>
                        <select x-model="picked"
                                :disabled="loading"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">— —</option>
                            <template x-for="p in producers" :key="p.id">
                                <option :value="p.id" x-text="p.name"></option>
                            </template>
                        </select>
                        <p class="mt-2 text-xs text-gray-500"
                           x-show="!loading && producers.length === 0" x-cloak>
                            {{ __('shop.admin.farmer_export_no_producers') }}
                        </p>
                        <p class="mt-2 text-xs text-gray-400" x-show="loading" x-cloak>
                            {{ __('Loading') }}…
                        </p>
                        <a :href="downloadHref()"
                           @click="if (!picked) { $event.preventDefault(); }"
                           :class="picked
                               ? 'mt-3 inline-flex w-full items-center justify-center gap-2 rounded-sm bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700'
                               : 'mt-3 inline-flex w-full items-center justify-center gap-2 rounded-sm bg-gray-300 px-4 py-2 text-sm font-semibold text-white cursor-not-allowed'"
                           :aria-disabled="!picked"
                           data-testid="shop-admin-farmer-export-download">
                            {{ __('shop.admin.farmer_export_download') }}
                        </a>
                    </div>
                </div>
                <a href="{{ route('app.admin.shop-orders.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-sm bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700"
                   data-testid="shop-admin-create-link">
                    + {{ __('shop.admin.action_create_order') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif

            @php
                $selFrom = $deliveryFrom !== '' ? $deliveryFrom : null;
                $selTo = $deliveryTo !== '' ? $deliveryTo : ($selFrom ?? null);
                $locale = app()->getLocale();
                // Button label: "Alle Termine" | "Mo, 6. Jul" | "6. Jul – 13. Jul"
                if ($selFrom === null) {
                    $rangeLabel = __('shop.admin.filter_all_dates');
                } elseif ($selFrom === $selTo) {
                    $rangeLabel = \Illuminate\Support\Carbon::parse($selFrom)->locale($locale)->isoFormat('dd, D. MMM YYYY');
                } else {
                    $rangeLabel = \Illuminate\Support\Carbon::parse($selFrom)->locale($locale)->isoFormat('D. MMM').' – '.\Illuminate\Support\Carbon::parse($selTo)->locale($locale)->isoFormat('D. MMM YYYY');
                }
            @endphp
            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm"
                 x-data="adminOrderCalendar({
                     month: @js($calendarInitialMonth),
                     initialDays: @js($calendarDays),
                     monthUrl: @js(route('app.admin.shop-orders.calendar-month')),
                     selFrom: @js($selFrom),
                     selTo: @js($selTo),
                     locale: @js($locale),
                     monthNames: @js(collect(range(1, 12))->map(fn ($m) => \Illuminate\Support\Carbon::create(2026, $m, 1)->locale($locale)->isoFormat('MMMM'))->all()),
                 })">
                <form method="get" x-ref="filterForm" class="flex flex-wrap items-end gap-3">
                    {{-- The calendar popover IS the date filter — the form only
                         carries the picked range in hidden fields. --}}
                    <input type="hidden" name="delivery_from" x-ref="deliveryFrom" value="{{ $deliveryFrom }}">
                    <input type="hidden" name="delivery_to" x-ref="deliveryTo" value="{{ $deliveryTo }}">

                    <div class="relative" @click.outside="calOpen = false" @keydown.escape.window="calOpen = false">
                        <label class="mb-1 block text-xs font-medium text-gray-600">{{ __('shop.admin.filter_dates') }}</label>
                        <button type="button" @click="calOpen = !calOpen" :aria-expanded="calOpen"
                                data-testid="shop-admin-calendar-toggle"
                                class="inline-flex min-w-[13rem] items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 hover:bg-gray-50">
                            <span>{{ $rangeLabel }}</span>
                            <svg class="h-3.5 w-3.5 text-gray-500 transition-transform" :class="calOpen && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                        </button>

                        {{-- Compact calendar popover with month paging (any month,
                             past or future). Click one day twice = that day; two
                             days = range. Green = deliveries, yellow = stock only. --}}
                        <div x-show="calOpen" x-cloak x-transition.origin.top
                             class="absolute left-0 z-40 mt-2 w-[21rem] rounded-lg border border-gray-200 bg-white p-3 shadow-xl"
                             data-testid="shop-admin-calendar">
                            <div class="mb-2 flex items-center justify-between">
                                <button type="button" @click="prevMonth()" class="rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-100" data-testid="shop-admin-cal-prev" aria-label="&larr;">&larr;</button>
                                <span class="text-sm font-medium text-gray-800" x-text="monthLabel()"></span>
                                <button type="button" @click="nextMonth()" class="rounded px-2 py-1 text-sm text-gray-600 hover:bg-gray-100" data-testid="shop-admin-cal-next" aria-label="&rarr;">&rarr;</button>
                            </div>
                            <div class="mb-1 grid grid-cols-7 gap-0.5 text-center font-mono text-[0.55rem] uppercase tracking-wider text-gray-400">
                                @foreach ([__('Mo'), __('Di'), __('Mi'), __('Do'), __('Fr'), __('Sa'), __('So')] as $dow)
                                    <span>{{ $dow }}</span>
                                @endforeach
                            </div>
                            <div class="space-y-0.5" :class="loading && 'opacity-50'">
                                <template x-for="(week, wi) in weeks()" :key="month + '-' + wi">
                                    <div class="grid grid-cols-7 gap-0.5">
                                        <template x-for="(day, di) in week" :key="di">
                                            <span>
                                                <button x-show="day" type="button"
                                                        @click="pick(day.iso)"
                                                        :data-testid="day ? ('shop-admin-cal-' + day.iso) : null"
                                                        :title="day ? dayTitle(day) : ''"
                                                        class="relative flex h-7 w-full items-center justify-center rounded text-[11px] tabular-nums transition"
                                                        :class="day ? toneFor(day) : ''">
                                                    <span x-text="day ? parseInt(day.iso.slice(8), 10) : ''"></span>
                                                    <span x-show="day && day.orders > 0" class="absolute right-0.5 top-0.5 h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-2 text-[0.65rem] text-gray-500">
                                <span class="flex items-center gap-3">
                                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-emerald-100 ring-1 ring-emerald-300"></span>{{ __('shop.admin.cal_legend_orders') }}</span>
                                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-amber-50 ring-1 ring-amber-300"></span>{{ __('shop.admin.cal_legend_stock') }}</span>
                                </span>
                                <button type="button" @click="apply('', '')" class="font-medium text-gray-700 underline" data-testid="shop-admin-all-dates">{{ __('shop.admin.filter_all_dates') }}</button>
                            </div>
                            <p class="mt-1 text-[0.65rem] text-gray-400">{{ __('shop.admin.cal_hint') }}</p>
                        </div>
                    </div>
                    <div>
                        <label for="status" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Status') }}</label>
                        <select id="status" name="status" class="rounded-md border-gray-300 text-sm">
                            <option value="finalized" @selected($status === 'finalized')>{{ __('shop.admin.status_finalized') }}</option>
                            <option value="all" @selected($status === 'all')>{{ __('All') }}</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>
                                    {{ __('shop.admin.status_' . $st->value) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="type" class="mb-1 block text-xs font-medium text-gray-600">{{ __('shop.admin.filter_type') }}</label>
                        <select id="type" name="type" class="rounded-md border-gray-300 text-sm">
                            <option value="" @selected($type === '')>{{ __('All') }}</option>
                            <option value="b2c" @selected($type === 'b2c')>{{ __('shop.admin.type_b2c') }}</option>
                            <option value="b2b" @selected($type === 'b2b')>{{ __('shop.admin.type_b2b') }}</option>
                        </select>
                    </div>
                    <x-primary-button size="sm">{{ __('Filter') }}</x-primary-button>
                    @if ($status !== 'finalized' || $type !== '')
                        <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-600 underline">{{ __('Reset') }}</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-100 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-600">
                            <th class="px-3 py-2">{{ __('shop.admin.col_id') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_placed') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_customer') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_type') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_fulfillment') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_when') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('shop.admin.col_total') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('shop.admin.col_variant_count') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_coupon') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orders as $order)
                            @if ($order instanceof \App\Models\RestaurantOrder)
                                <tr class="align-top" data-testid="shop-admin-restaurant-row-{{ $order->id }}">
                                    <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700">R#{{ $order->id }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ $order->created_at->locale(app()->getLocale())->isoFormat('D. MMM, HH:mm') }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-gray-800">{{ $order->user?->restaurantProfile?->business_name ?? $order->user?->name ?? '—' }}</div>
                                        @if ($order->user?->email)<div class="text-xs text-gray-500">{{ $order->user->email }}</div>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            <span class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                                            @if ($order->isRequest())
                                                <span class="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-amber-700">{{ __('restaurant.kind_request') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">
                                        {{ __('shop.fulfillment_delivery') }}
                                        <div class="text-xs text-gray-500">{{ $order->delivery_street }}@if ($order->delivery_postal_code), {{ $order->delivery_postal_code }}@endif</div>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">
                                        <div class="whitespace-nowrap">{{ $order->expected_delivery_date?->locale(app()->getLocale())->isoFormat('dd, D. MMM') ?? '—' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-medium tabular-nums text-gray-800">
                                        @eur($order->items_total_net_eur) <span class="text-[10px] font-normal text-gray-500">{{ __('restaurant.net_suffix') }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-gray-700"><span class="text-gray-400">—</span></td>
                                    <td class="whitespace-nowrap px-3 py-2"><span class="text-gray-400">—</span></td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800' => in_array($order->status->value, ['confirmed', 'fulfilled'], true),
                                            'bg-gray-100 text-gray-600' => ! in_array($order->status->value, ['confirmed', 'fulfilled'], true),
                                        ])>{{ __('restaurant.order_status_'.$order->status->value) }}</span>
                                        @if ($order->isInvoiced() && ! $order->isInvoicePaid())
                                            <div class="mt-1 text-[11px] font-semibold {{ $order->isInvoiceOverdue() ? 'text-rose-700' : 'text-amber-700' }}">{{ $order->isInvoiceOverdue() ? __('restaurant.invoice_overdue_badge') : __('restaurant.invoice_open_badge') }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right">
                                        <a href="{{ route('app.admin.restaurant-orders.show', $order) }}" class="text-emerald-700 underline" data-testid="shop-admin-open-restaurant-{{ $order->id }}">{{ __('Open') }}</a>
                                    </td>
                                </tr>
                                @continue
                            @endif
                            <tr class="align-top" data-testid="shop-admin-row-{{ $order->id }}">
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-700">#{{ $order->id }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">
                                    {{ $order->created_at->locale(app()->getLocale())->isoFormat('D. MMM, HH:mm') }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-800">
                                        {{ $order->name ?: '—' }}
                                    </div>
                                    @if ($order->email)
                                        <div class="text-xs text-gray-500">{{ $order->email }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($order->order_source === \App\Enums\OrderSource::Admin)
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-gray-600">{{ __('shop.admin.badge_admin') }}</span>
                                        @endif
                                        @if ($order->customer_type === \App\Enums\CustomerType::Business)
                                            <span class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    {{ $order->fulfillment_mode === 'delivery' ? __('shop.fulfillment_delivery') : __('shop.fulfillment_pickup') }}
                                    @if ($order->fulfillment_mode === 'delivery')
                                        @if ($order->delivery_street)
                                            <div class="text-xs text-gray-500">{{ $order->delivery_street }}@if ($order->delivery_postal_code), {{ $order->delivery_postal_code }}@endif</div>
                                        @endif
                                    @else
                                        @php($pickup = \App\Support\PickupLocations::find($order->pickup_location))
                                        @if ($pickup)
                                            <div class="text-xs text-gray-500">{{ $pickup['street'] }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-700">
                                    <div class="whitespace-nowrap">{{ $order->expected_delivery_date?->locale(app()->getLocale())->isoFormat('dd, D. MMM') ?? '—' }}</div>
                                    <form method="POST" action="{{ route('app.admin.shop-orders.delivery-window', $order) }}"
                                          class="mt-1 flex items-center gap-1"
                                          data-testid="shop-admin-window-form-{{ $order->id }}">
                                        @csrf
                                        <input type="time" name="window_from" value="{{ substr((string) $order->delivery_window_from, 0, 5) }}"
                                               aria-label="{{ __('shop.admin.window_from') }}"
                                               class="w-[5.5rem] rounded border-gray-300 px-1 py-0.5 text-xs tabular-nums" />
                                        <span class="text-gray-400">–</span>
                                        <input type="time" name="window_to" value="{{ substr((string) $order->delivery_window_to, 0, 5) }}"
                                               aria-label="{{ __('shop.admin.window_to') }}"
                                               class="w-[5.5rem] rounded border-gray-300 px-1 py-0.5 text-xs tabular-nums" />
                                        <button type="submit" class="rounded bg-gray-800 px-2 py-0.5 text-xs font-semibold text-white transition hover:bg-gray-700"
                                                data-testid="shop-admin-window-save-{{ $order->id }}">
                                            {{ __('shop.admin.window_save') }}
                                        </button>
                                    </form>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right font-medium tabular-nums text-gray-800">
                                    @eur($order->total_amount_eur)
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-gray-700" data-testid="shop-admin-variant-count-{{ $order->id }}">
                                    @php($variantCount = $order->items->whereNotNull('variant_id')->count())
                                    @if ($variantCount > 0)
                                        {{ $variantCount }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($order->coupon_code)
                                        <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-mono text-xs font-medium text-emerald-800">{{ $order->coupon_code }}</span>
                                        @if ((float) $order->discount_eur > 0)
                                            <div class="mt-0.5 text-[11px] tabular-nums text-gray-500">−@eur($order->discount_eur)</div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2">
                                    <x-shop-order-status :order="$order" />
                                    @if ((int) $order->payment_attempts > 0 && $order->status !== \App\Enums\ShopOrderStatus::Paid && $order->status !== \App\Enums\ShopOrderStatus::Fulfilled)
                                        <div class="mt-1 text-[11px] font-semibold text-chapter-meat">
                                            {{ trans_choice('shop.admin.failed_attempts', (int) $order->payment_attempts, ['count' => (int) $order->payment_attempts]) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    <a href="{{ route('app.admin.shop-orders.show', $order) }}" class="text-emerald-700 underline" data-testid="shop-admin-open-{{ $order->id }}">{{ __('Open') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-6 text-center text-gray-500">{{ __('shop.admin.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $orders->links() }}</div>
        </div>
    </div>
    <script>
    function adminOrderCalendar(config) {
        return {
            calOpen: false,
            pickFrom: '',
            month: config.month,               // 'YYYY-MM'
            monthUrl: config.monthUrl,
            monthNames: config.monthNames,
            selFrom: config.selFrom,
            selTo: config.selTo || config.selFrom,
            loading: false,
            cache: { [config.month]: config.initialDays },

            days() { return this.cache[this.month] || []; },

            monthLabel() {
                const [y, m] = this.month.split('-').map(Number);
                return this.monthNames[m - 1] + ' ' + y;
            },

            // Monday-first grid: pad the first week, chunk by 7.
            weeks() {
                const days = this.days();
                if (!days.length) return [];
                const first = new Date(days[0].iso + 'T00:00:00');
                const blanks = (first.getDay() + 6) % 7;
                const cells = Array(blanks).fill(null).concat(days);
                const out = [];
                for (let i = 0; i < cells.length; i += 7) out.push(cells.slice(i, i + 7));
                return out;
            },

            shiftMonth(delta) {
                let [y, m] = this.month.split('-').map(Number);
                m += delta;
                if (m < 1) { m = 12; y--; }
                if (m > 12) { m = 1; y++; }
                this.month = y + '-' + String(m).padStart(2, '0');
                this.fetchMonth(this.month);
            },
            prevMonth() { this.shiftMonth(-1); },
            nextMonth() { this.shiftMonth(1); },

            fetchMonth(month) {
                if (this.cache[month]) return;
                this.loading = true;
                fetch(this.monthUrl + '?month=' + month, { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.json())
                    .then((d) => { this.cache[month] = d.days || []; })
                    .catch(() => { this.cache[month] = []; })
                    .finally(() => { this.loading = false; });
            },

            toneFor(day) {
                const tone = day.orders > 0
                    ? 'bg-emerald-100 text-emerald-900 hover:bg-emerald-200'
                    : (day.stock > 0 ? 'bg-amber-50 text-amber-800 hover:bg-amber-100' : 'text-gray-400 hover:bg-gray-100');
                const sel = (this.selFrom && day.iso >= this.selFrom && day.iso <= (this.selTo || this.selFrom))
                    ? ' ring-2 ring-gray-900 font-semibold' : '';
                const picking = this.pickFrom === day.iso ? ' ring-2 ring-gray-800' : '';
                return tone + sel + picking;
            },

            dayTitle(day) {
                const orders = (day.orders === 1 ? @js(trans_choice('shop.admin.cal_orders', 1, ['count' => 1])) : @js(trans_choice('shop.admin.cal_orders', 2, ['count' => ':count'])).replace(':count', day.orders));
                const stock = (day.stock === 1 ? @js(trans_choice('shop.admin.cal_stock', 1, ['count' => 1])) : @js(trans_choice('shop.admin.cal_stock', 2, ['count' => ':count'])).replace(':count', day.stock));
                return day.iso + ' · ' + orders + ' · ' + stock;
            },

            pick(iso) {
                if (!this.pickFrom) { this.pickFrom = iso; return; }
                let a = this.pickFrom, b = iso;
                if (b < a) [a, b] = [b, a];
                this.apply(a, b);
            },
            apply(a, b) {
                this.$refs.deliveryFrom.value = a;
                this.$refs.deliveryTo.value = b;
                this.pickFrom = '';
                this.calOpen = false;
                this.$refs.filterForm.requestSubmit();
            },
        };
    }
    </script>
</x-admin-layout>
