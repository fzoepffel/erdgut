<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status') === 'current-box-missing')
                <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    No current week box is available yet.
                </p>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold">Next deliveries</h3>
                            <a href="{{ route('app.deliveries.index') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                Manage all
                            </a>
                        </div>

                        @if ($deliveryCards->isEmpty())
                            <p class="mt-3 text-sm text-gray-600">No upcoming deliveries yet.</p>
                        @else
                            <ul class="mt-4 space-y-3">
                                @foreach ($deliveryCards as $card)
                                    @php($delivery = $card['delivery'])
                                    @php($box = $card['box'])
                                    <li class="rounded-md border border-gray-200 p-4 shadow-sm">
                                        <div class="flex items-center justify-between">
                                            <p class="font-medium">{{ $delivery->scheduled_for_date->format('D, d M Y') }}</p>
                                            <x-status-badge :value="$delivery->status" type="delivery" />
                                        </div>
                                        <p class="mt-1 text-sm text-gray-600">{{ $delivery->timeslot }}</p>
                                        <p class="text-sm text-gray-600">
                                            {{ $delivery->address?->city ? 'To '.$delivery->address->city : 'Address not set' }}
                                        </p>
                                        @if ($card['deadline'])
                                            <p class="mt-2 text-xs text-gray-500">
                                                Modify by: {{ $card['deadline']->format('D, d M Y H:i') }}
                                            </p>
                                        @else
                                            <p class="mt-2 text-xs text-gray-500">Modify by: not available yet</p>
                                        @endif

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($box && $card['canModifyBox'])
                                                <a href="{{ route('app.boxes.show', ['box' => $box, 'mode' => 'edit']) }}" class="rounded-md border border-emerald-600 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                                    Modify box
                                                </a>
                                            @endif

                                            <a href="{{ route('app.deliveries.show', $delivery) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                Change delivery
                                            </a>

                                            @if ($delivery->status->value === 'scheduled')
                                                <form method="POST" action="{{ route('app.deliveries.skip', $delivery) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-md border border-amber-400 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">
                                                        Skip week
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('app.deliveries.gift', $delivery) }}" class="rounded-md border border-purple-400 px-3 py-1.5 text-xs font-semibold text-purple-700 hover:bg-purple-50">
                                                Gift box
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold">Current week box</h3>

                        @if ($currentBox)
                            <p class="mt-2 text-sm text-gray-600">
                                Week of {{ $currentBox->week_start_date->format('d M Y') }}
                            </p>
                            <p class="mt-2">
                                <a href="{{ route('app.boxes.current') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">
                                    View current box
                                </a>
                            </p>
                            <ul class="mt-4 space-y-2">
                                @forelse ($currentBox->items as $item)
                                    <li class="flex items-center justify-between text-sm">
                                        <span>{{ $item->product?->name ?? 'Unknown product' }}</span>
                                        <span class="text-gray-600">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span>
                                    </li>
                                @empty
                                    <li class="text-sm text-gray-600">No items in the current box yet.</li>
                                @endforelse
                            </ul>
                            <p class="mt-4 text-sm font-semibold">
                                Estimated value: EUR {{ number_format((float) $currentBox->estimated_value, 2) }}
                            </p>
                        @else
                            <p class="mt-3 text-sm text-gray-600">No box for this week.</p>
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
                                        <p class="text-sm text-gray-600">
                                            Status:
                                            <x-status-badge :value="$box->status" type="box" />
                                        </p>
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
