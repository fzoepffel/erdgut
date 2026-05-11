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

    // E3 (UX notes): pre-compute the initial missing-fields map so the
    // tabs are red on first render whenever a returning failed
    // submission has empty required fields. Alpine's recheckTabs()
    // then keeps the map current as the admin types.
    $initialTabMissing = [];
    foreach ($locales as $locale => $_) {
        $hasMissing = false;
        $t = $product?->translations?->firstWhere('locale', $locale);
        foreach ($translationFields as $f) {
            $current = old("translations.$locale.$f", $t?->$f);
            if (trim((string) $current) === '') {
                $hasMissing = true;
                break;
            }
        }
        $initialTabMissing[$locale] = $hasMissing;
    }
@endphp

<form method="post" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data">
    @csrf
    @if (($method ?? 'post') !== 'post')
        @method($method)
    @endif

    @php
        // F3 (UX-review notes): the plain <select> doesn't scale past
        // ~30 producers. We feed the same producer rows into an Alpine
        // combobox below — it filters as the admin types, keyboard
        // navigates with ↑/↓/Enter/Escape, and stores the chosen ID
        // in a hidden input so the existing form contract is preserved.
        $producerOptions = $producers->map(fn ($p) => [
            'id' => (int) $p->id,
            'name' => (string) $p->name,
        ])->values()->all();
        $initialProducerId = (int) old('producer_id', $product?->producer_id ?? 0);
        $initialProducerName = '';
        foreach ($producerOptions as $option) {
            if ($option['id'] === $initialProducerId) {
                $initialProducerName = $option['name'];
                break;
            }
        }
    @endphp
    <div
        x-data='{
            options: {!! json_encode($producerOptions, JSON_UNESCAPED_UNICODE) !!},
            selectedId: {{ $initialProducerId ?: 'null' }},
            query: {!! json_encode($initialProducerName) !!},
            open: false,
            highlight: 0,
            get filtered() {
                const q = this.query.trim().toLocaleLowerCase();
                if (q === "") return this.options;
                return this.options.filter(o => o.name.toLocaleLowerCase().includes(q));
            },
            pick(option) {
                this.selectedId = option.id;
                this.query = option.name;
                this.open = false;
            },
            onInput() {
                this.open = true;
                this.highlight = 0;
                // Typing breaks the previous selection until the admin
                // picks (or the input matches an option exactly).
                const exact = this.options.find(o => o.name.toLocaleLowerCase() === this.query.trim().toLocaleLowerCase());
                this.selectedId = exact ? exact.id : null;
            },
            onKey(ev) {
                const list = this.filtered;
                if (ev.key === "ArrowDown") {
                    ev.preventDefault();
                    this.open = true;
                    this.highlight = Math.min(list.length - 1, this.highlight + 1);
                } else if (ev.key === "ArrowUp") {
                    ev.preventDefault();
                    this.highlight = Math.max(0, this.highlight - 1);
                } else if (ev.key === "Enter") {
                    if (this.open && list[this.highlight]) {
                        ev.preventDefault();
                        this.pick(list[this.highlight]);
                    }
                } else if (ev.key === "Escape") {
                    this.open = false;
                }
            },
        }'
        x-on:click.outside="open = false"
        class="relative"
    >
        <x-input-label for="producer_id_search" :value="__('Producer')" required />
        <input
            id="producer_id_search"
            type="text"
            class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
            x-model="query"
            x-on:input="onInput()"
            x-on:focus="open = true"
            x-on:keydown="onKey($event)"
            placeholder="{{ __('admin.products.producer_search_placeholder') }}"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-controls="producer-combobox-listbox"
            data-testid="product-form-producer-combobox"
        />
        {{-- Hidden input is what actually submits with the form. Stays
             empty until a real option is picked, so the existing
             `required|exists:producers,id` rule rejects free-form
             garbage. --}}
        <input type="hidden" name="producer_id" x-bind:value="selectedId ?? ''" />

        <ul
            id="producer-combobox-listbox"
            role="listbox"
            x-show="open && filtered.length > 0"
            x-cloak
            class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg"
        >
            <template x-for="(option, idx) in filtered" :key="option.id">
                <li
                    role="option"
                    x-bind:aria-selected="selectedId === option.id"
                    x-bind:class="idx === highlight ? 'bg-emerald-50 text-emerald-900' : 'text-gray-700 hover:bg-gray-50'"
                    class="cursor-pointer px-3 py-2 text-sm"
                    x-on:mouseenter="highlight = idx"
                    x-on:click="pick(option)"
                    x-text="option.name"
                ></li>
            </template>
        </ul>

        <p
            x-show="open && filtered.length === 0 && query.trim() !== ''"
            x-cloak
            class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 shadow-lg"
        >{{ __('No producers match.') }}</p>

        <x-input-error class="mt-2" :messages="$errors->get('producer_id')" />
    </div>

    <div class="space-y-4"
         x-data='{
            tab: "{{ $initialTab }}",
            tabMissing: {!! json_encode($initialTabMissing, JSON_UNESCAPED_UNICODE) !!},
            recheckTabs() {
                Object.keys(this.tabMissing).forEach(loc => {
                    const fields = ["name", "unit", "origin", "variety", "taste_profile"];
                    let missing = false;
                    for (const f of fields) {
                        const el = document.getElementById("translations_" + loc + "_" + f);
                        if (!el || el.value.trim() === "") { missing = true; break; }
                    }
                    this.tabMissing[loc] = missing;
                });
            },
         }'
         x-init="recheckTabs()">
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
                    {{-- E3 (UX notes): tab visual state combines server-side
                         errors and the live missing-fields map so admins see
                         empty required tabs immediately, not only after a
                         failed submit. --}}
                    :class="(tabMissing['{{ $locale }}'] || {{ $hasError ? 'true' : 'false' }})
                        ? (tab === '{{ $locale }}'
                            ? 'border-rose-600 bg-rose-50 text-rose-800'
                            : 'border-rose-300 bg-rose-50/60 text-rose-700 hover:bg-rose-50')
                        : (tab === '{{ $locale }}'
                            ? 'border-emerald-600 text-emerald-700'
                            : 'border-transparent text-gray-600 hover:text-gray-900')"
                    class="relative -mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                    x-bind:title="(tabMissing['{{ $locale }}'] || {{ $hasError ? 'true' : 'false' }}) ? '{{ __('admin.form.tab_has_missing_fields') }}' : ''"
                    data-testid="product-form-tab-{{ $locale }}"
                >
                    {{ $label }}
                    <span
                        x-show="tabMissing['{{ $locale }}'] || {{ $hasError ? 'true' : 'false' }}"
                        x-cloak
                        class="inline-flex h-2 w-2 rounded-full bg-rose-600"
                        aria-hidden="true"
                        data-testid="product-form-tab-missing-{{ $locale }}"
                    ></span>
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
                            x-on:input="recheckTabs()"
                        />
                        <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.name')" />
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label :for="'translations_'.$locale.'_origin'" :value="__('Origin')" required />
                            <x-text-input
                                :id="'translations_'.$locale.'_origin'"
                                :name="'translations['.$locale.'][origin]'"
                                type="text"
                                class="mt-1 block w-full"
                                :value="old('translations.'.$locale.'.origin', $t?->origin)"
                                required
                                x-on:input="recheckTabs()"
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
                                x-on:input="recheckTabs()"
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
                            x-on:input="recheckTabs()"
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
            {{-- F2 (UX notes): force an explicit category choice instead of
                 silently defaulting to the first enum value (Backwaren). The
                 placeholder is a disabled empty option that Laravel's
                 `required` rule will reject, so a "saved as Backwaren by
                 mistake" outcome stops being possible. --}}
            @php
                // Use the full block form rather than the single-line
                // shorthand: the latter breaks subsequent block compilation.
                // See docs/feedback_blade_php_shorthand.md.
                $currentCategory = old('category', $product?->category);
            @endphp
            <option value="" disabled @selected($currentCategory === null || $currentCategory === '')>
                {{ __('— Select category —') }}
            </option>
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected($currentCategory === $cat)>{{ __('admin.category.'.$cat) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>

    {{-- Unit + natural-unit price live in a shared Alpine state because
         the price input's label depends on the chosen unit's natural
         denomination (g → "Preis pro kg", ml → "Preis pro L", others →
         "Preis pro Stück / kg / Bund"). The submitted field is
         `price_per_natural_unit`; the controller converts to the
         per-storage-unit price before persist. --}}
    @php
        $currentUnit = old('unit', $product?->unit?->value);
        // Pre-fill the natural-unit price when editing: storage price
        // (per g / ml / kg / etc.) × storageUnitsPerNaturalUnit. For
        // first-time-create this stays empty so the placeholder shows.
        $defaultNaturalPrice = null;
        if ($product?->unit !== null && $product?->unit_price !== null) {
            $defaultNaturalPrice = round((float) $product->unit_price * $product->unit->storageUnitsPerNaturalUnit(), 2);
        }
        $oldNaturalPrice = old('price_per_natural_unit', $defaultNaturalPrice);

        // Pre-build the per-unit-case natural label map so Alpine can
        // swap the field label live without another lookup.
        $naturalLabels = [];
        foreach (\App\Enums\ProductUnit::cases() as $uc) {
            $naturalLabels[$uc->value] = __('units.'.$uc->naturalUnit()->value);
        }
    @endphp

    <div
        x-data="{
            unit: @js((string) ($currentUnit ?? '')),
            naturalLabels: @js($naturalLabels),
            get naturalLabel() { return this.naturalLabels[this.unit] ?? ''; },
            get hasUnit() { return this.unit !== ''; },
        }"
        class="space-y-6"
    >
        <div>
            <x-input-label for="unit" :value="__('Unit')" required />
            <select id="unit" name="unit" x-model="unit" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm sm:w-48" required>
                <option value="">{{ __('— Select unit —') }}</option>
                @foreach (\App\Enums\ProductUnit::cases() as $unitCase)
                    <option value="{{ $unitCase->value }}">
                        {{ __('units.'.$unitCase->value) }} ({{ $unitCase->value }})
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.unit_hint') }}</p>
            <x-input-error class="mt-2" :messages="$errors->get('unit')" />
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <label for="price_per_natural_unit" class="block text-sm font-medium text-gray-700">
                    <span x-show="!hasUnit">{{ __('admin.product_form.price_label_pick_unit_first') }}</span>
                    <span x-show="hasUnit" x-cloak>
                        {{ __('admin.product_form.price_label_per') }}
                        <span x-text="naturalLabel" class="font-semibold"></span>
                    </span>
                    <span class="text-rose-500">*</span>
                </label>
                <x-text-input
                    id="price_per_natural_unit"
                    name="price_per_natural_unit"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full"
                    :value="$oldNaturalPrice"
                    x-bind:placeholder="hasUnit ? ('z. B. 21,50 €/' + naturalLabel) : ''"
                    required
                />
                <p class="mt-1 text-xs text-gray-500" x-show="hasUnit" x-cloak>
                    {{ __('admin.product_form.price_hint_natural_unit') }}
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('price_per_natural_unit')" />
                <x-input-error class="mt-2" :messages="$errors->get('unit_price')" />
            </div>
            <div>
                <x-input-label for="buying_price" :value="__('Buying price')" />
                <x-text-input id="buying_price" name="buying_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$buyingPriceValue" />
                <p class="mt-1 text-xs text-gray-500">{{ __('What we pay the producer. Used for margin reporting.') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('buying_price')" />
            </div>
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
        {{-- Both publication flags default to FALSE on create — operators
             must explicitly opt in once the product is fully filled out.
             On edit, fall back to whatever's stored on the row. --}}
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_available" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_available', $product?->is_available ?? false))>
            <span class="text-sm text-gray-700">{{ __('Available in shop') }}</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_published" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_published', $product?->is_published ?? false))>
            <span class="text-sm text-gray-700">{{ __('Published') }}</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_egg_addon" value="1" class="rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-600" @checked(old('is_egg_addon', $product?->is_egg_addon))>
            <span class="text-sm text-gray-700">{{ __('admin.products.is_egg_addon_label') }}</span>
        </label>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_test" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_test', $product?->is_test))>
            <span class="text-sm text-gray-700">{{ __('Test data') }}</span>
        </label>
    </div>
    <x-input-error class="mt-2" :messages="$errors->get('is_seasonal')" />
    <p class="text-xs text-amber-700 -mt-1 sm:col-span-full">{{ __('admin.products.is_egg_addon_hint') }}</p>

    {{-- F5 (UX notes): the boolean trio (Published + Available + Seasonal)
         interacts non-trivially. The shop scope `visibleInCatalog` requires
         BOTH published AND available, so "Published" alone isn't enough —
         the older helper text gave the wrong impression. This collapsible
         panel spells out the customer-side consequence of each combo so
         operators know what they're saving. --}}
    <details class="mt-2 group border border-gray-200 bg-gray-50 rounded-md" data-testid="product-visibility-explainer">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-800 hover:bg-gray-100 select-none flex items-center justify-between">
            <span>{{ __('admin.products.visibility_explainer_title') }}</span>
            <span aria-hidden="true" class="text-gray-500 group-open:rotate-180 transition-transform">▾</span>
        </summary>
        <div class="border-t border-gray-200 px-4 py-3 space-y-3 text-sm text-gray-700">
            <p>{{ __('admin.products.visibility_explainer_intro') }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            <th scope="col" class="py-2 pe-3">{{ __('Published') }}</th>
                            <th scope="col" class="py-2 pe-3">{{ __('Available in shop') }}</th>
                            <th scope="col" class="py-2 pe-3">{{ __('admin.products.visibility_explainer_col_outcome') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr>
                            <td class="py-2 pe-3 text-emerald-700">✓</td>
                            <td class="py-2 pe-3 text-emerald-700">✓</td>
                            <td class="py-2 pe-3">{{ __('admin.products.visibility_outcome_visible') }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pe-3 text-emerald-700">✓</td>
                            <td class="py-2 pe-3 text-rose-700">✗</td>
                            <td class="py-2 pe-3">{{ __('admin.products.visibility_outcome_hidden_unavailable') }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pe-3 text-rose-700">✗</td>
                            <td class="py-2 pe-3 text-gray-500">—</td>
                            <td class="py-2 pe-3">{{ __('admin.products.visibility_outcome_draft') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-600">{{ __('admin.products.visibility_explainer_seasonal_hint') }}</p>
        </div>
    </details>
    <x-input-error class="mt-2" :messages="$errors->get('is_available')" />
    <x-input-error class="mt-2" :messages="$errors->get('is_published')" />
    <x-input-error class="mt-2" :messages="$errors->get('is_egg_addon')" />
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
            @if (! $isEdit)
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.gallery_alt_after_save_hint') }}</p>
            @endif
            <x-input-error class="mt-2" :messages="$errors->get('gallery_images')" />
            <x-input-error class="mt-2" :messages="$errors->get('gallery_images.0')" />
        </div>
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ $isEdit ? __('Save') : __('Create product') }}</x-primary-button>
        <a href="{{ route('app.admin.products.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('Cancel') }}</a>
    </div>
</form>
