<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New product') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @include('admin.products._form', [
                        'product' => null,
                        'producers' => $producers,
                        'categories' => $categories,
                        'action' => route('app.admin.products.store'),
                        'method' => 'post',
                    ])
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
