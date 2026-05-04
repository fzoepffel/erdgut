@php
    /** @var \App\Models\Producer|null $producer */
    $isEdit = $producer !== null;
    $locales = ['de' => 'DE', 'en' => 'EN', 'fr' => 'FR'];

    // Open the tab whose locale already has a validation error so the
    // admin doesn't have to hunt for the field that failed.
    $initialTab = 'de';
    foreach (array_keys($locales) as $loc) {
        if ($errors->has("translations.$loc.name")
            || $errors->has("translations.$loc.region")
            || $errors->has("translations.$loc.description")) {
            $initialTab = $loc;
            break;
        }
    }
@endphp

<form method="post" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data">
    @csrf
    @if (($method ?? 'post') !== 'post')
        @method($method)
    @endif

    <div class="space-y-4" x-data="{ tab: '{{ $initialTab }}' }">
        {{-- Stack on narrow viewports so the all-three-required hint
             doesn't crash into the heading — the text is long in DE/FR
             and `justify-between` would otherwise wrap to two cramped
             columns. Inline at sm: where there's room. --}}
        <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('Translations') }}</h2>
            <p class="text-xs text-gray-600">{{ __('All three languages are required.') }}</p>
        </div>

        <div class="flex gap-1 border-b border-gray-200" role="tablist">
            @foreach ($locales as $locale => $label)
                @php
                    $hasError = $errors->has("translations.$locale.name")
                        || $errors->has("translations.$locale.region")
                        || $errors->has("translations.$locale.description");
                @endphp
                <button
                    type="button"
                    role="tab"
                    :aria-selected="tab === '{{ $locale }}'"
                    @click="tab = '{{ $locale }}'"
                    :class="tab === '{{ $locale }}'
                        ? 'border-emerald-600 text-emerald-700'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                    class="relative -mb-px border-b-2 px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                >
                    {{ $label }}
                    @if ($hasError)
                        <span class="ml-1 inline-block h-2 w-2 rounded-full bg-rose-600" aria-hidden="true" title="{{ __('admin.form.tab_has_errors') }}"></span>
                    @endif
                </button>
            @endforeach
        </div>

        @foreach ($locales as $locale => $label)
            @php
                $t = $producer?->translations?->firstWhere('locale', $locale);
            @endphp

            <div x-show="tab === '{{ $locale }}'" role="tabpanel" class="rounded border border-gray-200 bg-white p-4">
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <x-input-label :for="'translations_'.$locale.'_name'" :value="__('Name')" required />
                        <x-text-input
                            :id="'translations_'.$locale.'_name'"
                            :name="'translations['.$locale.'][name]'"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('translations.'.$locale.'.name', $t?->name)"
                            required
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.name')" />
                    </div>

                    <div>
                        <x-input-label :for="'translations_'.$locale.'_region'" :value="__('Region')" required />
                        <x-text-input
                            :id="'translations_'.$locale.'_region'"
                            :name="'translations['.$locale.'][region]'"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('translations.'.$locale.'.region', $t?->region)"
                            required
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.region')" />
                    </div>

                    <div>
                        <x-input-label :for="'translations_'.$locale.'_description'" :value="__('Description')" required />
                        <textarea
                            id="{{ 'translations_'.$locale.'_description' }}"
                            name="{{ 'translations['.$locale.'][description]' }}"
                            rows="4"
                            class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
                            required
                        >{{ old('translations.'.$locale.'.description', $t?->description) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.description')" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div>
        <x-input-label for="image_url" :value="__('External image URL (optional)')" />
        <x-text-input id="image_url" name="image_url" type="url" class="mt-1 block w-full" :value="old('image_url', $producer?->image_url)" placeholder="https://..." />
        <p class="mt-1 text-xs text-gray-500">{{ __('Used on the public producers page when no uploaded image is set.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('image_url')" />
    </div>

    <div>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_test" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_test', $producer?->is_test))>
            <span class="text-sm text-gray-700">{{ __('Test data') }}</span>
        </label>
        <x-input-error class="mt-2" :messages="$errors->get('is_test')" />
    </div>

    <div
        x-data="{
            previewUrl: null,
            onFile(e) {
                const f = e.target.files?.[0];
                if (!f || !f.type.startsWith('image/')) {
                    this.previewUrl = null;
                    return;
                }
                const reader = new FileReader();
                reader.onload = () => { this.previewUrl = reader.result; };
                reader.readAsDataURL(f);
            },
        }"
    >
        <x-input-label for="image" :value="__('Uploaded image')" />
        <input id="image" name="image" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600" x-on:change="onFile($event)" />
        <p class="mt-1 text-xs text-gray-500">{{ __('Optional. Takes precedence over external URL. Max 5 MB.') }}</p>
        <div class="mt-3" x-show="previewUrl !== null" x-cloak>
            <p class="text-xs font-medium text-gray-600 mb-1">{{ __('admin.form.new_image_preview') }}</p>
            <img src="" alt="" class="max-h-40 rounded border border-gray-200 shadow-sm" x-bind:src="previewUrl" />
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('image')" />
    </div>

    @if ($isEdit && $producer->primaryImageUrl())
        <div class="rounded border border-gray-200 p-4 bg-gray-50">
            <p class="text-sm font-medium text-gray-700 mb-2">{{ __('Current image') }}</p>
            <img src="{{ $producer->primaryImageUrl() }}" alt="" class="max-h-40 rounded shadow-sm">
            @if ($producer->image_path)
                <label class="mt-3 inline-flex items-center gap-2">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('remove_image'))>
                    <span class="text-sm text-gray-700">{{ __('Remove uploaded file (external URL below may still apply)') }}</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('remove_image')" />
            @endif
        </div>
    @endif

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $isEdit ? __('Save') : __('Create producer') }}</x-primary-button>
        <a href="{{ route('app.admin.producers.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('Cancel') }}</a>
    </div>
</form>
