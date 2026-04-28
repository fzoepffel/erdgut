@php
    $hasFilters = filled(request('status')) || filled(request('week'));
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.trial_box_setup.select_title') }}
            </h2>
            <a href="{{ route('app.admin.boxes.index') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.trial_box_setup.link_weekly_boxes') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                <form method="get" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label for="week" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.trial_box_setup.week_label') }}</label>
                        <input id="week" type="date" name="week" value="{{ request('week', $week->toDateString()) }}" class="rounded-md border-gray-300 text-sm" />
                        <p class="mt-1 text-[11px] text-gray-500">{{ __('admin.trial_box_setup.week_hint') }}</p>
                    </div>
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
                    @if ($hasFilters)
                        <a href="{{ route('app.admin.trial-boxes.setup.select') }}" class="text-sm text-gray-600 underline">{{ __('Reset') }}</a>
                    @endif
                </form>
            </div>

            <form method="get" action="{{ route('app.admin.trial-boxes.setup.show', ['week' => request('week', $week->toDateString())]) }}">
                <input type="hidden" name="week" value="{{ request('week', $week->toDateString()) }}" />
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">
                                    <span class="sr-only">{{ __('Select') }}</span>
                                </th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Order') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Customer') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Status') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Size') }}</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">{{ __('Created') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($orders as $order)
                                <tr>
                                    <td class="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            name="order_ids[]"
                                            value="{{ $order->id }}"
                                            class="rounded border-gray-300 text-emerald-700 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                        />
                                    </td>
                                    <td class="px-3 py-2 font-mono">#{{ $order->id }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium">{{ $order->user?->name ?? '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ $order->user?->email }}</div>
                                    </td>
                                    <td class="px-3 py-2"><x-status-badge :value="$order->status" type="order" /></td>
                                    <td class="px-3 py-2">{{ __(ucfirst((string) $order->box_size)) }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $order->created_at?->format('d M H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">{{ __('admin.trial_box_setup.no_orders') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <div>{{ $orders->links() }}</div>
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                    >
                        {{ __('admin.trial_box_setup.cta_setup_selected') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>

