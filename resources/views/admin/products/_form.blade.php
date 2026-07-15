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
    $translationFields = ['name', 'unit', 'origin', 'variety', 'short_description', 'taste_profile'];
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

@php
    // F3 (UX-review notes): the plain <select> doesn't scale past
    // ~30 producers, so the picker is an Alpine combobox (filter as
    // you type, ↑/↓/Enter/Escape). Since the multi-producer epic a
    // product can be supplied by SEVERAL farms: selections render as
    // ordered chips, the first chip is the primary farm (mirrored
    // into products.producer_id), and each chip carries its own
    // hidden producer_ids[] input so DOM order = pivot position.
    $producerOptions = $producers->map(fn ($p) => [
        'id' => (int) $p->id,
        'name' => (string) $p->name,
    ])->values()->all();
    $producerOptionsById = collect($producerOptions)->keyBy('id');

    // Prefill priority: old() after a failed submit (or the E4
    // preselect flash) → pivot order on edit → legacy single column.
    $selectedProducerIds = old('producer_ids');
    if (! is_array($selectedProducerIds)) {
        $selectedProducerIds = $product !== null && $product->producers->isNotEmpty()
            ? $product->producers->pluck('id')->all()
            : array_filter([(int) old('producer_id', $product?->producer_id ?? 0)]);
    }
    $initialSelectedProducers = collect($selectedProducerIds)
        ->map(fn ($id) => $producerOptionsById->get((int) $id))
        ->filter()
        ->values()
        ->all();

    // Per-farm buying prices (display-unit euros): old() after a
    // failed submit, else the pivot overrides on edit.
    $initialProducerPrices = old('producer_buying_prices');
    if (! is_array($initialProducerPrices)) {
        $initialProducerPrices = [];
        foreach (($product?->producers ?? collect()) as $supplier) {
            if ($supplier->pivot->buying_price_cents !== null) {
                $initialProducerPrices[$supplier->id] = number_format(((int) $supplier->pivot->buying_price_cents) / 100, 2, '.', '');
            }
        }
    }
@endphp
    {{-- Unit, display unit, and price live in a shared Alpine state so
         the price label and the allowed display-unit options stay in
         sync with the chosen kind. The admin enters the price in
         whichever display unit they pick (€/g, €/100 g, €/kg, €/ml, €/L,
         €/Stück, €/Bund); the controller divides by the kind's
         canonical factor before persisting. --}}
    @php
        // Use the full block form rather than the single-line shorthand:
        // the latter breaks subsequent block compilation. See
        // docs/feedback_blade_php_shorthand.md.
        $currentUnit = old('unit', $product?->unit?->value);
        $currentDisplayUnit = old('price_display_unit', $product?->price_display_unit);

        // Pre-fill the price using the currently-chosen display unit so
        // the round-trip is stable. Storage = €/canonical; display =
        // storage × canonicalUnitsPerDisplay.
        $defaultPrice = null;
        if ($product?->unit !== null && $product?->unit_price !== null) {
            $du = $currentDisplayUnit ?? $product->unit->defaultDisplayUnit();
            // Guard: after a failed save where the admin switched unit
            // KIND, `old('price_display_unit')` can belong to a different
            // kind than the persisted `$product->unit` (e.g. 'bunch' while
            // the stored unit is still 'mass'). Converting against the
            // stored kind would throw. Fall back to the stored kind's
            // default display unit in that case.
            if (! in_array($du, $product->unit->allowedDisplayUnits(), true)) {
                $du = $product->unit->defaultDisplayUnit();
            }
            $factor = $product->unit->canonicalUnitsPerDisplay($du);
            $defaultPrice = round((float) $product->unit_price * $factor, 2);
        }
        $oldPrice = old('price', $defaultPrice);

        $allowedDisplayMap = [];
        $kindLabels = [];
        foreach (\App\Enums\ProductUnit::cases() as $uc) {
            $allowedDisplayMap[$uc->value] = $uc->allowedDisplayUnits();
            $kindLabels[$uc->value] = __('units.'.$uc->value);
        }
        $displayLabels = [
            'g' => 'g',
            '100g' => '100 g',
            'kg' => 'kg',
            'ml' => 'ml',
            'l' => 'L',
            'piece' => __('units.piece'),
            'bunch' => __('units.bunch'),
        ];

        // Optional display word for piece products ("Topf", "Beutel", ...):
        // singular forms for the select and the "€/<symbol>" hints.
        $countLabelOptions = [];
        foreach (\App\Enums\CountUnitLabel::cases() as $countCase) {
            $countLabelOptions[$countCase->value] = trans_choice($countCase->labelKey(), 1);
        }
        $currentCountLabel = (string) old('count_unit_label', $product?->count_unit_label?->value ?? '');
    @endphp

<form method="post" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data"
      x-on:submit="submitting = true"
      {{-- Producer picker state lives on the form so both the farm
           chips (top) and the per-farm buying prices (pricing
           section) share it. --}}
      x-data='{
            submitting: false,
            options: {!! json_encode($producerOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            selected: {!! json_encode($initialSelectedProducers, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            prices: {!! json_encode($initialProducerPrices === [] ? new stdClass : $initialProducerPrices, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            query: "",
            open: false,
            highlight: 0,
            get available() {
                return this.options.filter(o => !this.selected.some(s => s.id === o.id));
            },
            get filtered() {
                const q = this.query.trim().toLocaleLowerCase();
                if (q === "") return this.available;
                return this.available.filter(o => o.name.toLocaleLowerCase().includes(q));
            },
            add(option) {
                this.selected.push(option);
                this.query = "";
                this.highlight = 0;
                this.open = false;
            },
            remove(id) {
                this.selected = this.selected.filter(s => s.id !== id);
            },
            makePrimary(id) {
                const i = this.selected.findIndex(s => s.id === id);
                if (i > 0) this.selected.unshift(this.selected.splice(i, 1)[0]);
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
                        this.add(list[this.highlight]);
                    }
                } else if (ev.key === "Escape") {
                    this.open = false;
                }
            },
            unit: {!! json_encode((string) ($currentUnit ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            displayUnit: {!! json_encode((string) ($currentDisplayUnit ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            countLabel: {!! json_encode($currentCountLabel, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            countWords: {!! json_encode($countLabelOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            allowedDisplayMap: {!! json_encode($allowedDisplayMap, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            displayLabels: {!! json_encode($displayLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
            init() {
                // x-model on the <select> initializes BEFORE the <template
                // x-for> renders its <option>s, so on edit the stored value
                // (e.g. kg) has no matching option yet and the browser
                // coerces the select to the empty default. Re-apply the
                // value once the options exist so the saved choice sticks.
                this.$nextTick(() => {
                    if (this.$refs.displayUnitSelect) {
                        this.$refs.displayUnitSelect.value = this.displayUnit;
                    }
                });
                this.$watch("unit", (val) => {
                    const allowed = this.allowedDisplayMap[val] ?? [];
                    if (! allowed.includes(this.displayUnit)) {
                        // Default to the largest (last) allowed display
                        // unit for the kind (kg / L / piece / bunch).
                        this.displayUnit = allowed.length ? allowed[allowed.length - 1] : "";
                    }
                    // The display word only exists for count kinds (piece/bunch).
                    if (val !== "piece" && val !== "bunch") {
                        this.countLabel = "";
                    }
                });
            },
            get allowed() { return this.allowedDisplayMap[this.unit] ?? []; },
            get displaySymbol() {
                if ((this.unit === "piece" || this.unit === "bunch") && this.countLabel !== "") {
                    return this.countWords[this.countLabel] ?? this.displayLabels[this.displayUnit] ?? this.displayUnit;
                }
                return this.displayLabels[this.displayUnit] ?? this.displayUnit;
            },
            get hasUnit() { return this.unit !== ""; },
            get hasDisplay() { return this.displayUnit !== ""; },
      }'
>
    @csrf
    @if (($method ?? 'post') !== 'post')
        @method($method)
    @endif

    @if (($existingProductNames ?? null) !== null)
        {{-- Duplicate-name advisory data (create form only): the name
             fields match against this while typing. --}}
        <script>window.erdgutExistingProducts = @json($existingProductNames);</script>
    @endif

    @if ($errors->any())
        <div class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert" data-testid="product-form-error-summary">
            {{ trans_choice('admin.product_form.error_summary', $errors->count(), ['count' => $errors->count()]) }}
        </div>
    @endif

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_form.section_basics') }}</h2>
    <div class="space-y-4"
         {{-- Native validation cannot focus required fields inside a
              hidden tab panel and silently blocks submission; jump to the
              tab holding the first invalid control instead. --}}
         x-on:invalid.capture="const p = $event.target.closest('[data-tab-locale]'); if (p) { tab = p.dataset.tabLocale; }"
         x-data='{
            tab: "{{ $initialTab }}",
            tabMissing: {!! json_encode($initialTabMissing, JSON_UNESCAPED_UNICODE) !!},
            recheckTabs() {
                Object.keys(this.tabMissing).forEach(loc => {
                    const fields = ["name", "unit", "origin", "variety", "short_description", "taste_profile"];
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

            <div x-show="tab === '{{ $locale }}'" role="tabpanel" data-tab-locale="{{ $locale }}" class="rounded border border-gray-200 bg-white p-4">
                <div class="grid grid-cols-1 gap-6">
                    {{-- The duplicate-check state is always present (the
                         payload script only exists on create, so hits stay
                         empty on edit). A blade @if INSIDE a component tag
                         below would silently break compilation. --}}
                    <div
                        x-data='{
                            hits: [],
                            check(v) {
                                const q = v.trim().toLocaleLowerCase();
                                if (q.length < 3) { this.hits = []; return; }
                                this.hits = (window.erdgutExistingProducts || []).filter(p =>
                                    Object.values(p.names).some(n => {
                                        const m = n.toLocaleLowerCase();
                                        return m !== "" && (m.includes(q) || q.includes(m));
                                    })
                                ).slice(0, 3);
                            },
                        }'
                    >
                        <x-input-label :for="'translations_'.$locale.'_name'" :value="__('Name')" required />
                        <x-text-input
                            :id="'translations_'.$locale.'_name'"
                            :name="'translations['.$locale.'][name]'"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('translations.'.$locale.'.name', $t?->name)"
                            required
                            x-on:input="recheckTabs()"
                            x-on:input.debounce.250ms="check($event.target.value)"
                        />
                        @if (($existingProductNames ?? null) !== null)
                            {{-- Advisory only: a matching name suggests adding the
                                 farm to the existing product instead of creating a
                                 twin. Never blocks saving. --}}
                            <template x-if="hits.length > 0">
                                <div class="mt-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900" data-testid="product-duplicate-warning">
                                    <span>{{ __('admin.products.duplicate_name_exists') }}</span>
                                    <template x-for="hit in hits" :key="hit.id">
                                        <a
                                            x-bind:href="hit.url"
                                            class="ms-1 font-medium underline underline-offset-2"
                                            x-text="(hit.names['{{ $locale }}'] || Object.values(hit.names).find(n => n !== '') || '') + (hit.producer ? ' (' + hit.producer + ')' : '')"
                                        ></a>
                                    </template>
                                    <p class="mt-1 text-xs text-amber-800">{{ __('admin.products.duplicate_name_hint') }}</p>
                                </div>
                            </template>
                        @endif
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
                        <x-input-label :for="'translations_'.$locale.'_short_description'" :value="__('admin.product_form.short_description_label')" required />
                        <textarea
                            id="{{ 'translations_'.$locale.'_short_description' }}"
                            name="{{ 'translations['.$locale.'][short_description]' }}"
                            rows="2"
                            maxlength="240"
                            class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
                            required
                            x-on:input="recheckTabs()"
                        >{{ old('translations.'.$locale.'.short_description', $t?->short_description) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.short_description_hint') }}</p>
                        <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.short_description')" />
                    </div>

                    <div>
                        <x-input-label :for="'translations_'.$locale.'_taste_profile'" :value="__('admin.product_form.long_description_label')" required />
                        <textarea
                            id="{{ 'translations_'.$locale.'_taste_profile' }}"
                            name="{{ 'translations['.$locale.'][taste_profile]' }}"
                            rows="4"
                            class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
                            required
                            x-on:input="recheckTabs()"
                        >{{ old('translations.'.$locale.'.taste_profile', $t?->taste_profile) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.long_description_hint') }}</p>
                        <x-input-error class="mt-2" :messages="$errors->get('translations.'.$locale.'.taste_profile')" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @php
        $currentCategory    = old('category', $product?->category);
        $currentSubcatId    = (int) old('subcategory_id', $product?->subcategory_id ?? 0);

        $subcategoriesByCategory = \App\Models\ProductSubcategory::query()
            ->orderBy('sort_order')
            ->orderBy('label_de')
            ->get()
            ->groupBy('category')
            ->map(fn ($g) => $g->map(fn ($s) => [
                'id'    => $s->id,
                'label' => $s->label(app()->getLocale()),
            ])->values()->all())
            ->all();
    @endphp
    <div
        x-data="{
            category: @js((string) ($currentCategory ?? '')),
            subcategoryId: @js($currentSubcatId),
            subcategoriesByCategory: @js($subcategoriesByCategory),
            get availableSubcategories() {
                return this.subcategoriesByCategory[this.category] ?? [];
            },
            syncSubcategorySelect() {
                this.$nextTick(() => {
                    if (! this.$refs.subcategorySelect) {
                        return;
                    }
                    const value = this.subcategoryId > 0 ? String(this.subcategoryId) : '0';
                    this.$refs.subcategorySelect.value = value;
                });
            },
            init() {
                // x-model on <select> initializes before <template x-for>
                // renders its <option>s, so the browser coerces back to the
                // empty default and the POST carries subcategory_id=0.
                this.syncSubcategorySelect();
                this.$watch('category', () => this.syncSubcategorySelect());
                this.$watch('availableSubcategories', () => this.syncSubcategorySelect());
            },
        }"
        class="space-y-4"
    >
        <div>
            <x-input-label for="category" :value="__('Category')" required />
            <select id="category" name="category"
                    x-model="category"
                    x-on:change="subcategoryId = 0"
                    class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
                    required>
                <option value="" disabled :selected="category === ''">
                    {{ __('— Select category —') }}
                </option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat }}">{{ __('admin.category.'.$cat) }}</option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('category')" />
        </div>

        <div x-show="availableSubcategories.length > 0" x-cloak>
            <div class="flex items-baseline justify-between">
                <x-input-label for="subcategory_id" :value="__('admin.product_form.subcategory_label')" />
                <a href="{{ route('app.admin.subcategories.index') }}"
                   class="text-xs text-emerald-700 underline underline-offset-2 hover:no-underline"
                   target="_blank">
                    {{ __('admin.product_form.subcategory_manage_link') }} →
                </a>
            </div>
            {{-- Hidden input submits the value; the visible <select> is Alpine-only
                 (same pattern as the producer combobox) so x-for option timing
                 cannot drop the chosen subcategory on save. --}}
            <input type="hidden" name="subcategory_id" x-bind:value="subcategoryId > 0 ? subcategoryId : ''" />
            <select id="subcategory_id"
                    x-model="subcategoryId"
                    x-ref="subcategorySelect"
                    class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm">
                <option value="0">{{ __('admin.product_form.subcategory_none') }}</option>
                <template x-for="s in availableSubcategories" :key="s.id">
                    <option :value="s.id" x-text="s.label"></option>
                </template>
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('subcategory_id')" />
        </div>
    </div>

    </div>

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
    <div
        x-on:click.outside="open = false"
        class="relative"
    >
        <x-input-label for="producer_ids_search" :value="__('admin.products.producers_label')" required />

        {{-- Selected farms as ordered chips; the first is the primary. --}}
        <div class="mt-1 flex flex-wrap items-center gap-1.5" data-testid="product-form-producer-chips">
            <template x-for="(s, idx) in selected" :key="s.id">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-sm text-emerald-900">
                    <button
                        type="button"
                        x-on:click="makePrimary(s.id)"
                        x-bind:title="idx === 0 ? '' : '{{ __('admin.products.producer_make_primary') }}'"
                        class="inline-flex items-center gap-1.5"
                    >
                        <span x-text="s.name"></span>
                        <span
                            x-show="idx === 0"
                            x-cloak
                            class="rounded bg-emerald-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white"
                            data-testid="producer-chip-primary-badge"
                        >{{ __('admin.products.producer_primary_badge') }}</span>
                    </button>
                    <button
                        type="button"
                        x-on:click="remove(s.id)"
                        aria-label="{{ __('admin.products.producer_remove') }}"
                        class="text-emerald-700 hover:text-emerald-900"
                    >&times;</button>
                    <input type="hidden" name="producer_ids[]" x-bind:value="s.id" />
                </span>
            </template>
        </div>

        <input
            id="producer_ids_search"
            type="text"
            class="mt-2 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
            x-model="query"
            x-on:input="open = true; highlight = 0"
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
                    x-bind:class="idx === highlight ? 'bg-emerald-50 text-emerald-900' : 'text-gray-700 hover:bg-gray-50'"
                    class="cursor-pointer px-3 py-2 text-sm"
                    x-on:mouseenter="highlight = idx"
                    x-on:click="add(option)"
                    x-text="option.name"
                ></li>
            </template>
        </ul>

        <p
            x-show="open && filtered.length === 0 && query.trim() !== ''"
            x-cloak
            class="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-500 shadow-lg"
        >{{ __('No producers match.') }}</p>

        <x-input-error class="mt-2" :messages="$errors->get('producer_ids')" />
        @foreach ($errors->get('producer_ids.*') as $producerIdMessages)
            <x-input-error class="mt-2" :messages="$producerIdMessages" />
        @endforeach

    </div>

    </div>

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_form.section_unit') }}</h2>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="unit" :value="__('Unit')" required />
                <select id="unit" name="unit" x-model="unit" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm" required>
                    <option value="">{{ __('— Select unit —') }}</option>
                    @foreach (\App\Enums\ProductUnit::cases() as $unitCase)
                        <option value="{{ $unitCase->value }}">{{ __('units.'.$unitCase->value) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.unit_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('unit')" />
            </div>

            <div>
                <x-input-label for="price_display_unit" :value="__('admin.product_form.price_display_unit_label')" required />
                <select id="price_display_unit" name="price_display_unit" x-model="displayUnit" x-ref="displayUnitSelect" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm" required>
                    <option value="">—</option>
                    <template x-for="opt in allowed" :key="opt">
                        <option :value="opt" x-text="displayLabels[opt] ?? opt"></option>
                    </template>
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.price_display_unit_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('price_display_unit')" />
            </div>

            <div x-show="unit === 'piece' || unit === 'bunch'" x-cloak>
                <x-input-label for="count_unit_label" :value="__('admin.product_form.count_unit_label_label')" />
                <select id="count_unit_label" name="count_unit_label" x-model="countLabel" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm">
                    {{-- Empty choice keeps the kind's own word (Stück / Bund). --}}
                    <option value="" x-text="unit === 'bunch' ? {{ Js::from(__('units.bunch')) }} : {{ Js::from(__('units.piece')) }}">{{ __('units.piece') }}</option>
                    @foreach ($countLabelOptions as $countValue => $countWord)
                        <option value="{{ $countValue }}">{{ $countWord }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.count_unit_label_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('count_unit_label')" />
            </div>
        </div>

        @php
            $quantityStepValue = old('quantity_step', $product?->quantity_step ?? '');
            $globalStep = (int) config('shop.quantity_step', 50);
        @endphp
        <div x-show="unit === 'mass' || unit === 'volume'" x-cloak>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="quantity_step" :value="__('admin.product_form.quantity_step_label')" />
                    <div class="mt-1 flex items-center gap-2">
                        <x-text-input
                            id="quantity_step"
                            name="quantity_step"
                            type="number"
                            min="1"
                            step="1"
                            class="block w-32"
                            :value="$quantityStepValue"
                            placeholder="{{ $globalStep }}"
                        />
                        <span class="text-sm text-gray-500" x-text="unit === 'volume' ? 'ml' : 'g'"></span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __('admin.product_form.quantity_step_hint', ['default' => $globalStep]) }}
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('quantity_step')" />
                </div>
            </div>
        </div>

        {{-- Unit descriptors — shown only for piece / bunch products --}}
        @php
            $unitWeightValue = old('unit_weight_grams', $product?->unit_weight_grams ?? '');
            $unitVolumeValue = old('unit_volume_ml', $product?->unit_volume_ml ?? '');
        @endphp
        <div x-show="unit === 'piece' || unit === 'bunch'" x-cloak>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="unit_weight_grams" :value="__('admin.product_form.unit_weight_label')" />
                    <div class="mt-1 flex items-center gap-2">
                        <x-text-input
                            id="unit_weight_grams"
                            name="unit_weight_grams"
                            type="number"
                            min="1"
                            step="1"
                            class="block w-32"
                            :value="$unitWeightValue"
                        />
                        <span class="text-sm text-gray-500">g</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.unit_weight_hint') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('unit_weight_grams')" />
                </div>
                <div>
                    <x-input-label for="unit_volume_ml" :value="__('admin.product_form.unit_volume_label')" />
                    <div class="mt-1 flex items-center gap-2">
                        <x-text-input
                            id="unit_volume_ml"
                            name="unit_volume_ml"
                            type="number"
                            min="1"
                            step="1"
                            class="block w-32"
                            :value="$unitVolumeValue"
                        />
                        <span class="text-sm text-gray-500">ml</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.unit_volume_hint') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('unit_volume_ml')" />
                </div>
            </div>
        </div>
        @php
            $freshnessValue = old(
                'default_freshness_window_days',
                $product?->default_freshness_window_days ?? 1
            );
        @endphp
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="default_freshness_window_days" :value="__('admin.product_form.freshness_label')" />
                <x-text-input
                    id="default_freshness_window_days"
                    name="default_freshness_window_days"
                    type="number"
                    min="1"
                    max="60"
                    step="1"
                    class="mt-1 block w-full sm:w-40"
                    :value="$freshnessValue"
                />
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.freshness_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('default_freshness_window_days')" />
            </div>
        </div>

        {{-- Quantity step — shown only for mass / volume products --}}
    </div>

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_form.section_prices') }}</h2>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <label for="price" class="block text-sm font-medium text-gray-700">
                    <span x-show="!hasDisplay">{{ __('admin.product_form.price_label_pick_unit_first') }}</span>
                    <span x-show="hasDisplay" x-cloak>
                        {{ __('admin.product_form.price_label_per') }}
                        <span x-text="displaySymbol" class="font-semibold"></span>
                    </span>
                    <span class="text-rose-500">*</span>
                </label>
                <x-text-input
                    id="price"
                    name="price"
                    type="number"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full"
                    :value="$oldPrice"
                    x-bind:placeholder="hasDisplay ? ('z. B. 21,50 €/' + displaySymbol) : ''"
                    required
                />
                <p class="mt-1 text-xs text-gray-500" x-show="hasDisplay" x-cloak>
                    {{ __('admin.product_form.price_hint_natural_unit') }}
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('price')" />
                <x-input-error class="mt-2" :messages="$errors->get('unit_price')" />
            </div>
            <div>
                <x-input-label for="buying_price" :value="__('admin.products.buying_price_default_label')" />
                <x-text-input id="buying_price" name="buying_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$buyingPriceValue" />
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.products.buying_price_default_help') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('buying_price')" />
            
                {{-- Per-farm buying price overrides (display-unit euros); empty
                     means the product's default buying price applies. --}}
                <div class="mt-3 space-y-2" data-testid="product-form-producer-prices" x-show="selected.length > 1" x-cloak>
                    <p class="text-xs font-medium text-gray-700">{{ __('admin.products.producer_price_label') }}</p>
                    <template x-for="s in selected" :key="s.id">
                        <div class="flex items-center gap-3">
                            <span class="w-48 truncate text-sm text-gray-700" x-text="s.name"></span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600"
                                x-bind:name="'producer_buying_prices[' + s.id + ']'"
                                x-model="prices[s.id]"
                                placeholder="{{ __('admin.products.producer_price_placeholder') }}"
                            />
                        </div>
                    </template>
                    <p class="text-xs text-gray-500">{{ __('admin.products.producer_price_help') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('producer_buying_prices')" />
                    @foreach ($errors->get('producer_buying_prices.*') as $producerPriceMessages)
                        <x-input-error class="mt-2" :messages="$producerPriceMessages" />
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Optional customer-group (business / B2B) price, in the display unit.
             Empty = business customers pay the standard price. --}}
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <label for="business_price" class="block text-sm font-medium text-gray-700">
                    {{ __('admin.product_form.business_price_label') }}
                    <span x-show="hasDisplay" x-cloak>(€/<span x-text="displaySymbol"></span>)</span>
                </label>
                <x-text-input id="business_price" name="business_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                    :value="old('business_price', $businessPrice !== null ? number_format($businessPrice, 2, '.', '') : '')" />
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.business_price_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('business_price')" />
            </div>
        </div>

        @php
            $currentVatRate = (int) old('vat_rate_percent', $product?->vat_rate_percent ?? 7);
        @endphp
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="vat_rate_percent" :value="__('admin.product_form.vat_rate_label')" required />
                <select
                    id="vat_rate_percent"
                    name="vat_rate_percent"
                    class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm"
                    required
                >
                    <option value="7" @selected($currentVatRate === 7)>{{ __('admin.product_form.vat_rate_7') }}</option>
                    <option value="19" @selected($currentVatRate === 19)>{{ __('admin.product_form.vat_rate_19') }}</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.vat_rate_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('vat_rate_percent')" />
            </div>
        </div>

        {{-- Quantity tiers: volume discounts on the default grade. Threshold +
             price are entered in the display unit (kg). --}}
        @php
            $tierSeed = old('tiers', collect($existingTiers ?? [])->map(fn ($t) => [
                'id' => $t['id'],
                'min_quantity' => rtrim(rtrim(number_format((float) $t['min_quantity'], 3, '.', ''), '0'), '.'),
                'unit_price' => number_format((float) $t['unit_price'], 2, '.', ''),
            ])->values()->all());
        @endphp
        <div x-data="{
            tiers: @js(array_values($tierSeed)),
            add() { this.tiers.push({ id: '', min_quantity: '', unit_price: '' }); },
            remove(i) { this.tiers.splice(i, 1); },
        }">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.product_form.tiers_heading') }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.tiers_hint') }}</p>

            <div class="mt-3 space-y-2">
                <template x-for="(t, i) in tiers" :key="i">
                    <div class="flex flex-wrap items-end gap-3 rounded-md border border-gray-200 p-3">
                        <input type="hidden" :name="'tiers['+i+'][id]'" :value="t.id">
                        <label class="block text-xs text-gray-600">
                            {{ __('admin.product_form.tiers_min_quantity') }}<span x-show="hasDisplay" x-cloak> (<span x-text="displaySymbol"></span>)</span>
                            <input type="number" step="0.001" min="0.001" x-model="t.min_quantity" :name="'tiers['+i+'][min_quantity]'"
                                class="mt-1 block w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                        <label class="block text-xs text-gray-600">
                            {{ __('admin.product_form.tiers_price') }}<span x-show="hasDisplay" x-cloak> (€/<span x-text="displaySymbol"></span>)</span>
                            <input type="number" step="0.01" min="0.01" x-model="t.unit_price" :name="'tiers['+i+'][unit_price]'"
                                x-bind:placeholder="document.getElementById('price')?.value || ''"
                                class="mt-1 block w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                        </label>
                        <button type="button" @click="remove(i)" class="ml-auto pb-2 text-xs font-medium text-rose-600 hover:text-rose-700">{{ __('admin.product_form.tiers_remove') }}</button>
                    </div>
                </template>
            </div>

            <button type="button" @click="add()" class="mt-3 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                + {{ __('admin.product_form.tiers_add') }}
            </button>
        </div>

        <p class="text-xs text-gray-500">
            {{ __('admin.product_form.pricing_calendar_hint') }}
            @if ($isEdit)
                <a href="{{ route('app.admin.products.pricing', $product) }}" class="font-medium text-emerald-700 hover:underline">{{ __('admin.product_form.pricing_calendar_link') }} →</a>
            @endif
        </p>
    </div>

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        {{-- Grades / variants: same product sold in several qualities, each with
             its own price (and, via the weekly catalog, its own stock). --}}
        @php
            // Per-farm buying prices for each grade, keyed producer id →
            // display-unit euros; rides along the variant rows so it works
            // at creation (rows have no ids yet).
            $variantFarmPrices = [];
            foreach (($product?->producerVariantPrices ?? collect()) as $gradePriceRow) {
                $variantFarmPrices[$gradePriceRow->variant_id][$gradePriceRow->producer_id] = number_format($gradePriceRow->buying_price_cents / 100, 2, '.', '');
            }
            $variantSeed = old('variants', collect($existingVariants ?? [])->map(fn ($v) => [
                'id' => $v['id'],
                'name' => [
                    'de' => $v['name']['de'] ?? '',
                    'en' => $v['name']['en'] ?? '',
                    'fr' => $v['name']['fr'] ?? '',
                ],
                'unit_price' => $v['price'] !== null ? number_format((float) $v['price'], 2, '.', '') : '',
                'business_price' => ($v['business_price'] ?? null) !== null ? number_format((float) $v['business_price'], 2, '.', '') : '',
                'is_active' => $v['is_active'],
                'producer_buying_prices' => $variantFarmPrices[$v['id']] ?? [],
                'tiers' => collect($v['tiers'] ?? [])->map(fn ($t) => [
                    'id' => $t['id'],
                    'min_quantity' => rtrim(rtrim(number_format((float) $t['min_quantity'], 3, '.', ''), '0'), '.'),
                    'unit_price' => number_format((float) $t['unit_price'], 2, '.', ''),
                ])->values()->all(),
            ])->values()->all());
            // Alpine needs an object (not []) so x-model can assign by
            // farm id — also normalizes rows rehydrated via old().
            $variantSeed = array_map(function (array $v): array {
                $v['producer_buying_prices'] = (object) array_filter(
                    (array) ($v['producer_buying_prices'] ?? []),
                    fn ($value) => $value !== null && $value !== '',
                );

                return $v;
            }, array_values($variantSeed));
        @endphp
        <div x-data="{
            variants: @js(array_values($variantSeed)),
            add() { this.variants.push({ id: '', name: { de: '', en: '', fr: '' }, unit_price: '', business_price: '', is_active: true, producer_buying_prices: {}, tiers: [] }); },
            remove(i) { this.variants.splice(i, 1); },
            addTier(i) { this.variants[i].tiers.push({ id: '', min_quantity: '', unit_price: '' }); },
            removeTier(i, j) { this.variants[i].tiers.splice(j, 1); },
        }">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('admin.product_form.grades_heading') }}</h3>
            <p class="mt-1 text-xs text-gray-500">{{ __('admin.product_form.grades_hint') }}</p>

            <div class="mt-3 space-y-3">
                <template x-for="(v, i) in variants" :key="i">
                    <div class="rounded-md border border-gray-200 p-3">
                        <input type="hidden" :name="'variants['+i+'][id]'" :value="v.id">
                        <input type="hidden" :name="'variants['+i+'][is_active]'" :value="v.is_active ? 1 : 0">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <label class="block text-xs text-gray-600">{{ __('admin.product_form.grades_name') }} (DE)
                                <input type="text" maxlength="120" x-model="v.name.de" :name="'variants['+i+'][name][de]'" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <label class="block text-xs text-gray-600">{{ __('admin.product_form.grades_name') }} (EN)
                                <input type="text" maxlength="120" x-model="v.name.en" :name="'variants['+i+'][name][en]'" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <label class="block text-xs text-gray-600">{{ __('admin.product_form.grades_name') }} (FR)
                                <input type="text" maxlength="120" x-model="v.name.fr" :name="'variants['+i+'][name][fr]'" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                        </div>
                        <div class="mt-2 flex flex-wrap items-end gap-4">
                            <label class="block text-xs text-gray-600">
                                {{ __('admin.product_form.grades_price') }}<span x-show="hasDisplay" x-cloak> (€/<span x-text="displaySymbol"></span>)</span>
                                <input type="number" step="0.01" min="0.01" x-model="v.unit_price" :name="'variants['+i+'][unit_price]'"
                                    x-bind:placeholder="document.getElementById('price')?.value || ''"
                                    class="mt-1 block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <label class="block text-xs text-gray-600">
                                {{ __('admin.product_form.grades_business_price') }}<span x-show="hasDisplay" x-cloak> (€/<span x-text="displaySymbol"></span>)</span>
                                <input type="number" step="0.01" min="0.01" x-model="v.business_price" :name="'variants['+i+'][business_price]'"
                                    class="mt-1 block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                            </label>
                            <label class="flex items-center gap-2 pb-2 text-xs text-gray-700">
                                <input type="checkbox" x-model="v.is_active" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                                {{ __('admin.product_form.grades_active') }}
                            </label>
                            <button type="button" @click="remove(i)" class="ml-auto pb-2 text-xs font-medium text-rose-600 hover:text-rose-700">{{ __('admin.product_form.grades_remove') }}</button>
                        </div>

                        {{-- Optional buying price per farm for THIS grade;
                             empty = the farm's price, then the default. Works
                             at creation: the row identifies the grade. --}}
                        <div class="mt-3 border-t border-gray-100 pt-3" data-testid="product-form-grade-farm-prices" x-show="selected.length > 1" x-cloak>
                            <p class="text-[0.7rem] font-medium uppercase tracking-wide text-gray-500">{{ __('admin.products.producer_price_label') }}</p>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <template x-for="s in selected" :key="s.id">
                                    <label class="block text-xs text-gray-600">
                                        <span x-text="s.name"></span>
                                        <input type="number" step="0.01" min="0"
                                            :name="'variants['+i+'][producer_buying_prices]['+s.id+']'"
                                            x-model="v.producer_buying_prices[s.id]"
                                            placeholder="{{ __('admin.products.producer_price_placeholder') }}"
                                            class="mt-1 block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                    </label>
                                </template>
                            </div>
                        </div>

                        {{-- Per-grade volume tiers --}}
                        <div class="mt-3 border-t border-gray-100 pt-3">
                            <p class="text-[0.7rem] font-medium uppercase tracking-wide text-gray-500">{{ __('admin.product_form.tiers_heading') }}</p>
                            <div class="mt-2 space-y-2">
                                <template x-for="(t, j) in v.tiers" :key="j">
                                    <div class="flex flex-wrap items-end gap-3">
                                        <input type="hidden" :name="'variants['+i+'][tiers]['+j+'][id]'" :value="t.id">
                                        <label class="block text-xs text-gray-600">
                                            {{ __('admin.product_form.tiers_min_quantity') }}<span x-show="hasDisplay" x-cloak> (<span x-text="displaySymbol"></span>)</span>
                                            <input type="number" step="0.001" min="0.001" x-model="t.min_quantity" :name="'variants['+i+'][tiers]['+j+'][min_quantity]'"
                                                class="mt-1 block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                        </label>
                                        <label class="block text-xs text-gray-600">
                                            {{ __('admin.product_form.tiers_price') }}<span x-show="hasDisplay" x-cloak> (€/<span x-text="displaySymbol"></span>)</span>
                                            <input type="number" step="0.01" min="0.01" x-model="t.unit_price" :name="'variants['+i+'][tiers]['+j+'][unit_price]'"
                                                class="mt-1 block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                                        </label>
                                        <button type="button" @click="removeTier(i, j)" class="pb-2 text-xs font-medium text-rose-600 hover:text-rose-700">{{ __('admin.product_form.tiers_remove') }}</button>
                                    </div>
                                </template>
                            </div>
                            <button type="button" @click="addTier(i)" class="mt-2 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                + {{ __('admin.product_form.tiers_add') }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="add()" class="mt-3 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                + {{ __('admin.product_form.grades_add') }}
            </button>
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

        @php
            // The enum tags drive the checkboxes; custom tags are trilingual
            // `{de,en,fr}` rows stored separately in `custom_taste_tags`.
            $selectedEnumTaste = array_values(array_intersect($selectedTaste, TasteProfile::values()));
            $selectedCustomTaste = [];
            foreach ((array) old('custom_taste_tags', $product?->custom_taste_tags ?? []) as $row) {
                if (is_array($row)) {
                    $selectedCustomTaste[] = [
                        'de' => (string) ($row['de'] ?? ''),
                        'en' => (string) ($row['en'] ?? ''),
                        'fr' => (string) ($row['fr'] ?? ''),
                    ];
                }
            }
        @endphp
        <div
            x-data='{
                enumSelected: {!! json_encode($selectedEnumTaste, JSON_UNESCAPED_UNICODE) !!},
                custom: {!! json_encode($selectedCustomTaste, JSON_UNESCAPED_UNICODE) !!},
                draft: { de: "", en: "", fr: "" },
                add() {
                    const de = this.draft.de.trim();
                    const en = this.draft.en.trim();
                    const fr = this.draft.fr.trim();
                    if (de === "" || en === "" || fr === "") return;
                    this.custom.push({ de, en, fr });
                    this.draft = { de: "", en: "", fr: "" };
                },
                remove(i) { this.custom.splice(i, 1); },
            }'
        >
            <p class="text-xs font-medium text-gray-700 uppercase tracking-wide">{{ __('Taste profile tags') }}</p>
            <p class="mt-0.5 text-xs text-gray-500">{{ __('Structured tags shown as chips to customers. Separate from the prose above.') }}</p>

            {{-- Predefined enum tags. These carry no name attribute — they
                 only drive the Alpine `enumSelected` array; the hidden inputs
                 at the bottom are what actually submit. --}}
            <div class="mt-2 grid grid-cols-2 gap-y-1 sm:grid-cols-3">
                @foreach ($tasteCases as $taste)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" value="{{ $taste->value }}" x-model="enumSelected" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                        <span>{{ $tasteLabel($taste) }}</span>
                    </label>
                @endforeach
            </div>

            {{-- Custom tags: one tag entered in all three languages at once. --}}
            <div class="mt-4 rounded-md border border-dashed border-gray-300 p-3">
                <p class="text-xs font-medium text-gray-700">{{ __('admin.product_form.taste_custom_heading') }}</p>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wide text-gray-500">DE</label>
                        <input type="text" x-model="draft.de" x-on:keydown.enter.prevent="add()" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm text-sm" data-testid="taste-custom-de">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wide text-gray-500">EN</label>
                        <input type="text" x-model="draft.en" x-on:keydown.enter.prevent="add()" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm text-sm" data-testid="taste-custom-en">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wide text-gray-500">FR</label>
                        <input type="text" x-model="draft.fr" x-on:keydown.enter.prevent="add()" class="mt-1 block w-full border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-md shadow-sm text-sm" data-testid="taste-custom-fr">
                    </div>
                </div>
                <button
                    type="button"
                    x-on:click="add()"
                    class="mt-2 inline-flex items-center rounded-md border border-emerald-600 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
                    data-testid="taste-custom-add"
                >{{ __('Add') }}</button>
                <p class="mt-1 text-[11px] text-gray-500">{{ __('admin.product_form.taste_custom_hint') }}</p>

                <div class="mt-3 flex flex-wrap gap-2" x-show="custom.length > 0" x-cloak>
                    <template x-for="(tag, i) in custom" :key="i">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800">
                            <span x-text="tag.de + ' · ' + tag.en + ' · ' + tag.fr"></span>
                            <button type="button" x-on:click="remove(i)" class="text-emerald-600 hover:text-emerald-900" aria-label="{{ __('Remove') }}">&times;</button>
                        </span>
                    </template>
                </div>
            </div>

            {{-- Hidden inputs that actually submit. Enum → taste_profile_tags[],
                 custom → custom_taste_tags[i][de|en|fr]. --}}
            <template x-for="val in enumSelected" :key="'enum-' + val">
                <input type="hidden" name="taste_profile_tags[]" :value="val">
            </template>
            <template x-for="(tag, i) in custom" :key="'custom-' + i">
                <span>
                    <input type="hidden" :name="'custom_taste_tags[' + i + '][de]'" :value="tag.de">
                    <input type="hidden" :name="'custom_taste_tags[' + i + '][en]'" :value="tag.en">
                    <input type="hidden" :name="'custom_taste_tags[' + i + '][fr]'" :value="tag.fr">
                </span>
            </template>

            <x-input-error class="mt-2" :messages="$errors->get('taste_profile_tags')" />
            <x-input-error class="mt-2" :messages="$errors->get('custom_taste_tags')" />
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

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_form.section_visibility') }}</h2>
        <div x-data="{
            seasonal: {{ old('is_seasonal', $product?->is_seasonal) ? 'true' : 'false' }},
            published: {{ old('is_published', $product?->is_published ?? true) ? 'true' : 'false' }},
            available: {{ old('is_available', $product?->is_available ?? true) ? 'true' : 'false' }},
        }">
            {{-- Both publication flags default to TRUE on create (they were
                 forgotten too often); operators opt OUT for drafts. The shop
                 needs BOTH (visibleInCatalog), spelled out in the explainer.
                 The FormRequest merges booleans, so a validation bounce keeps
                 an explicit uncheck. --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="is_published" value="1" x-model="published" class="mt-0.5 rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_published', $product?->is_published ?? true))>
                    <span>
                        <span class="block text-sm text-gray-700">{{ __('Published') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('admin.products.visibility_published_hint') }}</span>
                    </span>
                </label>
                <label class="flex items-start gap-2">
                    <input type="checkbox" name="is_available" value="1" x-model="available" class="mt-0.5 rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_available', $product?->is_available ?? true))>
                    <span>
                        <span class="block text-sm text-gray-700">{{ __('Available in shop') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('admin.products.visibility_available_hint') }}</span>
                    </span>
                </label>
            </div>
            <p x-show="!published || !available" x-cloak class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800" data-testid="product-visibility-warning">
                {{ __('admin.products.visibility_hidden_warning') }}
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('is_published')" />
            <x-input-error class="mt-2" :messages="$errors->get('is_available')" />

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

            <div class="mt-4 border-t border-gray-100 pt-4">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_seasonal" value="1" x-model="seasonal" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_seasonal', $product?->is_seasonal))>
                    <span class="text-sm text-gray-700">{{ __('Seasonal') }}</span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('is_seasonal')" />
    {{-- Recurring yearly season window (month + day). Only shown when the
         product is flagged seasonal; the window repeats every year and may
         wrap the year boundary (e.g. Nov → Feb). Consumed by the restaurant
         (B2B) shop, not the consumer storefront. --}}
    <div x-show="seasonal" x-cloak class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-4">
        <p class="text-sm font-semibold text-gray-800">{{ __('product.season_window_label') }}</p>
        <p class="mt-1 text-xs text-gray-600">{{ __('product.season_hint') }}</p>
        @php
            $seasonMonths = [];
            for ($m = 1; $m <= 12; $m++) {
                $seasonMonths[$m] = \Illuminate\Support\Carbon::create(2024, $m, 1)->locale(app()->getLocale())->isoFormat('MMMM');
            }
        @endphp
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            @foreach (['start' => __('product.season_from'), 'end' => __('product.season_to')] as $key => $label)
                <div>
                    <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</label>
                    <div class="mt-1 flex gap-2">
                        <select name="season_{{ $key }}_month" class="block w-full rounded border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                            <option value="">{{ __('product.season_month') }}</option>
                            @foreach ($seasonMonths as $num => $name)
                                <option value="{{ $num }}" @selected((int) old('season_'.$key.'_month', $product?->{'season_'.$key.'_month'}) === $num)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" max="31" name="season_{{ $key }}_day"
                               placeholder="{{ __('product.season_day') }}"
                               value="{{ old('season_'.$key.'_day', $product?->{'season_'.$key.'_day'}) }}"
                               class="w-24 rounded border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                    </div>
                    <x-input-error class="mt-1" :messages="$errors->get('season_'.$key.'_month')" />
                    <x-input-error class="mt-1" :messages="$errors->get('season_'.$key.'_day')" />
                </div>
            @endforeach
        </div>
    </div>
    {{-- B2B Anfragen visibility override: 'auto' follows the season window,
         'always' features the product there even out of season/stock
         (sourceable gap-fillers), 'never' hides it from the request list. --}}
    <div class="mt-4 max-w-md">
        <label for="request_visibility" class="block text-sm font-medium text-gray-700">{{ __('products.request_visibility_label') }}</label>
        <select id="request_visibility" name="request_visibility" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            @foreach (['auto', 'always', 'never'] as $opt)
                <option value="{{ $opt }}" @selected(old('request_visibility', $product?->request_visibility ?? 'auto') === $opt)>{{ __('products.request_visibility_'.$opt) }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">{{ __('products.request_visibility_hint') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('request_visibility')" />
    </div>
            </div>

            {{-- System flags: not customer-facing content. --}}
            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('admin.product_form.section_system') }}</p>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:gap-8">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_egg_addon" value="1" class="rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-600" @checked(old('is_egg_addon', $product?->is_egg_addon))>
                        <span class="text-sm text-gray-700">{{ __('admin.products.is_egg_addon_label') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="is_test" value="1" class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-600" @checked(old('is_test', $product?->is_test))>
                        <span class="text-sm text-gray-700">{{ __('Test data') }}</span>
                    </label>
                </div>
                <p class="mt-1 text-xs text-amber-700">{{ __('admin.products.is_egg_addon_hint') }}</p>
                <x-input-error class="mt-2" :messages="$errors->get('is_egg_addon')" />
                <x-input-error class="mt-2" :messages="$errors->get('is_test')" />
            </div>
        </div>
    </div>

    <div class="rounded border border-gray-200 bg-white p-4 space-y-4">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.product_form.section_images') }}</h2>
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

    </div>

    <div class="sticky bottom-0 z-10 -mx-4 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="flex items-center gap-4">
            <x-primary-button x-bind:disabled="submitting" data-testid="product-form-submit">
                <span x-show="!submitting">{{ $isEdit ? __('Save') : __('Create product') }}</span>
                <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    {{ __('admin.product_form.saving') }}
                </span>
            </x-primary-button>
            <a href="{{ route('app.admin.products.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('Cancel') }}</a>
            @if ($errors->any())
                <span class="text-sm text-rose-600">{{ trans_choice('admin.product_form.error_summary', $errors->count(), ['count' => $errors->count()]) }}</span>
            @endif
        </div>
    </div>
</form>
