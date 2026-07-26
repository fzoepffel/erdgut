<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.products.csv.preview_title') }}
        </h1>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Summary counts --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-md bg-emerald-50 p-4">
                    <div class="text-2xl font-semibold text-emerald-800">{{ $result['summary']['to_create'] }}</div>
                    <div class="text-sm text-emerald-700">{{ __('admin.products.csv.to_create') }}</div>
                </div>
                <div class="rounded-md bg-blue-50 p-4">
                    <div class="text-2xl font-semibold text-blue-800">{{ $result['summary']['to_update'] }}</div>
                    <div class="text-sm text-blue-700">{{ __('admin.products.csv.to_update') }}</div>
                </div>
                <div class="rounded-md bg-gray-50 p-4">
                    <div class="text-2xl font-semibold text-gray-800">{{ count($result['summary']['new_groups']) }}</div>
                    <div class="text-sm text-gray-600">{{ __('admin.products.csv.new_groups') }}</div>
                </div>
                <div class="rounded-md bg-amber-50 p-4">
                    <div class="text-2xl font-semibold text-amber-800">{{ $result['summary']['weekly_changes'] }}</div>
                    <div class="text-sm text-amber-700">{{ __('admin.products.csv.weekly_changes') }}</div>
                </div>
            </div>

            {{-- New groups --}}
            @if (! empty($result['summary']['new_groups']))
                <div class="rounded-md border border-gray-200 bg-white p-4">
                    <h2 class="mb-2 text-sm font-semibold text-gray-800">{{ __('admin.products.csv.new_groups') }}</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($result['summary']['new_groups'] as $group)
                            <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ $group }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Errors (skipped rows) --}}
            @if (! empty($result['errors']))
                <div class="rounded-md border border-red-200 bg-red-50 p-4">
                    <h2 class="mb-2 text-sm font-semibold text-red-800">{{ __('admin.products.csv.skipped_rows') }}</h2>
                    <ul class="space-y-1 text-sm text-red-700">
                        @foreach ($result['errors'] as $error)
                            <li>{{ __('admin.products.csv.row') }} {{ $error['row'] }}: {{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Product change list --}}
            @if (! empty($result['products']))
                <div class="overflow-hidden rounded-md border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.row') }}</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.action') }}</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.product') }}</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($result['products'] as $product)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $product['row'] }}</td>
                                    <td class="px-4 py-2">
                                        @if ($product['action'] === 'create')
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('admin.products.csv.create') }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">{{ __('admin.products.csv.update') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-medium text-gray-800">{{ $product['name'] }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        @if (! empty($product['weekly']))
                                            <span class="mr-2">{{ trans_choice('admin.products.csv.weeks_set', count($product['weekly']), ['count' => count($product['weekly'])]) }}</span>
                                        @endif
                                        @foreach ($product['warnings'] as $warning)
                                            <span class="block text-xs text-amber-700">{{ $warning }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('app.admin.products.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    {{ __('Cancel') }}
                </a>
                @if (! empty($result['products']))
                    <form method="POST" action="{{ route('app.admin.products.import.confirm') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="ext" value="{{ $ext }}">
                        <button type="submit" class="btn-solid-sm">
                            {{ __('admin.products.csv.confirm') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
