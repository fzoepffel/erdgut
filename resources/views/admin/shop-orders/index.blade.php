<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.heading') }}</h1>
                <span class="text-sm text-gray-500">{{ __('shop.admin.subheading', ['count' => $orders->total()]) }}</span>
            </div>
            <a href="{{ route('app.admin.shop-orders.create') }}"
               class="inline-flex items-center gap-1.5 rounded-sm bg-gray-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700"
               data-testid="shop-admin-create-link">
                + {{ __('shop.admin.action_create_order') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <form method="get" class="flex flex-wrap items-end gap-3">
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
                        <label for="delivery_date" class="mb-1 block text-xs font-medium text-gray-600">{{ __('shop.admin.filter_delivery_date') }}</label>
                        <input type="date" id="delivery_date" name="delivery_date" value="{{ $deliveryDate }}" class="rounded-md border-gray-300 text-sm">
                    </div>
                    <x-primary-button size="sm">{{ __('Filter') }}</x-primary-button>
                    @if ($status !== 'finalized' || $deliveryDate !== '')
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
                            <th class="px-3 py-2">{{ __('shop.admin.col_coupon') }}</th>
                            <th class="px-3 py-2">{{ __('shop.admin.col_status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orders as $order)
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
                                <td colspan="10" class="px-3 py-6 text-center text-gray-500">{{ __('shop.admin.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $orders->links() }}</div>
        </div>
    </div>
</x-admin-layout>
