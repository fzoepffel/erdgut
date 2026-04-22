@php
    $hasFilters = filled(request('status')) || filled(request('date_from')) || filled(request('date_to')) || request('trial_only') === '1';
    $trialOnly = request('trial_only') === '1';
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.deliveries_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status') === 'admin-delivery-status-updated')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('admin.deliveries.status.updated_notice') }}
                </p>
            @endif
            @if (session('status') === 'admin-deliveries-synced')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('admin.deliveries.synced_notice', ['count' => session('admin_deliveries_sync_count', 0), 'from' => session('admin_deliveries_sync_from', '')]) }}
                </p>
            @endif
            @if (session('status') === 'admin-delivery-created')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('admin.deliveries.created_notice') }}
                </p>
            @endif
            @if (session('status') === 'admin-delivery-updated')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('admin.deliveries.updated_notice') }}
                </p>
            @endif

            <div class="flex flex-col gap-3 rounded-lg border border-gray-100 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <form method="post" action="{{ route('app.admin.deliveries.sync-from-boxes') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div>
                        <label for="sync-from" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.sync_from_label') }}</label>
                        <input id="sync-from" type="date" name="from" value="{{ old('from') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-auto" />
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500">
                        {{ __('admin.deliveries.sync_button') }}
                    </button>
                </form>
                <a href="{{ route('app.admin.deliveries.create') }}" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                    {{ __('admin.deliveries.new_delivery') }}
                </a>
            </div>

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
                        <div class="flex items-center gap-2">
                            <input id="admin-del-trial-only" type="checkbox" name="trial_only" value="1" @checked($trialOnly) class="rounded border-gray-300 text-emerald-700 shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            <label for="admin-del-trial-only" class="text-sm text-gray-700">{{ __('Trial only') }}</label>
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
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.deliveries.status.column') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.address_summary') }}</th>
                                        <th scope="col" class="py-2 pe-0">{{ __('admin.deliveries.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($deliveries as $delivery)
                                        <tr>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $delivery->id }}</td>
                                            <td class="py-3 pe-4 text-gray-900">
                                                {{ $delivery->user?->email ?? '—' }}
                                                @if ($delivery->order_id)
                                                    <span class="ml-1 inline-flex items-center rounded bg-category-produce/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-category-produce" data-testid="admin-delivery-trial-badge">{{ __('Trial') }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $delivery->scheduled_for_date?->toDateString() ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $delivery->status->value }}</td>
                                            <td class="py-3 pe-4">
                                                <form method="POST" action="{{ route('app.admin.deliveries.status.update', $delivery) }}" class="flex items-center gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <label class="sr-only" for="delivery-status-{{ $delivery->id }}">
                                                        {{ __('admin.deliveries.status.label') }}
                                                    </label>
                                                    <select
                                                        id="delivery-status-{{ $delivery->id }}"
                                                        name="status"
                                                        class="block rounded-md border-gray-300 text-xs shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                                    >
                                                        @foreach ($deliveryStatuses as $st)
                                                            <option value="{{ $st->value }}" @selected($delivery->status === $st)>
                                                                {{ __('admin.deliveries.status.'.$st->value) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="inline-flex items-center rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-gray-800">
                                                        {{ __('admin.deliveries.status.save') }}
                                                    </button>
                                                </form>
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                @if ($delivery->address)
                                                    {{ $delivery->address->street_line_1 }}, {{ $delivery->address->postal_code }} {{ $delivery->address->city }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-3 pe-0">
                                                <a href="{{ route('app.admin.deliveries.edit', $delivery) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900">
                                                    {{ __('admin.deliveries.edit_link') }}
                                                </a>
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
