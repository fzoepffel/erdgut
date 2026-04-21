<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Trial orders') }}</h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <form method="get" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="status" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Status') }}</label>
                        <select id="status" name="status" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white">{{ __('Filter') }}</button>
                    @if ($status)
                        <a href="{{ route('app.admin.orders.index') }}" class="text-sm text-gray-600 underline">{{ __('Reset') }}</a>
                    @endif
                </form>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Order') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Customer') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Status') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Size') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Scheduled') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('City') }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Created') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($orders as $order)
                            <tr>
                                <td class="px-3 py-2 font-mono">#{{ $order->id }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $order->user?->name ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->user?->email }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $order->status->label() }}</td>
                                <td class="px-3 py-2">{{ ucfirst((string) $order->box_size) }}</td>
                                <td class="px-3 py-2">{{ $order->scheduled_for_date?->format('D d M') ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $order->address?->city }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $order->created_at?->format('d M H:i') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('app.admin.orders.show', $order) }}" class="text-emerald-700 underline">{{ __('Open') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-6 text-center text-gray-500">{{ __('No orders found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $orders->links() }}</div>
        </div>
    </div>
</x-admin-layout>
