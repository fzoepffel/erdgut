<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.deliveries.edit_title', ['id' => $delivery->id]) }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ __('admin.deliveries.edit_user', ['email' => $delivery->user?->email ?? '—']) }}
            </p>
            @if (! $delivery->is_weekly_override)
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {{ __('admin.deliveries.edit_canonical_hint') }}
                </p>
            @endif
            @if ($delivery->is_weekly_override)
                <p class="text-sm text-gray-600">
                    {{ __('admin.deliveries.edit_override_frozen') }}
                </p>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                <form method="post" action="{{ route('app.admin.deliveries.update', $delivery) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="address_id" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_address') }}</label>
                        <select id="address_id" name="address_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            @foreach ($addresses as $addr)
                                <option value="{{ $addr->id }}" @selected((int) old('address_id', $delivery->address_id) === (int) $addr->id)>
                                    {{ $addr->label }} — {{ $addr->street_line_1 }}, {{ $addr->postal_code }} {{ $addr->city }}
                                </option>
                            @endforeach
                        </select>
                        @error('address_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="scheduled_for_date" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_scheduled_for') }}</label>
                        <input id="scheduled_for_date" type="date" name="scheduled_for_date" value="{{ old('scheduled_for_date', $delivery->scheduled_for_date?->toDateString()) }}" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        @error('scheduled_for_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="timeslot" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_timeslot') }}</label>
                        <select id="timeslot" name="timeslot" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            @foreach ($timeslots as $slot)
                                <option value="{{ $slot }}" @selected(old('timeslot', $delivery->timeslot) === $slot)>{{ $slot }}</option>
                            @endforeach
                        </select>
                        @error('timeslot')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.status') }}</label>
                        <select id="status" name="status" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            @foreach ($deliveryStatuses as $st)
                                <option value="{{ $st->value }}" @selected(old('status', $delivery->status->value) === $st->value)>
                                    {{ __('admin.deliveries.status.'.$st->value) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="special_instructions" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_instructions') }}</label>
                        <textarea id="special_instructions" name="special_instructions" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">{{ old('special_instructions', $delivery->special_instructions) }}</textarea>
                        @error('special_instructions')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reason" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_reason') }}</label>
                        <input id="reason" type="text" name="reason" value="{{ old('reason') }}" maxlength="500" placeholder="{{ __('admin.deliveries.field_reason_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.deliveries.field_reason_hint') }}</p>
                        @error('reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-primary-button>
                            {{ __('admin.deliveries.save_update') }}
                        </x-primary-button>
                        <a href="{{ route('app.admin.deliveries.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            {{ __('admin.deliveries.cancel') }}
                        </a>
                    </div>
                </form>
            </div>

            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.operations.emails.resend.section_heading') }}</h3>
                <p class="mt-1 text-xs text-gray-600">{{ __('admin.operations.emails.resend.section_intro') }}</p>
                @if (session('status') === 'admin-email-resent')
                    <p class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-900">{{ __('admin.operations.emails.resend.flash_resent') }}</p>
                @elseif (session('status') === 'admin-email-resend-not-found')
                    <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-900">{{ __('admin.operations.emails.resend.flash_not_found') }}</p>
                @endif
                <div class="mt-3">
                    <x-admin.email-resend context="delivery" :entity-id="$delivery->id" />
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
