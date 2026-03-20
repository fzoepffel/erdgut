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

                    <div
                        class="mt-6"
                        @if ($isEditModeActive)
                            x-data="{
                                itemQuantities: {{ json_encode($box->items->keyBy('id')->map(fn ($i) => (float) $i->quantity)->toArray()) }},
                                estimatedValue: {{ (float) $box->estimated_value }},
                                removedItemIds: [],
                                totalItems: {{ $box->items->count() }},
                                updateBaseUrl: '{{ url('/app/boxes/'.$box->id.'/items') }}',
                                loading: null,
                                error: null,
                                async updateQuantity(itemId, delta) {
                                    this.loading = itemId;
                                    this.error = null;
                                    try {
                                        const res = await fetch(this.updateBaseUrl + '/' + itemId, {
                                            method: 'PATCH',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                            },
                                            body: JSON.stringify({ delta }),
                                        });
                                        const data = await res.json();
                                        if (!res.ok) {
                                            const msg = data?.errors?.delta?.[0] ?? data?.message ?? 'Update failed';
                                            throw new Error(msg);
                                        }
                                        this.itemQuantities[itemId] = data.quantity;
                                        this.estimatedValue = data.estimated_value;
                                    } catch (e) {
                                        this.error = e.message;
                                    } finally {
                                        this.loading = null;
                                    }
                                },
                                async removeItem(itemId) {
                                    this.loading = itemId;
                                    this.error = null;
                                    try {
                                        const res = await fetch(this.updateBaseUrl + '/' + itemId, {
                                            method: 'DELETE',
                                            headers: {
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                            },
                                        });
                                        const data = await res.json();
                                        if (!res.ok) {
                                            const msg = data?.message ?? 'Remove failed';
                                            throw new Error(msg);
                                        }
                                        this.removedItemIds.push(itemId);
                                        delete this.itemQuantities[itemId];
                                        this.estimatedValue = data.estimated_value;
                                    } catch (e) {
                                        this.error = e.message;
                                    } finally {
                                        this.loading = null;
                                    }
                                },
                            }"
                        @endif
                    >
                    <div class="rounded-md border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wider text-gray-600">Items</h3>
                            @if ($isEditModeActive)
                                <span class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Edit mode</span>
                            @endif
                        </div>

                        @if ($isEditModeActive)
                            <p x-show="error" x-text="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                        @endif

                        @if ($box->items->isEmpty())
                            <p class="mt-3 text-sm text-gray-600">No items in this box yet.</p>
                        @else
                            <p x-show="removedItemIds.length >= totalItems" class="mt-3 text-sm text-gray-600">No items in this box yet.</p>
                            <ul x-show="removedItemIds.length < totalItems" class="mt-3 space-y-2">
                                @foreach ($box->items as $item)
                                    <li x-show="!removedItemIds.includes({{ $item->id }})" class="flex items-center justify-between gap-3 text-sm">
                                        <div>
                                            <span>{{ $item->product?->name ?? 'Unknown product' }}</span>
                                            @if ($item->is_extra)
                                                <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Extra</span>
                                            @endif
                                        </div>
                                        @if ($isEditModeActive)
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    :disabled="loading === {{ $item->id }} || (itemQuantities[{{ $item->id }}] ?? {{ (float) $item->quantity }}) <= 1"
                                                    @click="updateQuantity({{ $item->id }}, -1)"
                                                    class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-transparent"
                                                >-</button>
                                                <span
                                                    class="w-16 rounded border border-gray-200 bg-gray-50 px-2 py-1 text-center text-xs text-gray-700"
                                                    x-text="(itemQuantities[{{ $item->id }}] ?? {{ (float) $item->quantity }}).toFixed(2)"
                                                ></span>
                                                <span class="text-gray-500">{{ $item->unit }}</span>
                                                <button
                                                    type="button"
                                                    :disabled="loading === {{ $item->id }}"
                                                    @click="updateQuantity({{ $item->id }}, 1)"
                                                    class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-transparent"
                                                >+</button>
                                                <button
                                                    type="button"
                                                    :disabled="loading === {{ $item->id }}"
                                                    @click="removeItem({{ $item->id }})"
                                                    class="rounded border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-400 disabled:hover:bg-transparent"
                                                >Remove</button>
                                            </div>
                                        @else
                                            <span class="text-gray-600">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <p class="mt-5 text-sm font-semibold">
                        @if ($isEditModeActive)
                            <span x-text="'Estimated value: EUR ' + estimatedValue.toFixed(2)"></span>
                        @else
                            Estimated value: EUR {{ number_format((float) $box->estimated_value, 2) }}
                        @endif
                    </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
