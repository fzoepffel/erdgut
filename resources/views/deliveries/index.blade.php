<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Weekly deliveries') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'delivery-weekly-override-updated')
                <p class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                    One-time delivery override saved.
                </p>
            @elseif (session('status') === 'delivery-weekly-override-reset')
                <p class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">
                    Weekly override reset to default delivery settings.
                </p>
            @elseif (session('status') === 'delivery-weekly-override-not-allowed')
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    This weekly delivery can no longer be changed.
                </p>
            @elseif (session('status') === 'delivery-weekly-skip-not-allowed')
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    Skip week is not available for this delivery state.
                </p>
            @endif

            @if ($weeklyDeliveries->isEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <p class="text-sm text-gray-600">No upcoming deliveries found.</p>
                    </div>
                </div>
            @endif

            @foreach ($weeklyDeliveries as $row)
                @php($baseDelivery = $row['base'])
                @php($effectiveDelivery = $row['effective'])
                @php($hasOverride = $row['hasOverride'])
                @php($isFocused = $focusedDeliveryId === $baseDelivery->id)

                <div
                    id="delivery-{{ $baseDelivery->id }}"
                    class="bg-white overflow-hidden shadow-sm sm:rounded-lg border {{ $isFocused ? 'border-emerald-400' : 'border-transparent' }}"
                >
                    <div class="p-6 text-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg font-semibold">
                                Delivery on {{ $baseDelivery->scheduled_for_date->format('D, d M Y') }}
                            </h3>
                            <x-status-badge :value="$effectiveDelivery->status" type="delivery" />
                        </div>

                        @if ($hasOverride)
                            <p class="mt-2 text-sm text-amber-700">
                                One-time override active for this week.
                            </p>
                            <p class="text-xs text-gray-500">
                                Last updated {{ $effectiveDelivery->updated_at?->format('d M Y H:i') }}.
                            </p>
                        @else
                            <p class="mt-2 text-sm text-gray-600">
                                Changes below apply only to this week and do not change your default delivery preferences.
                            </p>
                        @endif

                        <dl class="mt-4 grid gap-3 sm:grid-cols-2 text-sm">
                            <div>
                                <dt class="font-medium text-gray-500">Address</dt>
                                <dd class="mt-1">
                                    {{ $effectiveDelivery->address?->recipient_name ?? 'No recipient set' }},
                                    {{ $effectiveDelivery->address?->street_line_1 ?? 'No street set' }},
                                    {{ $effectiveDelivery->address?->city ?? 'No city set' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500">Timeslot</dt>
                                <dd class="mt-1">{{ $effectiveDelivery->timeslot }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="font-medium text-gray-500">Special instructions</dt>
                                <dd class="mt-1">{{ $effectiveDelivery->special_instructions ?: 'No special instructions.' }}</dd>
                            </div>
                        </dl>

                        <form method="POST" action="{{ route('app.deliveries.override.update', $baseDelivery) }}" class="mt-6 space-y-4">
                            @csrf
                            @method('PATCH')

                            <div>
                                <x-input-label :for="'address_id_'.$baseDelivery->id" :value="__('One-time address')" />
                                <select
                                    id="{{ 'address_id_'.$baseDelivery->id }}"
                                    name="address_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Use default delivery address</option>
                                    @foreach ($addresses as $address)
                                        <option value="{{ $address->id }}" @selected((int) old('address_id', $effectiveDelivery->address_id) === (int) $address->id)>
                                            {{ $address->label ?: 'Address' }}{{ $address->is_primary ? ' (Primary)' : '' }} - {{ $address->street_line_1 }}, {{ $address->city }}
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
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Use default timeslot ({{ $baseDelivery->timeslot }})</option>
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
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >{{ old('special_instructions', $effectiveDelivery->special_instructions) }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('special_instructions')" />
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    id="{{ 'skip_week_'.$baseDelivery->id }}"
                                    name="skip_week"
                                    type="checkbox"
                                    value="1"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    @checked(old('skip_week', $effectiveDelivery->status->value === 'skipped'))
                                />
                                <label for="{{ 'skip_week_'.$baseDelivery->id }}" class="text-sm text-gray-700">
                                    Skip this week only
                                </label>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <x-primary-button>Save one-time override</x-primary-button>
                                @if ($hasOverride)
                                    <button type="submit" name="reset_override" value="1" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 transition hover:bg-gray-50">
                                        Reset to default
                                    </button>
                                @endif
                                <a href="{{ route('app.deliveries.gift', $effectiveDelivery) }}" class="inline-flex items-center rounded-md border border-purple-400 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-purple-700 transition hover:bg-purple-50">
                                    Gift box
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</x-app-layout>
