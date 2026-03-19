<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Box details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="font-medium">Box for week of {{ $box->week_start_date->format('d M Y') }}</p>
                        <x-status-badge :value="$box->status" type="box" />
                    </div>

                    <p class="mt-3 text-sm text-gray-600">
                        @if ($modificationDeadline)
                            Modification deadline: {{ $modificationDeadline->format('D, d M Y H:i') }}
                        @else
                            Modification deadline not set.
                        @endif
                    </p>
                    <p class="mt-1 text-sm {{ $canModifyBox ? 'text-emerald-700' : 'text-gray-600' }}">
                        {{ $canModifyBox ? 'This box can still be modified.' : 'This box is currently not editable.' }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($isEditModeActive)
                            <a href="{{ route('app.boxes.show', $box) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                Back to view mode
                            </a>
                        @elseif ($canModifyBox)
                            <a href="{{ route('app.boxes.show', ['box' => $box, 'mode' => 'edit']) }}" class="inline-flex items-center rounded-md border border-emerald-600 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">
                                Edit box
                            </a>
                        @else
                            <span class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-500">
                                Edit mode unavailable
                            </span>
                        @endif
                    </div>

                    @if ($isEditModeBlocked)
                        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                            @if ($blockedReason === 'box-edit-status-not-editable')
                                Edit mode is blocked because this box status is not editable.
                            @elseif ($blockedReason === 'box-edit-deadline-expired')
                                Edit mode is blocked because the modification deadline has passed.
                            @else
                                Edit mode is currently unavailable for this box.
                            @endif
                        </p>
                    @endif

                    <div class="mt-6 rounded-md border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-600">Items</h3>
                            @if ($isEditModeActive)
                                <span class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Edit mode</span>
                            @endif
                        </div>

                        @if ($box->items->isEmpty())
                            <p class="mt-3 text-sm text-gray-600">No items in this box yet.</p>
                        @else
                            <ul class="mt-3 space-y-2">
                                @foreach ($box->items as $item)
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <div>
                                            <span>{{ $item->product?->name ?? 'Unknown product' }}</span>
                                            @if ($item->is_extra)
                                                <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Extra</span>
                                            @endif
                                        </div>
                                        @if ($isEditModeActive)
                                            <div class="flex items-center gap-2">
                                                <button type="button" disabled class="rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">-</button>
                                                <input
                                                    type="text"
                                                    value="{{ number_format((float) $item->quantity, 2) }}"
                                                    disabled
                                                    class="w-16 rounded border-gray-200 bg-gray-50 px-2 py-1 text-xs text-gray-500"
                                                />
                                                <span class="text-gray-500">{{ $item->unit }}</span>
                                                <button type="button" disabled class="rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">+</button>
                                                <button type="button" disabled class="rounded border border-gray-200 px-2 py-1 text-xs text-gray-400">Remove</button>
                                            </div>
                                        @else
                                            <span class="text-gray-600">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($isEditModeActive)
                            <p class="mt-3 text-xs text-gray-500">
                                Quantity and remove actions are scaffolded and will be activated in ERD-065/066.
                            </p>
                        @endif
                    </div>

                    <p class="mt-5 text-sm font-semibold">
                        Estimated value: EUR {{ number_format((float) $box->estimated_value, 2) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
