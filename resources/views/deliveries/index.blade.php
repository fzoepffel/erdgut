<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Deliveries timeline') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg border border-emerald-100 bg-emerald-50/80 px-4 py-3">
                <p class="text-sm font-semibold text-emerald-900">{{ __('Default vs this week only') }}</p>
                <p class="mt-1 text-sm text-emerald-800">
                    {{ __('Edit your saved address and usual timeslot in default delivery settings. Changes on this page apply only to the weeks listed below.') }}
                </p>
                <a href="{{ route('app.deliveries.preferences.edit') }}" class="mt-2 inline-flex text-sm font-semibold text-emerald-800 underline decoration-emerald-600 hover:text-emerald-950">
                    {{ __('Open default delivery settings') }}
                </a>
            </div>

            @if (session('status') === 'delivery-weekly-override-updated')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('One-time delivery override saved.') }}
                </p>
            @elseif (session('status') === 'delivery-weekly-override-reset')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('Weekly override reset to your default delivery settings.') }}
                </p>
            @elseif (session('status') === 'delivery-weekly-override-not-allowed')
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ __('This weekly delivery can no longer be changed.') }}
                </p>
            @elseif (session('status') === 'delivery-weekly-skip-not-allowed')
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    {{ __('Skip week is not available for this delivery state.') }}
                </p>
            @elseif (session('status') === 'delivery-week-skipped')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('This delivery week is skipped. Your subscription billing follows your plan; you will not receive a box this week.') }}
                </p>
            @elseif (session('status') === 'delivery-postponed')
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ __('This week was skipped and the next delivery week is scheduled. Billing follows your subscription plan.') }}
                </p>
            @elseif (session('status') === 'delivery-gifted')
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
                        $hasOverride = $row['hasOverride'];
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
                        $canPostpone = $baseDelivery->scheduled_for_date->toDateString() >= now()->toDateString()
                            && $effectiveDelivery->status !== \App\Enums\DeliveryStatus::Delivered;
                        $canQuickSkip = $baseDelivery->scheduled_for_date->toDateString() >= now()->toDateString()
                            && $effectiveDelivery->status === \App\Enums\DeliveryStatus::Scheduled;
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

                                    @if ($hasOverride)
                                        <p class="mt-2 text-sm text-amber-800">
                                            {{ __('One-time override active for this week.') }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ __('Last updated :when.', ['when' => $effectiveDelivery->updated_at?->format('d.m.Y H:i') ?? '—']) }}
                                        </p>
                                    @else
                                        <p class="mt-2 text-sm text-gray-600">
                                            {{ __('The form below applies only to this week. It does not change your default delivery preferences.') }}
                                        </p>
                                    @endif

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
                                        @if ($canQuickSkip)
                                            <form method="POST" action="{{ route('app.deliveries.skip', $baseDelivery) }}" class="inline">
                                                @csrf
                                                <x-secondary-button type="submit">
                                                    {{ __('Skip this week') }}
                                                </x-secondary-button>
                                            </form>
                                        @endif

                                        @if ($canPostpone)
                                            <x-secondary-button
                                                type="button"
                                                x-data=""
                                                x-on:click.prevent="$dispatch('open-modal', 'postpone-delivery-{{ $baseDelivery->id }}')"
                                            >
                                                {{ __('Postpone to next week') }}
                                            </x-secondary-button>
                                        @endif

                                        <a href="{{ route('app.deliveries.gift', $effectiveDelivery) }}" class="inline-flex items-center rounded-md border border-purple-400 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-purple-700 shadow-sm transition hover:bg-purple-50">
                                            {{ __('Gift box') }}
                                        </a>
                                    </div>

                                    <form method="POST" action="{{ route('app.deliveries.override.update', $baseDelivery) }}" class="mt-6 space-y-4 border-t border-gray-100 pt-6">
                                        @csrf
                                        @method('PATCH')

                                        <p class="text-sm font-medium text-gray-900">{{ __('One-time changes for this week') }}</p>

                                        <div>
                                            <x-input-label :for="'address_id_'.$baseDelivery->id" :value="__('One-time address')" />
                                            <select
                                                id="{{ 'address_id_'.$baseDelivery->id }}"
                                                name="address_id"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                            >
                                                <option value="">{{ __('Use default delivery address') }}</option>
                                                @foreach ($addresses as $address)
                                                    <option value="{{ $address->id }}" @selected((int) old('address_id', $effectiveDelivery->address_id) === (int) $address->id)>
                                                        {{ $address->label ?: __('Address') }}{{ $address->is_primary ? ' ('.__('Primary').')' : '' }} — {{ $address->street_line_1 }}, {{ $address->city }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-input-error class="mt-2" :messages="$errors->get('address_id')" />
                                        </div>

                                        <div>
                                            <x-input-label :for="'timeslot_'.$baseDelivery->id" :value="__('One-time timeslot')" />
                                            <select
                                                id="{{ 'timeslot_'.$baseDelivery->id }}"
                                                name="timeslot"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                            >
                                                <option value="">{{ __('Use default timeslot (:slot)', ['slot' => $baseDelivery->timeslot]) }}</option>
                                                @foreach ($timeslotOptions as $slot)
                                                    <option value="{{ $slot }}" @selected(old('timeslot', $effectiveDelivery->timeslot) === $slot)>
                                                        {{ $slot }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-input-error class="mt-2" :messages="$errors->get('timeslot')" />
                                        </div>

                                        <div>
                                            <x-input-label :for="'special_instructions_'.$baseDelivery->id" :value="__('One-time instructions')" />
                                            <textarea
                                                id="{{ 'special_instructions_'.$baseDelivery->id }}"
                                                name="special_instructions"
                                                rows="3"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                            >{{ old('special_instructions', $effectiveDelivery->special_instructions) }}</textarea>
                                            <x-input-error class="mt-2" :messages="$errors->get('special_instructions')" />
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <input
                                                id="{{ 'skip_week_'.$baseDelivery->id }}"
                                                name="skip_week"
                                                type="checkbox"
                                                value="1"
                                                class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600"
                                                @checked(old('skip_week', $effectiveDelivery->status->value === 'skipped'))
                                            />
                                            <label for="{{ 'skip_week_'.$baseDelivery->id }}" class="text-sm text-gray-700">
                                                {{ __('Skip this week only') }}
                                            </label>
                                        </div>

                                        <div class="flex flex-wrap gap-3">
                                            <x-primary-button>{{ __('Save one-time override') }}</x-primary-button>
                                            @if ($hasOverride)
                                                <button type="submit" name="reset_override" value="1" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50">
                                                    {{ __('Reset to default') }}
                                                </button>
                                            @endif
                                        </div>
                                    </form>

                                    <x-modal name="postpone-delivery-{{ $baseDelivery->id }}" focusable>
                                        <form method="POST" action="{{ route('app.deliveries.postpone', $baseDelivery) }}" class="p-6">
                                            @csrf

                                            <h2 class="text-lg font-medium text-gray-900">
                                                {{ __('Postpone to next week?') }}
                                            </h2>

                                            <p class="mt-2 text-sm text-gray-600">
                                                {{ __('We skip this delivery week and ensure the following week is scheduled with your current defaults. Subscription charges follow your plan; you will not receive a box on the original date.') }}
                                            </p>

                                            <div class="mt-6 flex justify-end gap-2">
                                                <x-secondary-button type="button" x-on:click="$dispatch('close')">
                                                    {{ __('Cancel') }}
                                                </x-secondary-button>

                                                <x-primary-button>
                                                    {{ __('Confirm postpone') }}
                                                </x-primary-button>
                                            </div>
                                        </form>
                                    </x-modal>
                                </div>
                            </section>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>
</x-app-layout>
