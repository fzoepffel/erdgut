<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('This week\'s box') }}
        </h2>
    </x-slot>

    @php
        $weekLabelDate = $box->week_start_date->format('d.m.Y');
    @endphp

    <div class="@if ($isEditModeActive) pb-28 sm:pb-0 @endif py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section
                class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:p-6"
                aria-labelledby="box-week-heading"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="box-week-heading" class="text-lg font-semibold text-gray-900">
                            {{ __('Week of :date', ['date' => $weekLabelDate]) }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Your weekly food box') }}</p>
                    </div>
                    <x-status-badge :value="$box->status" type="box" />
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    @if ($modificationDeadline)
                        <p class="text-gray-700">
                            {{ __('Changes close at :datetime', ['datetime' => $modificationDeadline->format('d.m.Y H:i')]) }}
                        </p>
                        @if ($canModifyBox && $customerBoxEditingEnabled)
                            <div
                                class="text-emerald-800"
                                x-data="{
                                    deadlineMs: {{ (int) round($modificationDeadline->timestamp * 1000) }},
                                    parts: '',
                                    init() {
                                        const tick = () => {
                                            const now = Date.now();
                                            if (now >= this.deadlineMs) {
                                                this.parts = '';
                                                return;
                                            }
                                            let s = Math.floor((this.deadlineMs - now) / 1000);
                                            const h = Math.floor(s / 3600);
                                            s %= 3600;
                                            const m = Math.floor(s / 60);
                                            this.parts = h > 0 ? `${h}h ${m}m` : `${m} min`;
                                        };
                                        tick();
                                        setInterval(tick, 60000);
                                    },
                                }"
                            >
                                <p class="flex flex-wrap items-baseline gap-x-1">
                                    <span>{{ __('Time left to edit:') }}</span>
                                    <span class="font-semibold tabular-nums" x-text="parts"></span>
                                </p>
                            </div>
                        @endif
                    @else
                        <p class="text-gray-600">{{ __('Modification deadline not set.') }}</p>
                    @endif

                    @if ($canCustomerMutateItems)
                        <p class="font-medium text-emerald-800">{{ __('You can still change the contents of this box.') }}</p>
                    @elseif ($canModifyBox && ! $customerBoxEditingEnabled)
                        <p class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-800">
                            {{ __('boxes.customer_editing_disabled_notice') }}
                        </p>
                    @else
                        <p class="rounded-md border border-amber-100 bg-amber-50/80 px-3 py-2 font-medium text-amber-900">
                            {{ __('This box is read-only. You cannot change quantities anymore.') }}
                        </p>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($isEditModeActive)
                        <a
                            href="{{ route('app.boxes.show', $box) }}"
                            class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            {{ __('Back to view mode') }}
                        </a>
                    @elseif ($canCustomerMutateItems)
                        <a
                            href="{{ route('app.boxes.show', ['box' => $box, 'mode' => 'edit']) }}"
                            data-testid="box-edit-contents-link"
                            class="inline-flex min-h-[44px] items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                        >
                            {{ __('Edit box contents') }}
                        </a>
                    @else
                        <span
                            class="inline-flex min-h-[44px] items-center rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-500"
                        >
                            {{ __('Editing unavailable') }}
                        </span>
                    @endif
                </div>

                @if ($isEditModeBlocked)
                    <p class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">
                        @if ($blockedReason === 'box-edit-customer-disabled')
                            {{ __('boxes.edit_mode_blocked_customer_disabled') }}
                        @elseif ($blockedReason === 'box-edit-status-not-editable')
                            {{ __('Edit mode is blocked because this box status is not editable.') }}
                        @elseif ($blockedReason === 'box-edit-deadline-expired')
                            {{ __('Edit mode is blocked because the modification deadline has passed.') }}
                        @else
                            {{ __('Edit mode is currently unavailable for this box.') }}
                        @endif
                    </p>
                @endif
            </section>

            <div
                class="space-y-6"
                @if ($isEditModeActive)
                    x-data="{
                        itemQuantities: {{ json_encode($box->items->keyBy('id')->map(fn ($i) => (float) $i->quantity)->toArray()) }},
                        estimatedValue: {{ (float) $box->estimated_value }},
                        includedValue: {{ $budgetDetails === null ? 'null' : json_encode($budgetDetails['included_value']) }},
                        removedItemIds: [],
                        totalItems: {{ $box->items->count() }},
                        updateBaseUrl: '{{ url('/app/boxes/'.$box->id.'/items') }}',
                        loading: null,
                        error: null,
                        formatRemainder() {
                            if (this.includedValue === null) {
                                return '';
                            }
                            const r = Math.round((this.includedValue - this.estimatedValue) * 100) / 100;
                            if (r >= 0) {
                                return 'EUR ' + r.toFixed(2);
                            }
                            return Math.abs(r).toFixed(2) + ' ' + @json(__('over'));
                        },
                        async updateQuantity(itemId, delta) {
                            this.loading = itemId;
                            this.error = null;
                            try {
                                const res = await fetch(this.updateBaseUrl + '/' + itemId, {
                                    method: 'PATCH',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        Accept: 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                    },
                                    body: JSON.stringify({ delta }),
                                });
                                const data = await res.json();
                                if (!res.ok) {
                                    const msg = data?.errors?.delta?.[0] ?? data?.message ?? @json(__('Update failed'));
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
                                        Accept: 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                    },
                                });
                                const data = await res.json();
                                if (!res.ok) {
                                    const msg = data?.message ?? @json(__('Remove failed'));
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
                <x-box.budget-panel
                    :box="$box"
                    :budget-details="$budgetDetails"
                    :bind-estimate="$isEditModeActive"
                />

                <section
                    class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                    aria-labelledby="box-items-heading"
                >
                    <div class="border-b border-gray-100 bg-gray-50/80 px-4 py-3 sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 id="box-items-heading" class="text-sm font-semibold text-gray-900">
                                {{ __('Contents') }}
                            </h3>
                            @if ($isEditModeActive)
                                <span class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">
                                    {{ __('Edit mode') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        @if ($isEditModeActive)
                            <div
                                x-show="error"
                                x-cloak
                                class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800"
                                role="alert"
                                aria-live="polite"
                                x-text="error"
                            ></div>
                        @endif

                        @if ($box->items->isEmpty())
                            <div class="rounded-md border border-dashed border-gray-200 bg-gray-50 p-6 text-center">
                                <p class="text-sm text-gray-700">{{ __('No items in this box yet.') }}</p>
                                @if ($canModifyBox && $shopExtrasEnabled && $customerBoxEditingEnabled)
                                    <a
                                        href="{{ route('app.shop.index') }}"
                                        class="mt-4 inline-flex min-h-[44px] items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                                    >
                                        {{ __('Browse shop extras') }}
                                    </a>
                                @endif
                            </div>
                        @else
                            @if ($isEditModeActive)
                                <p
                                    x-show="removedItemIds.length >= totalItems"
                                    x-cloak
                                    class="text-sm text-gray-600"
                                >
                                    {{ __('No items in this box yet.') }}
                                </p>
                                <ul
                                    x-show="removedItemIds.length < totalItems"
                                    class="divide-y divide-gray-100"
                                >
                                    @foreach ($box->items as $item)
                                        <li
                                            x-show="!removedItemIds.includes({{ $item->id }})"
                                            class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div class="min-w-0">
                                                <span class="font-medium text-gray-900">{{ $item->product?->name ?? __('Unknown product') }}</span>
                                                @if ($item->is_extra)
                                                    <span class="ml-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                        {{ __('Extra add-on') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="flex flex-shrink-0 items-center gap-2">
                                                <button
                                                    type="button"
                                                    :disabled="loading === {{ $item->id }} || (itemQuantities[{{ $item->id }}] ?? {{ (float) $item->quantity }}) <= 1"
                                                    @click="updateQuantity({{ $item->id }}, -1)"
                                                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md border border-gray-300 text-lg font-medium text-gray-800 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                    aria-label="{{ __('Decrease quantity') }}"
                                                >
                                                    −
                                                </button>
                                                <span
                                                    class="min-w-[4.5rem] rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-center text-sm font-medium tabular-nums text-gray-900"
                                                    x-text="(itemQuantities[{{ $item->id }}] ?? {{ (float) $item->quantity }}).toFixed(2)"
                                                ></span>
                                                <span class="text-sm text-gray-500">{{ $item->unit }}</span>
                                                <button
                                                    type="button"
                                                    :disabled="loading === {{ $item->id }}"
                                                    @click="updateQuantity({{ $item->id }}, 1)"
                                                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md border border-gray-300 text-lg font-medium text-gray-800 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                    aria-label="{{ __('Increase quantity') }}"
                                                >
                                                    +
                                                </button>
                                                <button
                                                    type="button"
                                                    :disabled="loading === {{ $item->id }}"
                                                    @click="removeItem({{ $item->id }})"
                                                    class="ml-1 inline-flex min-h-[44px] items-center rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    {{ __('Remove') }}
                                                </button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <ul class="divide-y divide-gray-100">
                                    @foreach ($box->items as $item)
                                        <li class="flex flex-col gap-1 py-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <span class="font-medium text-gray-900">{{ $item->product?->name ?? __('Unknown product') }}</span>
                                                @if ($item->is_extra)
                                                    <span class="ml-2 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                        {{ __('Extra add-on') }}
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-sm tabular-nums text-gray-600">
                                                {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($canModifyBox && $shopExtrasEnabled && $customerBoxEditingEnabled)
                                    <div class="mt-6 border-t border-gray-100 pt-4">
                                        <a
                                            href="{{ route('app.shop.index') }}"
                                            class="inline-flex min-h-[44px] items-center text-sm font-semibold text-emerald-700 hover:text-emerald-900"
                                        >
                                            {{ __('Add extras from the shop') }} →
                                        </a>
                                    </div>
                                @endif
                            @endif
                        @endif
                    </div>
                </section>

                @if ($isEditModeActive)
                    <div
                        class="fixed inset-x-0 bottom-0 z-40 border-t border-emerald-200 bg-white/95 px-4 py-3 shadow-[0_-4px_20px_rgba(0,0,0,0.08)] backdrop-blur-sm sm:hidden"
                        aria-live="polite"
                    >
                        <p class="text-center text-sm font-semibold text-emerald-900">
                            <span>{{ __('Estimated value') }}</span>:
                            <span x-text="'EUR ' + estimatedValue.toFixed(2)"></span>
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
