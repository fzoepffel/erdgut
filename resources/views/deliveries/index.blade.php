<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Deliveries timeline') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg border border-emerald-100 bg-emerald-50/80 px-4 py-3">
                <p class="text-sm font-semibold text-emerald-900">{{ __('Default delivery settings') }}</p>
                <p class="mt-1 text-sm text-emerald-800">
                    {{ __('deliveries.default_settings_intro') }}
                </p>
                <a href="{{ route('app.deliveries.preferences.edit') }}" class="mt-2 inline-flex text-sm font-semibold text-emerald-800 underline decoration-emerald-600 hover:text-emerald-950">
                    {{ __('Open default delivery settings') }}
                </a>
            </div>

            @if (session('status') === 'delivery-gifted')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('Gift delivery scheduled. Your original week was skipped; the gift ships on the date you chose.') }}
                </p>
            @elseif (session('status') === 'delivery-gift-already-delivered')
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ __('This delivery was already completed; gifting is no longer available.') }}
                </p>
            @endif

            @if ($weeklyDeliveries->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-6">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('No upcoming deliveries') }}</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        {{ __('When your subscription is active, your delivery weeks appear here. You can still set defaults for the next cycle.') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="{{ route('app.subscriptions.show') }}" class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                            {{ __('View subscription') }}
                        </a>
                        <a href="{{ route('app.deliveries.preferences.edit') }}" class="inline-flex items-center rounded-md border border-emerald-600 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm hover:bg-emerald-50">
                            {{ __('Open default delivery settings') }}
                        </a>
                    </div>
                </div>
            @endif

            <ol class="space-y-6">
                @foreach ($weeklyDeliveries as $row)
                    @php
                        $baseDelivery = $row['base'];
                        $effectiveDelivery = $row['effective'];
                        $isFocused = $focusedDeliveryId === $baseDelivery->id;
                        $slotDate = $baseDelivery->scheduled_for_date->copy()->startOfDay();
                        $thisWeekStart = now()->copy()->startOfWeek();
                        $thisWeekEnd = now()->copy()->endOfWeek();
                        $nextWeekStart = $thisWeekStart->copy()->addWeek();
                        $nextWeekEnd = $thisWeekEnd->copy()->addWeek();
                        if ($slotDate->betweenIncluded($thisWeekStart, $thisWeekEnd)) {
                            $relativeWeekLabel = __('This week');
                        } elseif ($slotDate->betweenIncluded($nextWeekStart, $nextWeekEnd)) {
                            $relativeWeekLabel = __('Next week');
                        } else {
                            $relativeWeekLabel = null;
                        }
                    @endphp


                    <li class="flex gap-4">
                        <div class="flex shrink-0 flex-col items-center">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-emerald-600 bg-white text-sm font-bold text-emerald-800">
                                {{ $loop->iteration }}
                            </span>
                            @unless ($loop->last)
                                <span class="mt-1 w-0.5 grow min-h-[2rem] bg-emerald-200" aria-hidden="true"></span>
                            @endunless
                        </div>

                        <div class="min-w-0 flex-1 pb-2">
                            <section
                                id="delivery-{{ $baseDelivery->id }}"
                                class="overflow-hidden rounded-lg border bg-white shadow-sm sm:rounded-lg {{ $isFocused ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-gray-100' }}"
                                aria-labelledby="delivery-heading-{{ $baseDelivery->id }}"
                            >
                                <div class="p-6 text-gray-900">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <h3 id="delivery-heading-{{ $baseDelivery->id }}" class="text-lg font-semibold">
                                                @if ($relativeWeekLabel)
                                                    <span class="text-emerald-800">{{ $relativeWeekLabel }}</span>
                                                    <span class="text-gray-400" aria-hidden="true">·</span>
                                                @endif
                                                <span>{{ $baseDelivery->scheduled_for_date->format('d.m.Y') }}</span>
                                            </h3>
                                            <p class="text-xs text-gray-500">{{ __('Delivery date') }}</p>
                                        </div>
                                        <x-status-badge :value="$effectiveDelivery->status" type="delivery" />
                                    </div>

                                    <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                                        <div>
                                            <dt class="font-medium text-gray-500">{{ __('Address') }}</dt>
                                            <dd class="mt-1">
                                                {{ $effectiveDelivery->address?->recipient_name ?? __('No recipient set') }},
                                                {{ $effectiveDelivery->address?->street_line_1 ?? __('No street set') }},
                                                {{ $effectiveDelivery->address?->city ?? __('No city set') }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="font-medium text-gray-500">{{ __('Timeslot') }}</dt>
                                            <dd class="mt-1">{{ $effectiveDelivery->timeslot }}</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="font-medium text-gray-500">{{ __('Special instructions') }}</dt>
                                            <dd class="mt-1">{{ $effectiveDelivery->special_instructions ?: __('No special instructions.') }}</dd>
                                        </div>
                                    </dl>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <a href="{{ route('app.deliveries.gift', $effectiveDelivery) }}" class="inline-flex items-center rounded-md border border-purple-400 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-purple-700 shadow-sm transition hover:bg-purple-50">
                                            {{ __('Gift box') }}
                                        </a>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</x-app-layout>
