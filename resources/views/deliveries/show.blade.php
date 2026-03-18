<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Change delivery') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="font-medium">Delivery on {{ $delivery->scheduled_for_date->format('d M Y') }}</p>
                    <p class="mt-2 text-sm text-gray-600">Delivery override options are coming soon.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
