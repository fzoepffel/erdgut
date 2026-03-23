@php
    /** @var \App\Models\Producer|null $producer */
    $isEdit = $producer !== null;
@endphp

<form method="post" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data">
    @csrf
    @if (($method ?? 'post') !== 'post')
        @method($method)
    @endif

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $producer?->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="region" :value="__('Region')" />
        <x-text-input id="region" name="region" type="text" class="mt-1 block w-full" :value="old('region', $producer?->region)" />
        <x-input-error class="mt-2" :messages="$errors->get('region')" />
    </div>

    <div>
        <x-input-label for="description" :value="__('Description')" />
        <textarea id="description" name="description" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $producer?->description) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('description')" />
    </div>

    <div>
        <x-input-label for="image_url" :value="__('External image URL (optional)')" />
        <x-text-input id="image_url" name="image_url" type="url" class="mt-1 block w-full" :value="old('image_url', $producer?->image_url)" placeholder="https://..." />
        <p class="mt-1 text-xs text-gray-500">{{ __('Used on the public producers page when no uploaded image is set.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('image_url')" />
    </div>

    <div>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_test" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_test', $producer?->is_test))>
            <span class="text-sm text-gray-700">{{ __('Test data') }}</span>
        </label>
        <x-input-error class="mt-2" :messages="$errors->get('is_test')" />
    </div>

    <div>
        <x-input-label for="image" :value="__('Uploaded image')" />
        <input id="image" name="image" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600" />
        <p class="mt-1 text-xs text-gray-500">{{ __('Optional. Takes precedence over external URL. Max 5 MB.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('image')" />
    </div>

    @if ($isEdit && $producer->primaryImageUrl())
        <div class="rounded border border-gray-200 p-4 bg-gray-50">
            <p class="text-sm font-medium text-gray-700 mb-2">{{ __('Current image') }}</p>
            <img src="{{ $producer->primaryImageUrl() }}" alt="" class="max-h-40 rounded shadow-sm">
            @if ($producer->image_path)
                <label class="mt-3 inline-flex items-center gap-2">
                    <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('remove_image'))>
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
