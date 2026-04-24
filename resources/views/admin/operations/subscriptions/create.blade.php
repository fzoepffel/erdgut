<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.subscriptions.create_title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">{{ __('admin.subscriptions.create_intro') }}</p>

            <div class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('app.admin.subscriptions.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="sub-user" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.user') }}</label>
                        <select id="sub-user" name="user_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">{{ __('admin.subscriptions.user_placeholder') }}</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected((int) old('user_id', $selectedUserId) === (int) $u->id)>{{ $u->email }}</option>
                            @endforeach
                        </select>
                        @error('user_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="sub-billing-mode" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.subscriptions.field_billing_mode') }}</label>
                            <select id="sub-billing-mode" name="billing_mode" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach ($billingModes as $mode)
                                    <option value="{{ $mode->value }}" @selected(old('billing_mode', \App\Enums\BillingMode::ManualInvoice->value) === $mode->value)>{{ $mode->label() }}</option>
                                @endforeach
                            </select>
                            @error('billing_mode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="sub-plan" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.subscriptions.field_plan') }}</label>
                            <select id="sub-plan" name="plan" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach ($plans as $p)
                                    <option value="{{ $p->value }}" @selected(old('plan', 'weekly') === $p->value)>{{ ucfirst($p->value) }}</option>
                                @endforeach
                            </select>
                            @error('plan')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="sub-size" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.subscriptions.field_box_size') }}</label>
                            <select id="sub-size" name="box_size" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach (['small', 'medium', 'large'] as $size)
                                    <option value="{{ $size }}" @selected(old('box_size', 'medium') === $size)>{{ ucfirst($size) }}</option>
                                @endforeach
                            </select>
                            @error('box_size')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="sub-fulfillment" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.subscriptions.field_fulfillment_mode') }}</label>
                            <select id="sub-fulfillment" name="fulfillment_mode" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                @foreach ($fulfillmentModes as $fm)
                                    <option value="{{ $fm->value }}" @selected(old('fulfillment_mode', 'delivery') === $fm->value)>{{ ucfirst($fm->value) }}</option>
                                @endforeach
                            </select>
                            @error('fulfillment_mode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="sub-weekday" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Preferred weekday') }}</label>
                            <select id="sub-weekday" name="delivery_weekday" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="">{{ __('No preference') }}</option>
                                @php
                                    $weekdayLabels = [0 => __('Sunday'), 1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday')];
                                @endphp
                                @foreach ($allowedWeekdays as $wd)
                                    <option value="{{ $wd }}" @selected((string) old('delivery_weekday') === (string) $wd)>{{ $weekdayLabels[$wd] ?? $wd }}</option>
                                @endforeach
                            </select>
                            @error('delivery_weekday')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="sub-timeslot" class="mb-1 block text-xs font-medium text-gray-600">{{ __('Preferred timeslot') }}</label>
                            <select id="sub-timeslot" name="preferred_timeslot" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                <option value="">{{ __('No preference') }}</option>
                                @foreach ($allowedTimeslots as $slot)
                                    <option value="{{ $slot }}" @selected(old('preferred_timeslot') === $slot)>{{ $slot }}</option>
                                @endforeach
                            </select>
                            @error('preferred_timeslot')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
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

                    <div>
                        <label for="sub-reason" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.deliveries.field_reason') }}</label>
                        <input id="sub-reason" type="text" name="reason" value="{{ old('reason') }}" maxlength="500" placeholder="{{ __('admin.subscriptions.reason_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        @error('reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2">
                        <button type="submit" class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800">
                            {{ __('admin.subscriptions.create_submit') }}
                        </button>
                        <a href="{{ route('app.admin.subscriptions.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            {{ __('admin.deliveries.cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
