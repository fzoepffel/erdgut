<form method="post" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'post')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <x-input-label for="category" :value="__('Category')" required />
        <select id="category" name="category"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                required>
            <option value="">{{ __('— Select category —') }}</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected(old('category', $subcategory?->category) === $cat)>
                    {{ $categoryLabels[$cat] ?? $cat }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <x-input-label for="label_de" value="Label DE" required />
            <x-text-input id="label_de" name="label_de" type="text" class="mt-1 block w-full"
                          :value="old('label_de', $subcategory?->label_de)" required maxlength="120" />
            <x-input-error class="mt-2" :messages="$errors->get('label_de')" />
        </div>
        <div>
            <x-input-label for="label_en" value="Label EN" required />
            <x-text-input id="label_en" name="label_en" type="text" class="mt-1 block w-full"
                          :value="old('label_en', $subcategory?->label_en)" required maxlength="120" />
            <x-input-error class="mt-2" :messages="$errors->get('label_en')" />
        </div>
        <div>
            <x-input-label for="label_fr" value="Label FR" required />
            <x-text-input id="label_fr" name="label_fr" type="text" class="mt-1 block w-full"
                          :value="old('label_fr', $subcategory?->label_fr)" required maxlength="120" />
            <x-input-error class="mt-2" :messages="$errors->get('label_fr')" />
        </div>
    </div>

    <div class="w-32">
        <x-input-label for="sort_order" :value="__('Sort order')" />
        <x-text-input id="sort_order" name="sort_order" type="number" min="0" max="9999" step="1"
                      class="mt-1 block w-full"
                      :value="old('sort_order', $subcategory?->sort_order ?? 0)" />
        <p class="mt-1 text-xs text-gray-500">{{ __('admin.subcategories.sort_order_hint') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
    </div>

    <div class="flex items-center gap-4 border-t border-gray-100 pt-4">
        <button type="submit" class="btn-solid-sm">
            {{ __('Save') }}
        </button>
        <a href="{{ route('app.admin.subcategories.index') }}" class="text-sm text-gray-500 underline underline-offset-2 hover:text-gray-800">
            {{ __('Cancel') }}
        </a>
    </div>
</form>
