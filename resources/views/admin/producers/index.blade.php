@php
    $hasFilters = filled(trim((string) request('q')));
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Producers') }}
            </h2>
            <a
                href="{{ route('app.admin.producers.create') }}"
                class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
            >
                {{ __('Add producer') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12" x-data="{ deleteAction: '', deleteLabel: '' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 space-y-4 text-gray-900">
                    <form method="get" action="{{ route('app.admin.producers.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1 sm:max-w-md">
                            <label for="admin-producers-q" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Search') }}</label>
                            <input
                                id="admin-producers-q"
                                type="search"
                                name="q"
                                value="{{ old('q', request('q')) }}"
                                maxlength="120"
                                placeholder="{{ __('admin.catalog.search_producers_placeholder') }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                            >
                                {{ __('admin.catalog.apply_filters') }}
                            </button>
                            @if ($hasFilters)
                                <a
                                    href="{{ route('app.admin.producers.index') }}"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                                >
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                        </div>
                    </form>
                    <p class="text-xs text-gray-500">{{ __('admin.catalog.producers_search_hint') }}</p>

                    @if ($producers->isEmpty())
                        <p class="text-sm text-gray-600">
                            {{ $hasFilters ? __('admin.catalog.no_producers_match') : __('No producers yet.') }}
                        </p>
                    @else
                        <div class="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <caption class="sr-only">
                                    {{ __('admin.catalog.producers_table_caption') }}
                                </caption>
                                <thead>
                                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <th scope="col" class="py-2 pe-4">{{ __('Name') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Region') }}</th>
                                        <th scope="col" class="py-2 pe-4">{{ __('Products') }}</th>
                                        <th scope="col" class="py-2 pe-0 text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($producers as $producer)
                                        <tr>
                                            <td class="py-3 pe-4 font-medium text-gray-900">{{ $producer->name }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $producer->region ?? '—' }}</td>
                                            <td class="py-3 pe-4 text-gray-600">{{ $producer->products_count }}</td>
                                            <td class="py-3 pe-0 text-end space-x-2 whitespace-nowrap">
                                                <a href="{{ route('app.admin.producers.edit', $producer) }}" class="text-emerald-700 hover:text-emerald-900 font-medium">{{ __('Edit') }}</a>
                                                <button
                                                    type="button"
                                                    class="text-red-600 hover:text-red-800 font-medium"
                                                    x-on:click.prevent="deleteAction = @js(route('app.admin.producers.destroy', $producer)); deleteLabel = @js($producer->name); $dispatch('open-modal', 'confirm-admin-producer-delete')"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $producers->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <x-modal name="confirm-admin-producer-delete" focusable>
            <form method="post" x-bind:action="deleteAction" class="p-6" aria-labelledby="admin-delete-producer-title">
                @csrf
                @method('delete')

                <h2 id="admin-delete-producer-title" class="text-lg font-medium text-gray-900">
                    {{ __('admin.catalog.delete_producer_title') }}
                </h2>

                <p class="mt-2 text-sm text-gray-600">
                    {{ __('admin.catalog.delete_producer_body') }}
                </p>
                <p class="mt-1 text-sm font-medium text-gray-900">
                    <span class="sr-only">{{ __('Name') }}: </span>
                    <span x-text="deleteLabel"></span>
                </p>

                <div class="mt-6 flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <x-danger-button type="submit">
                        {{ __('admin.catalog.confirm_delete') }}
                    </x-danger-button>
                </div>
            </form>
        </x-modal>
    </div>
</x-admin-layout>
