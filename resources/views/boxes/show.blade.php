<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Modify box') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="font-medium">Box for week of {{ $box->week_start_date->format('d M Y') }}</p>
                    <p class="mt-2 text-sm text-gray-600">Box editing details are coming soon.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
