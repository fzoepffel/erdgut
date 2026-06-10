<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.subcategories.edit') }}: {{ $subcategory->label_de }}
            </h1>
            <a href="{{ route('app.admin.subcategories.index') }}" class="text-sm text-gray-500 underline underline-offset-2 hover:text-gray-800 hover:no-underline">
                ← {{ __('admin.subcategories.title') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="p-6">
                    @include('admin.subcategories._form', [
                        'action' => route('app.admin.subcategories.update', $subcategory),
                        'method' => 'put',
                        'subcategory' => $subcategory,
                    ])
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
