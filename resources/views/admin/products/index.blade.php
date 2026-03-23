<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Products') }}
            </h2>
            <a href="{{ route('app.admin.products.create') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Add product') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 overflow-x-auto">
                    @if ($products->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('No products yet.') }}</p>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <th class="py-2 pe-4">{{ __('Name') }}</th>
                                    <th class="py-2 pe-4">{{ __('Producer') }}</th>
                                    <th class="py-2 pe-4">{{ __('Category') }}</th>
                                    <th class="py-2 pe-4">{{ __('Price') }}</th>
                                    <th class="py-2 pe-4">{{ __('Shop') }}</th>
                                    <th class="py-2 pe-4 text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($products as $product)
                                    <tr>
                                        <td class="py-3 pe-4 font-medium text-gray-900">{{ $product->name }}</td>
                                        <td class="py-3 pe-4 text-gray-600">{{ $product->producer?->name ?? '—' }}</td>
                                        <td class="py-3 pe-4 text-gray-600">{{ $product->category }}</td>
                                        <td class="py-3 pe-4 text-gray-600">{{ number_format((float) $product->unit_price, 2) }}</td>
                                        <td class="py-3 pe-4">
                                            @if ($product->is_available)
                                                <span class="text-green-700">{{ __('Available') }}</span>
                                            @else
                                                <span class="text-gray-500">{{ __('Hidden') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pe-0 text-end space-x-2 whitespace-nowrap">
                                            <a href="{{ route('app.admin.products.edit', $product) }}" class="text-indigo-600 hover:text-indigo-800">{{ __('Edit') }}</a>
                                            <form action="{{ route('app.admin.products.destroy', $product) }}" method="post" class="inline" onsubmit="return confirm(@json(__('Delete this product?')));">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-4">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
