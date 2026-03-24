<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.upcoming_title', ['days' => $windowDays]) }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">{{ __('admin.operations.upcoming_intro', ['days' => $windowDays]) }}</p>
            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    @if ($subscriptions->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('admin.operations.upcoming_empty') }}</p>
                    @else
                        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">{{ __('admin.operations.upcoming_table_caption') }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th scope="col" class="py-2 pe-4">ID</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.user') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.plan') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.next_charge') }}</th>
                                        <th scope="col" class="py-2 pe-0">{{ __('admin.operations.expected_amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($subscriptions as $subscription)
                                        <tr>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $subscription->id }}</td>
                                            <td class="py-3 pe-4 text-gray-900">{{ $subscription->user?->email ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $subscription->plan->value }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $subscription->next_charge_date?->toDateString() ?? '—' }}</td>
                                            <td class="py-3 pe-0 tabular-nums text-gray-600">{{ number_format($subscription->expectedChargeAmount(), 2) }} €</td>
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
