<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit subscription') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="post" action="{{ route('app.subscriptions.update') }}" class="space-y-6">
                        @csrf
                        @method('patch')

                        <div>
                            <x-input-label for="box_size" :value="__('Box size')" />
                            <select
                                id="box_size"
                                name="box_size"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                @foreach (['small', 'medium', 'large'] as $size)
                                    <option value="{{ $size }}" @selected(old('box_size', $subscription->box_size) === $size)>
                                        {{ ucfirst($size) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('box_size')" />
                        </div>

                        <fieldset class="space-y-3">
                            <legend class="text-sm font-medium text-gray-700">Preferences</legend>

                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="preferences[veg_only]"
                                    value="1"
                                    @checked((bool) old('preferences.veg_only', data_get($subscription->preferences, 'veg_only', false)))
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">Veg option only</span>
                            </label>

                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="preferences[meat_add_on]"
                                    value="1"
                                    @checked((bool) old('preferences.meat_add_on', data_get($subscription->preferences, 'meat_add_on', false)))
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">Meat add-on</span>
                            </label>

                            <x-input-error class="mt-2" :messages="$errors->get('preferences.veg_only')" />
                            <x-input-error class="mt-2" :messages="$errors->get('preferences.meat_add_on')" />
                        </fieldset>

                        @php
                            $oldAllergies = old('allergies');
                            $allergiesValue = is_array($oldAllergies) ? implode(', ', $oldAllergies) : $allergiesInput;
                        @endphp
                        <div>
                            <x-input-label for="allergies" :value="__('Allergies (comma separated)')" />
                            <x-text-input
                                id="allergies"
                                name="allergies"
                                type="text"
                                class="mt-1 block w-full"
                                :value="$allergiesValue"
                                placeholder="nuts, gluten"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('allergies')" />
                            <x-input-error class="mt-2" :messages="$errors->get('allergies.*')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>Save changes</x-primary-button>
                            <a href="{{ route('app.subscriptions.show') }}" class="text-sm text-gray-600 hover:text-gray-900">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
