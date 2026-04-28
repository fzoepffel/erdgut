@php
    use App\Enums\Allergen;
    use App\Enums\DietaryTag;
    use App\Enums\TasteProfile;

    /** @var \App\Models\Product|null $product */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Product> $pairableProducts */
    $isEdit = $product !== null;
    $locales = ['de' => 'DE', 'en' => 'EN', 'fr' => 'FR'];

    $allergenLabel = fn (Allergen $a) => match ($a) {
        Allergen::Gluten => __('Gluten'),
        Allergen::Crustaceans => __('Crustaceans'),
        Allergen::Eggs => __('Eggs'),
        Allergen::Fish => __('Fish'),
        Allergen::Peanuts => __('Peanuts'),
        Allergen::Soybeans => __('Soybeans'),
        Allergen::Milk => __('Milk'),
        Allergen::Nuts => __('Nuts'),
        Allergen::Celery => __('Celery'),
        Allergen::Mustard => __('Mustard'),
        Allergen::Sesame => __('Sesame'),
        Allergen::Sulphites => __('Sulphites'),
        Allergen::Lupin => __('Lupin'),
        Allergen::Molluscs => __('Molluscs'),
    };

    $dietaryLabel = fn (DietaryTag $d) => match ($d) {
        DietaryTag::Vegan => __('Vegan'),
        DietaryTag::Vegetarian => __('Vegetarian'),
        DietaryTag::ContainsMeat => __('Contains meat'),
        DietaryTag::ContainsFish => __('Contains fish'),
        DietaryTag::ContainsDairy => __('Contains dairy'),
        DietaryTag::ContainsEggs => __('Contains eggs'),
    };

    $tasteLabel = fn (TasteProfile $t) => match ($t) {
        TasteProfile::Sweet => __('Sweet'),
        TasteProfile::Earthy => __('Earthy'),
        TasteProfile::Fresh => __('Fresh'),
        TasteProfile::Peppery => __('Peppery'),
        TasteProfile::Bitter => __('Bitter'),
        TasteProfile::Umami => __('Umami'),
        TasteProfile::Crisp => __('Crisp'),
        TasteProfile::Herbal => __('Herbal'),
        TasteProfile::Nutty => __('Nutty'),
        TasteProfile::Spicy => __('Spicy'),
        TasteProfile::Sour => __('Sour'),
        TasteProfile::Rich => __('Rich'),
    };

    $selectedAllergens = (array) old('allergens', $product?->allergens ?? []);
    $selectedDietary = (array) old('dietary_tags', $product?->dietary_tags ?? []);
    $selectedTaste = (array) old('taste_profile_tags', $product?->taste_profile_tags ?? []);
    $selectedPairings = array_map('intval', (array) old('pairings', $product?->pairings?->pluck('id')->all() ?? []));

    $buyingPriceValue = old('buying_price');
    if ($buyingPriceValue === null && $product?->buying_price_cents !== null) {
        $buyingPriceValue = number_format(((int) $product->buying_price_cents) / 100, 2, '.', '');
    }

    // Open the tab whose locale already has a validation error so the
    // admin doesn't have to hunt for the field that failed.
    $translationFields = ['name', 'unit', 'origin', 'variety', 'taste_profile'];
    $initialTab = 'de';
    foreach (array_keys($locales) as $loc) {
        foreach ($translationFields as $f) {
            if ($errors->has("translations.$loc.$f")) {
                $initialTab = $loc;
                break 2;
            }
        }
    }
@endphp

<form method="post" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data">
    @csrf
    @if (($method ?? 'post') !== 'post')
        @method($method)
    @endif

    <div>
        <x-input-label for="producer_id" :value="__('Producer')" required />
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

    <div class="space-y-4" x-data="{ tab: '{{ $initialTab }}' }">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('Translations') }}</h2>
            <p class="text-xs text-gray-600">{{ __('All three languages are required.') }}</p>
        </div>

        <div class="flex gap-1 border-b border-gray-200" role="tablist">
            @foreach ($locales as $locale => $label)
                @php
                    $hasError = false;
                    foreach ($translationFields as $f) {
                        if ($errors->has("translations.$locale.$f")) { $hasError = true; break; }
                    }
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
                $t = $product?->translations?->firstWhere('locale', $locale);
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

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <x-input-label :for="'translations_'.$locale.'_unit'" :value="__('Unit')" required />
                            <x-text-input
                                :id="'translations_'.$locale.'_unit'"
                                :name="'translations['.$locale.'][unit]'"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('translations.'.$locale.'.unit', $t?->unit)"
                                required
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.unit')" />
                        </div>

                        <div>
                            <x-input-label :for="'translations_'.$locale.'_origin'" :value="__('Origin')" required />
                            <x-text-input
                                :id="'translations_'.$locale.'_origin'"
                                :name="'translations['.$locale.'][origin]'"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('translations.'.$locale.'.origin', $t?->origin)"
                                required
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.origin')" />
                        </div>

                        <div>
                            <x-input-label :for="'translations_'.$locale.'_variety'" :value="__('Variety')" required />
                            <x-text-input
                                :id="'translations_'.$locale.'_variety'"
                                :name="'translations['.$locale.'][variety]'"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('translations.'.$locale.'.variety', $t?->variety)"
                                required
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.variety')" />
                        </div>
                    </div>

                    <div>
                        <x-input-label :for="'translations_'.$locale.'_taste_profile'" :value="__('Taste profile')" required />
                        <textarea
                            id="{{ 'translations_'.$locale.'_taste_profile' }}"
                            name="{{ 'translations['.$locale.'][taste_profile]' }}"
                            rows="2"
                            class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
                            required
                        >{{ old('translations.'.$locale.'.taste_profile', $t?->taste_profile) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.taste_profile')" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div>
        <x-input-label for="category" :value="__('Category')" required />
        <select id="category" name="category" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm" required>
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected(old('category', $product?->category) === $cat)>{{ __('admin.category.'.$cat) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="unit_price" :value="__('Unit price')" required />
            <x-text-input id="unit_price" name="unit_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('unit_price', $product?->unit_price)" required />
            <p class="mt-1 text-xs text-gray-500">{{ __('Customer-facing selling price.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('unit_price')" />
        </div>
        <div>
            <x-input-label for="buying_price" :value="__('Buying price')" />
            <x-text-input id="buying_price" name="buying_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$buyingPriceValue" />
            <p class="mt-1 text-xs text-gray-500">{{ __('What we pay the producer. Used for margin reporting.') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('buying_price')" />
        </div>
    </div>

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Attributes') }}</h2>

        <div>
            <p class="text-xs font-medium text-gray-700 uppercase tracking-wide">{{ __('Allergens') }}</p>
            <p class="mt-0.5 text-xs text-gray-500">{{ __('EU-regulated major food allergens present in this product.') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-y-1 sm:grid-cols-3">
                @foreach ($allergenCases as $allergen)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allergens[]" value="{{ $allergen->value }}" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" @checked(in_array($allergen->value, $selectedAllergens, true))>
                        <span>{{ $allergenLabel($allergen) }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('allergens')" />
        </div>

        <div>
            <p class="text-xs font-medium text-gray-700 uppercase tracking-wide">{{ __('Dietary tags') }}</p>
            <p class="mt-0.5 text-xs text-gray-500">{{ __('Drives automatic filtering against subscriber dietary preferences.') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-y-1 sm:grid-cols-3">
                @foreach ($dietaryCases as $tag)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="dietary_tags[]" value="{{ $tag->value }}" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" @checked(in_array($tag->value, $selectedDietary, true))>
                        <span>{{ $dietaryLabel($tag) }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('dietary_tags')" />
        </div>

        <div>
            <p class="text-xs font-medium text-gray-700 uppercase tracking-wide">{{ __('Taste profile tags') }}</p>
            <p class="mt-0.5 text-xs text-gray-500">{{ __('Structured tags shown as chips to customers. Separate from the prose above.') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-y-1 sm:grid-cols-3">
                @foreach ($tasteCases as $taste)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="taste_profile_tags[]" value="{{ $taste->value }}" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" @checked(in_array($taste->value, $selectedTaste, true))>
                        <span>{{ $tasteLabel($taste) }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('taste_profile_tags')" />
        </div>
    </div>

    <div
        x-data="{ query: '' }"
        class="rounded border border-gray-200 bg-white p-4 space-y-3"
    >
        <div>
            <h2 class="text-sm font-semibold text-gray-900">{{ __('Pairings') }}</h2>
            <p class="mt-0.5 text-xs text-gray-500">{{ __('Products this one is commonly served with.') }}</p>
        </div>
        <x-text-input type="search" x-model="query" placeholder="{{ __('Filter…') }}" class="w-full sm:w-64" />
        <div class="max-h-64 overflow-y-auto rounded border border-gray-200 p-2">
            @forelse ($pairableProducts as $candidate)
                @php
                    $candName = $candidate->name;
                    $candSelected = in_array((int) $candidate->id, $selectedPairings, true);
                @endphp
                <label
                    class="flex items-center gap-2 px-2 py-1 text-sm text-gray-700"
                    x-show="query === '' || '{{ Str::lower(addslashes($candName)) }}'.includes(query.toLowerCase())"
                >
                    <input type="checkbox" name="pairings[]" value="{{ $candidate->id }}" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" @checked($candSelected)>
                    <span>{{ $candName }}</span>
                </label>
            @empty
                <p class="px-2 py-1 text-sm text-gray-500">{{ __('No other products yet.') }}</p>
            @endforelse
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('pairings')" />
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

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">{{ __('Gallery') }}</h2>
            <p class="mt-0.5 text-xs text-gray-500">{{ __('Additional images shown on the product detail page. Up to 12 images, 5 MB each.') }}</p>
        </div>

        @if ($isEdit && $product->images->isNotEmpty())
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($product->images as $image)
                    @php $imgUrl = $image->url(); @endphp
                    <div class="rounded border border-gray-200 p-2 space-y-2">
                        @if ($imgUrl)
                            <img src="{{ $imgUrl }}" alt="{{ $image->alt ?? '' }}" class="h-24 w-full rounded object-cover">
                        @else
                            <div class="flex h-24 w-full items-center justify-center rounded bg-gray-100 text-xs text-gray-400">{{ __('Missing') }}</div>
                        @endif
                        <x-text-input
                            type="text"
                            name="gallery_alt[{{ $image->id }}]"
                            :value="old('gallery_alt.'.$image->id, $image->alt)"
                            placeholder="{{ __('Alt text (optional)') }}"
                            class="w-full text-xs"
                        />
                        <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" name="gallery_remove[]" value="{{ $image->id }}" class="rounded border-gray-300 text-red-600 focus:ring-red-600" @checked(in_array((int) $image->id, array_map('intval', (array) old('gallery_remove', [])), true))>
                            <span>{{ __('Remove') }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('gallery_remove')" />
        @endif

        <div>
            <x-input-label for="gallery_images" :value="__('Add images')" />
            <input id="gallery_images" name="gallery_images[]" type="file" multiple accept="image/*" class="mt-1 block w-full text-sm text-gray-600" />
            <x-input-error class="mt-2" :messages="$errors->get('gallery_images')" />
            <x-input-error class="mt-2" :messages="$errors->get('gallery_images.0')" />
        </div>
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $isEdit ? __('Save') : __('Create product') }}</x-primary-button>
        <a href="{{ route('app.admin.products.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('Cancel') }}</a>
    </div>
</form>
