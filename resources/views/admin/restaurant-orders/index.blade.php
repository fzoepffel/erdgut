<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.admin_requests_title') }}</h1>
                <span class="text-sm text-gray-500">{{ __('restaurant.admin_orders_subtitle', ['count' => $orders->total()]) }}</span>
            </div>
            @if ($openRequests > 0)
                <a href="{{ route('app.admin.restaurant-orders.index', ['status' => 'submitted']) }}"
                   class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-200" data-testid="admin-open-requests">
                    {{ trans_choice('restaurant.admin_open_requests', $openRequests, ['count' => $openRequests]) }}
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
                <label for="status" class="text-sm text-gray-500">{{ __('restaurant.admin_status') }}</label>
                <select id="status" name="status" onchange="this.form.submit()" class="rounded border-gray-300 text-sm">
                    <option value="">{{ __('restaurant.admin_orders_all') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected($activeStatus === $s)>{{ __('restaurant.order_status_'.$s->value) }}</option>
                    @endforeach
                </select>
            </form>

            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">{{ __('restaurant.field_business_name') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.catalog_date_label') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.admin_status') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.admin_orders_items') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('restaurant.order_net_total') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orders as $order)
                            @php $status = $order->status->value; @endphp
                            {{-- Whole row opens the detail (the link column can sit
                                 off-screen on phones). --}}
                            <tr class="cursor-pointer hover:bg-gray-50"
                                onclick="window.location='{{ route('app.admin.restaurant-orders.show', $order) }}'">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    {{ $order->id }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $order->expected_delivery_date->format('d.m.Y') }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'bg-amber-100 text-amber-800' => $status === 'submitted',
                                        'bg-green-100 text-green-800' => $status === 'confirmed' || $status === 'fulfilled',
                                        'bg-gray-100 text-gray-600' => $status === 'cancelled' || $status === 'declined',
                                    ])>{{ __('restaurant.order_status_'.$status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $order->items_count }}</td>
                                <td class="px-4 py-3 text-right font-mono text-gray-700">@eur($order->items_total_net_eur)</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('app.admin.restaurant-orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800">{{ __('restaurant.admin_review') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">{{ __('restaurant.admin_orders_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>
        </div>
    </div>
</x-admin-layout>
