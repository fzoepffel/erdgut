@php
    $hasFilters = request()->filled('missing_payment') || request()->filled('paused_exhausted');
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.operations.subscriptions_title') }}
            </h2>
            <a href="{{ route('app.admin.subscriptions.create') }}" class="btn-solid-sm">
                {{ __('admin.subscriptions.new_action') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    <form method="get" action="{{ route('app.admin.subscriptions.index') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="w-full sm:w-auto">
                            <label for="admin-subs-missing" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.subscriptions_filter_missing_payment') }}</label>
                            <select id="admin-subs-missing" name="missing_payment" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-56">
                                <option value="">{{ __('admin.operations.filter_all') }}</option>
                                <option value="1" @selected(request('missing_payment') === '1')>{{ __('Yes') }}</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-auto">
                            <label for="admin-subs-exhausted" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.subscriptions_filter_paused_exhausted') }}</label>
                            <select id="admin-subs-exhausted" name="paused_exhausted" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 sm:w-56">
                                <option value="">{{ __('admin.operations.filter_all') }}</option>
                                <option value="1" @selected(request('paused_exhausted') === '1')>{{ __('Yes') }}</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <x-primary-button size="sm">
                                {{ __('admin.catalog.apply_filters') }}
                            </x-primary-button>
                            @if ($hasFilters)
                                <a href="{{ route('app.admin.subscriptions.index') }}" class="btn-ghost-sm">
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($subscriptions->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('admin.operations.subscriptions_empty') }}</p>
                    @else
                        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">{{ __('admin.operations.subscriptions_table_caption') }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th scope="col" class="py-2 pe-4">ID</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.user') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.plan') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.status') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.next_charge') }}</th>
                                        <th scope="col" class="py-2 pe-0">{{ __('admin.operations.pause_window') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($subscriptions as $subscription)
                                        <tr>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $subscription->id }}</td>
                                            <td class="py-3 pe-4 text-gray-900">{{ $subscription->user?->email ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ __('plan.'.$subscription->plan->value) }}</td>
                                            <td class="py-3 pe-4"><x-status-badge :value="$subscription->status" type="subscription" /></td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $subscription->next_charge_date?->toDateString() ?? '—' }}</td>
                                            <td class="py-3 pe-0 text-gray-600">
                                                @if ($subscription->pause_from && $subscription->pause_until)
                                                    {{ $subscription->pause_from->toDateString() }} – {{ $subscription->pause_until->toDateString() }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="pt-2">{{ $subscriptions->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
