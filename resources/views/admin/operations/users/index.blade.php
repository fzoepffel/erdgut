@php
    $hasFilters = filled(trim((string) request('q')));
@endphp

<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.operations.users_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    <form method="get" action="{{ route('app.admin.users.index') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <div class="min-w-0 flex-1 sm:min-w-[12rem]">
                            <label for="admin-users-q" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.filter_email') }}</label>
                            <input
                                id="admin-users-q"
                                type="search"
                                name="q"
                                value="{{ old('q', request('q')) }}"
                                maxlength="255"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                                {{ __('admin.catalog.apply_filters') }}
                            </button>
                            @if ($hasFilters)
                                <a href="{{ route('app.admin.users.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($users->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('admin.operations.users_empty') }}</p>
                    @else
                        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">{{ __('admin.operations.users_table_caption') }}</caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th scope="col" class="py-2 pe-4">ID</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Email') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('admin.operations.email_verified') }}</th>
                                        <th scope="col" class="py-2 pe-0">{{ __('admin.operations.created_at') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($users as $user)
                                        <tr>
                                            <td class="py-3 pe-4 tabular-nums text-gray-600">{{ $user->id }}</td>
                                            <td class="py-3 pe-4 font-medium text-gray-900">{{ $user->email }}</td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                {{ $user->email_verified_at ? $user->email_verified_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}
                                            </td>
                                            <td class="py-3 pe-0 text-gray-600">{{ $user->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="pt-2">{{ $users->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
