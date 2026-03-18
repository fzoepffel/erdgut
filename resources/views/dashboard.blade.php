<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold">Next deliveries</h3>

                        @if ($upcomingDeliveries->isEmpty())
                            <p class="mt-3 text-sm text-gray-600">No upcoming deliveries yet.</p>
                        @else
                            <ul class="mt-4 space-y-3">
                                @foreach ($upcomingDeliveries as $delivery)
                                    <li class="rounded-md border border-gray-200 p-3">
                                        <p class="font-medium">{{ $delivery->scheduled_for_date->format('D, d M Y') }}</p>
                                        <p class="text-sm text-gray-600">{{ $delivery->timeslot }}</p>
                                        <p class="text-sm text-gray-600">
                                            {{ $delivery->address?->city ? 'To '.$delivery->address->city : 'Address not set' }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold">Current and upcoming boxes</h3>

                        @if ($upcomingBoxes->isEmpty())
                            <p class="mt-3 text-sm text-gray-600">No boxes available yet.</p>
                        @else
                            <ul class="mt-4 space-y-3">
                                @foreach ($upcomingBoxes as $box)
                                    <li class="rounded-md border border-gray-200 p-3">
                                        <p class="font-medium">Week of {{ $box->week_start_date->format('d M Y') }}</p>
                                        <p class="text-sm text-gray-600">Status: {{ $box->status->value }}</p>
                                        <p class="text-sm text-gray-600">
                                            Estimated value: EUR {{ number_format((float) $box->estimated_value, 2) }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
