@php
    $hasFilters = filled(request('status')) || filled(request('date_from')) || filled(request('date_to'));
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.deliveries_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    <form method="get" action="{{ route('app.admin.deliveries.index') }}" class="flex flex-col flex-wrap gap-3 sm:flex-row sm:items-end">
                        <div class="w-full sm:w-auto">
                            <label for="admin-del-status" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.status') }}</label>
                            <select id="admin-del-status" name="status" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-44">
                                <option value="">{{ __('admin.operations.filter_all') }}</option>
                                @foreach ($deliveryStatuses as $st)
                                    <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-del-from" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.date_from') }}</label>
                            <input id="admin-del-from" type="date" name="date_from" value="{{ request('date_from') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-del-to" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.date_to') }}</label>
                            <input id="admin-del-to" type="date" name="date_to" value="{{ request('date_to') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                {{ __('admin.catalog.apply_filters') }}
                            </button>
                            @if ($hasFilters)
                                <a href="{{ route('app.admin.deliveries.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($deliveries->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('admin.operations.deliveries_empty') }}</p>
                    @else
                        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">{{ __('admin.operations.deliveries_table_caption') }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th scope="col" class="py-2 pe-4">ID</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.user') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.scheduled_for') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.status') }}</th>
                                        <th scope="col" class="py-2 pe-0">{{ __('admin.operations.address_summary') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($deliveries as $delivery)
                                        <tr>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $delivery->id }}</td>
                                            <td class="py-3 pe-4 text-gray-900">{{ $delivery->user?->email ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $delivery->scheduled_for_date?->toDateString() ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $delivery->status->value }}</td>
                                            <td class="py-3 pe-0 text-gray-600">
                                                @if ($delivery->address)
                                                    {{ $delivery->address->street_line_1 }}, {{ $delivery->address->postal_code }} {{ $delivery->address->city }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="pt-2">{{ $deliveries->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
