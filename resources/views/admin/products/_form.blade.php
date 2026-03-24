@php
    /** @var \App\Models\Product|null $product */
    $isEdit = $product !== null;
@endphp

<form method="post" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data">
    @csrf
    @if (($method ?? 'post') !== 'post')
        @method($method)
    @endif

    <div>
        <x-input-label for="producer_id" :value="__('Producer')" />
        <select id="producer_id" name="producer_id" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm" required>
            <option value="">{{ __('Select producer') }}</option>
            @foreach ($producers as $producer)
                <option value="{{ $producer->id }}" @selected((int) old('producer_id', $product?->producer_id) === (int) $producer->id)>
                    {{ $producer->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('producer_id')" />
    </div>

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $product?->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="category" :value="__('Category')" />
        <select id="category" name="category" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm" required>
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected(old('category', $product?->category) === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    <div>
        <x-input-label for="unit_price" :value="__('Unit price')" />
        <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('unit_price', $product?->unit_price)" required />
        <x-input-error class="mt-2" :messages="$errors->get('unit_price')" />
    </div>

    <div class="flex flex-col gap-2 sm:flex-row sm:gap-8">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_seasonal" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_seasonal', $product?->is_seasonal))>
            <span class="text-sm text-gray-700">{{ __('Seasonal') }}</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_available" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_available', $product?->is_available ?? true))>
            <span class="text-sm text-gray-700">{{ __('Available in shop') }}</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_published" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_published', $product?->is_published ?? true))>
            <span class="text-sm text-gray-700">{{ __('Published') }}</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_test" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_test', $product?->is_test))>
            <span class="text-sm text-gray-700">{{ __('Test data') }}</span>
        </label>
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('is_seasonal')" />
    <p class="text-xs text-gray-500 -mt-1 sm:col-span-full">{{ __('Published: visible in shop and default box composition. Available: shown in shop when published.') }}</p>
    <x-input-error class="mt-2" :messages="$errors->get('is_available')" />
    <x-input-error class="mt-2" :messages="$errors->get('is_published')" />
    <x-input-error class="mt-2" :messages="$errors->get('is_test')" />

    <div>
        <x-input-label for="metadata_unit" :value="__('Metadata: unit')" />
        <x-text-input id="metadata_unit" name="metadata[unit]" type="text" class="mt-1 block w-full" :value="old('metadata.unit', $product?->metadata['unit'] ?? '')" />
        <x-input-error class="mt-2" :messages="$errors->get('metadata.unit')" />
    </div>
    <div>
        <x-input-label for="metadata_origin" :value="__('Metadata: origin')" />
        <x-text-input id="metadata_origin" name="metadata[origin]" type="text" class="mt-1 block w-full" :value="old('metadata.origin', $product?->metadata['origin'] ?? '')" />
        <x-input-error class="mt-2" :messages="$errors->get('metadata.origin')" />
    </div>
    <div>
        <x-input-label for="metadata_variety" :value="__('Metadata: variety')" />
        <x-text-input id="metadata_variety" name="metadata[variety]" type="text" class="mt-1 block w-full" :value="old('metadata.variety', $product?->metadata['variety'] ?? '')" />
        <x-input-error class="mt-2" :messages="$errors->get('metadata.variety')" />
    </div>
    <div>
        <x-input-label for="metadata_taste_profile" :value="__('Metadata: taste profile')" />
        <textarea id="metadata_taste_profile" name="metadata[taste_profile]" rows="2" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm">{{ old('metadata.taste_profile', $product?->metadata['taste_profile'] ?? '') }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('metadata.taste_profile')" />
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
        <x-input-label for="image" :value="__('Image')" />
        <input id="image" name="image" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600" x-on:change="onFile($event)" />
        <p class="mt-1 text-xs text-gray-500">{{ __('Optional. Max 5 MB.') }}</p>
        <div class="mt-3" x-show="previewUrl !== null" x-cloak>
            <p class="text-xs font-medium text-gray-600 mb-1">{{ __('admin.form.new_image_preview') }}</p>
            <img src="" alt="" class="max-h-40 rounded border border-gray-200 shadow-sm" x-bind:src="previewUrl" />
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('image')" />
    </div>

    @if ($isEdit && $product->imageUrl())
        <div class="rounded border border-gray-200 p-4 bg-gray-50">
            <p class="text-sm font-medium text-gray-700 mb-2">{{ __('Current image') }}</p>
            <img src="{{ $product->imageUrl() }}" alt="" class="max-h-40 rounded shadow-sm">
            <label class="mt-3 inline-flex items-center gap-2">
                <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('remove_image'))>
                <span class="text-sm text-gray-700">{{ __('Remove image') }}</span>
            </label>
            <x-input-error class="mt-2" :messages="$errors->get('remove_image')" />
        </div>
    @endif

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $isEdit ? __('Save') : __('Create product') }}</x-primary-button>
        <a href="{{ route('app.admin.products.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('Cancel') }}</a>
    </div>
</form>
