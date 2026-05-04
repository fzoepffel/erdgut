<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.orders.create_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">{{ __('admin.orders.create_intro') }}</p>

            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('app.admin.orders.create') }}" class="mb-5 flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <label for="order-user-pick" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.user') }}</label>
                        <select id="order-user-pick" name="user_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">{{ __('admin.orders.user_placeholder') }}</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected((int) $selectedUserId === (int) $u->id)>{{ $u->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                        {{ __('admin.orders.load_addresses') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('app.admin.orders.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $selectedUserId }}" />

                    <div>
                        <label for="order-address" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.orders.field_address') }}</label>
                        <select id="order-address" name="address_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">{{ __('admin.orders.address_placeholder') }}</option>
                            @foreach ($addresses as $address)
                                <option value="{{ $address->id }}" @selected((int) old('address_id') === (int) $address->id)>
                                    {{ $address->street_line_1 }}, {{ $address->postal_code }} {{ $address->city }}@if ($address->is_primary) ({{ __('admin.orders.address_primary') }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('address_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        @if ($selectedUserId > 0 && $addresses->isEmpty())
                            <p class="mt-1 text-sm text-amber-600">{{ __('admin.orders.no_addresses') }}</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="order-box-size" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.orders.field_box_size') }}</label>
                            <select id="order-box-size" name="box_size" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach (['small', 'medium', 'large'] as $size)
                                    <option value="{{ $size }}" @selected(old('box_size', 'medium') === $size)>{{ ucfirst($size) }}</option>
                                @endforeach
                            </select>
                            @error('box_size')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="order-timeslot" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Preferred timeslot') }}</label>
                            <select id="order-timeslot" name="preferred_timeslot" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="">{{ __('No preference') }}</option>
                                @foreach ($allowedTimeslots as $slot)
                                    <option value="{{ $slot }}" @selected(old('preferred_timeslot') === $slot)>{{ $slot }}</option>
                                @endforeach
                            </select>
                            @error('preferred_timeslot')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="order-window" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.orders.field_delivery_window') }}</label>
                        <input id="order-window" type="text" name="preferred_delivery_window" value="{{ old('preferred_delivery_window') }}" maxlength="255" placeholder="{{ __('admin.orders.delivery_window_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        @error('preferred_delivery_window')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <fieldset class="border-t border-gray-100 pt-4">
                        <legend class="text-xs font-medium text-gray-600">{{ __('admin.subscriptions.preferences_legend') }}</legend>
                        <div class="mt-3 flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="preferences[veg_only]" value="1" @checked(old('preferences.veg_only')) class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" />
                                {{ __('admin.subscriptions.pref_veg_only') }}
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="preferences[meat_add_on]" value="1" @checked(old('preferences.meat_add_on')) class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" />
                                {{ __('admin.subscriptions.pref_meat_add_on') }}
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="preferences[eggs_dairy_add_on]" value="1" @checked(old('preferences.eggs_dairy_add_on')) class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" />
                                {{ __('admin.subscriptions.pref_eggs_dairy') }}
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="border-t border-gray-100 pt-4">
                        <legend class="text-xs font-medium text-gray-600">{{ __('Allergies and exclusions') }}</legend>
                        @php($selectedAllergens = is_array(old('allergies')) ? old('allergies') : [])
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach(\App\Enums\Allergen::cases() as $allergen)
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="allergies[]" value="{{ $allergen->value }}"
                                           @checked(in_array($allergen->value, $selectedAllergens, true))
                                           class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" />
                                    {{ __('allergens.'.$allergen->value) }}
                                </label>
                            @endforeach
                        </div>
                        @error('allergies.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        <div class="mt-3">
                            <label for="order-allergies-note" class="mb-1 block text-xs font-medium text-gray-600">{{ __('allergens.note_label') }}</label>
                            <textarea id="order-allergies-note" name="allergies_note" rows="2" maxlength="500" placeholder="{{ __('allergens.note_hint') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">{{ old('allergies_note') }}</textarea>
                            @error('allergies_note')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </fieldset>

                    <div>
                        <label for="order-instructions" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.orders.field_special_instructions') }}</label>
                        <textarea id="order-instructions" name="special_instructions" rows="2" maxlength="1000" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">{{ old('special_instructions') }}</textarea>
                        @error('special_instructions')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="order-reason" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_reason') }}</label>
                        <input id="order-reason" type="text" name="reason" value="{{ old('reason') }}" maxlength="500" placeholder="{{ __('admin.orders.reason_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        @error('reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <x-primary-button :disabled="$selectedUserId <= 0 || $addresses->isEmpty()">
                            {{ __('admin.orders.create_submit') }}
                        </x-primary-button>
                        <a href="{{ route('app.admin.orders.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            {{ __('admin.deliveries.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
