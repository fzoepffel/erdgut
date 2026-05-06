<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.deliveries.create_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ __('admin.deliveries.create_intro') }}
            </p>

            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.deliveries.select_user') }}</h3>
                <form method="get" action="{{ route('app.admin.deliveries.create') }}" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label for="pick-user" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.user') }}</label>
                        <select id="pick-user" name="user_id" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">{{ __('admin.deliveries.user_placeholder') }}</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected((int) $selectedUserId === (int) $u->id)>{{ $u->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                        {{ __('admin.deliveries.load_addresses') }}
                    </button>
                </form>
            </div>

            {{-- I1 (UX notes): top-level validation banner. The
                 AdminDeliveryStoreRequest::withValidator hook adds
                 errors keyed under `user_id` (no_box_or_order) and
                 `scheduled_for_date` (canonical_duplicate). The
                 per-field @error blocks below already catch the
                 scheduled_for_date case, but `user_id` is a hidden
                 input so its inline message used to render in nowhere.
                 The banner ensures any custom-rule error surfaces, and
                 the explicit @error('user_id') below it places the
                 message visibly inside the form too. --}}
            @if ($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert" data-testid="admin-deliveries-create-errors">
                    <p class="font-semibold">{{ __('admin.deliveries.create_errors_heading') }}</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($addresses->isNotEmpty())
                <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                    <form method="post" action="{{ route('app.admin.deliveries.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $selectedUserId }}" />

                        @error('user_id')
                            <p class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-900">{{ $message }}</p>
                        @enderror

                        <div>
                            <label for="address_id" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_address') }}</label>
                            <select id="address_id" name="address_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach ($addresses as $addr)
                                    <option value="{{ $addr->id }}" @selected((int) old('address_id') === (int) $addr->id)>
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
                            <input id="scheduled_for_date" type="date" name="scheduled_for_date" value="{{ old('scheduled_for_date') }}" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            @error('scheduled_for_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="timeslot" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_timeslot') }}</label>
                            <select id="timeslot" name="timeslot" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach ($timeslots as $slot)
                                    <option value="{{ $slot }}" @selected(old('timeslot', $timeslots[0]) === $slot)>{{ $slot }}</option>
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
                                    <option value="{{ $st->value }}" @selected(old('status', 'scheduled') === $st->value)>
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
                            <textarea id="special_instructions" name="special_instructions" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">{{ old('special_instructions') }}</textarea>
                            @error('special_instructions')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-2">
                            <input id="is_weekly_override" type="checkbox" name="is_weekly_override" value="1" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" @checked(old('is_weekly_override')) />
                            <label for="is_weekly_override" class="text-sm text-gray-700">{{ __('admin.deliveries.field_weekly_override') }}</label>
                        </div>

                        <div>
                            <label for="overrides_delivery_id" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_overrides_delivery_id') }}</label>
                            <input id="overrides_delivery_id" type="number" name="overrides_delivery_id" value="{{ old('overrides_delivery_id') }}" min="1" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.deliveries.override_hint') }}</p>
                            @error('overrides_delivery_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-wrap gap-2 pt-2">
                            <x-primary-button>
                                {{ __('admin.deliveries.save_create') }}
                            </x-primary-button>
                            <a href="{{ route('app.admin.deliveries.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                                {{ __('admin.deliveries.cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
