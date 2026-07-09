<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.admin_index_title') }}</h1>
            <span class="text-sm text-gray-500">{{ __('restaurant.admin_index_subtitle', ['count' => $profiles->total()]) }}</span>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('restaurant.field_business_name') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.field_contact_name') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.field_email') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.admin_status') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.admin_applied_at') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($profiles as $profile)
                            @php $status = $profile->verification_status->value; @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $profile->business_name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $profile->contact_name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $profile->user?->email ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'bg-amber-100 text-amber-800' => $status === 'pending',
                                        'bg-green-100 text-green-800' => $status === 'verified',
                                        'bg-gray-100 text-gray-600' => $status === 'rejected',
                                    ])>{{ __('restaurant.status_'.$status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $profile->created_at?->format('d.m.Y H:i') }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($status === 'pending')
                                        <a href="{{ $profile->review_url }}" class="font-medium text-indigo-600 hover:text-indigo-800">{{ __('restaurant.admin_review') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('restaurant.admin_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $profiles->links() }}</div>
        </div>
    </div>
</x-admin-layout>
