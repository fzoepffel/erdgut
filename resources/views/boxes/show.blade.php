<x-app-layout>
    @php
        $weekLabelDate = $box->week_start_date->format('d.m.Y');
        // D6/D7/F8 (UX-review notes): chapter-aligned category keys.
        // Existing image filenames left in place — they're keyed by
        // category value rather than chapter, so adding new entries
        // for the merged taxonomy is the cleanest forward path.
        $categoryPlaceholders = [
            'produce' => asset('images/mock/products/vegetables.jpg'),
            'eggs_and_dairy' => asset('images/mock/products/dairy.jpg'),
            'bread' => asset('images/mock/products/bakery.jpg'),
            'fish' => asset('images/mock/products/fish.jpg'),
            'meat' => asset('images/mock/products/meat.jpg'),
        ];
        $allergenLabels = [
            \App\Enums\Allergen::Gluten->value => __('Gluten'),
            \App\Enums\Allergen::Crustaceans->value => __('Crustaceans'),
            \App\Enums\Allergen::Eggs->value => __('Eggs'),
            \App\Enums\Allergen::Fish->value => __('Fish'),
            \App\Enums\Allergen::Peanuts->value => __('Peanuts'),
            \App\Enums\Allergen::Soybeans->value => __('Soybeans'),
            \App\Enums\Allergen::Milk->value => __('Milk'),
            \App\Enums\Allergen::Nuts->value => __('Nuts'),
            \App\Enums\Allergen::Celery->value => __('Celery'),
            \App\Enums\Allergen::Mustard->value => __('Mustard'),
            \App\Enums\Allergen::Sesame->value => __('Sesame'),
            \App\Enums\Allergen::Sulphites->value => __('Sulphites'),
            \App\Enums\Allergen::Lupin->value => __('Lupin'),
            \App\Enums\Allergen::Molluscs->value => __('Molluscs'),
        ];
    @endphp

    @php
        $boxStory = \App\Support\EditorialContent::thisWeekLead($box);
        // Pick a producer that actually appears in this box; fall back to the
        // weekly rotation if no producer can be sourced from the box items.
        $boxProducerSlug = $box->items
            ->pluck('product.producer.name')
            ->filter()
            ->map(fn ($name) => strtolower(explode(' ', $name)[0] ?? ''))
            ->first(fn ($slug) => in_array($slug, \App\Support\EditorialContent::PRODUCER_SLUGS, true));
        $boxProducer = is_string($boxProducerSlug) && $boxProducerSlug !== ''
            ? \App\Support\EditorialContent::producer($boxProducerSlug)
            : \App\Support\EditorialContent::producerOfTheWeek();
    @endphp

    <div class="pb-10 sm:pb-16">
        <div class="mx-auto max-w-4xl space-y-10 px-4 pt-8 sm:px-6 sm:pt-10 lg:px-8">
            <x-customer.hero-band
                chapter="produce"
                :image="asset('images/mock/boxes/box-hero.jpg')"
                :eyebrow="__('Your weekly food box')"
                :title="__('Week of :date', ['date' => $weekLabelDate])"
                :lead="$boxStory"
            >
                <x-slot name="actions">
                    <span class="inline-flex bg-brand-cream/95 px-3 py-1.5">
                        <x-status-badge :value="$box->status" type="box" />
                    </span>
                </x-slot>
            </x-customer.hero-band>

            <section class="border border-brand-ink/15 p-6 sm:p-8" aria-labelledby="box-week-heading">
                <h2 id="box-week-heading" class="sr-only">{{ __('Box summary') }}</h2>
                <div class="space-y-3 text-sm">
                    @if ($modificationDeadline)
                        <p class="font-editorial italic text-brand-ink/75">
                            {{ __('Box finalized on :datetime', ['datetime' => $modificationDeadline->format('d.m.Y H:i')]) }}
                        </p>
                    @endif

                    {{-- K14 (UX notes): customers who paid for the eggs add-on but
                         couldn't be supplied this week deserve to be told before
                         the next email arrives. eggs_addon_unfulfilled_at is set
                         by the box-save handler when the saved lines have no
                         is_egg_addon=true product; the EggsUnfulfilledHandler
                         already books the partial refund and queues an email.
                         Surfacing it on the box page closes the loop on the
                         customer side. --}}
                    @if ($box->eggs_addon_unfulfilled_at !== null)
                        <p
                            class="border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900"
                            data-testid="customer-box-eggs-unfulfilled-notice"
                        >
                            {{ __('No eggs in this box — we are refunding the EUR :amount add-on for this delivery.', ['amount' => number_format((float) config('box.eggs_dairy_add_on_eur', 4.5), 2, ',', '.')]) }}
                        </p>
                    @endif

                    <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 font-medium text-brand-ink">
                        {{ __('boxes.read_only_notice') }}
                    </p>
                </div>
            </section>

            <x-box.budget-panel
                :box="$box"
                :budget-details="$budgetDetails"
                :bind-estimate="false"
            />

            <section class="border border-brand-ink/15" aria-labelledby="box-items-heading">
                <div class="border-b border-brand-ink/15 px-6 py-5">
                    <p class="eyebrow text-chapter-produce">{{ __('Contents') }}</p>
                    <h2 id="box-items-heading" class="mt-2 font-display text-xl text-brand-ink">
                        {{ __('This week in the box') }}
                    </h2>
                    <p class="mt-2 font-editorial text-sm italic text-brand-ink/65">
                        {{ __('Arranged stall by stall — the way a good market unfolds.') }}
                    </p>
                </div>
                <div class="p-6 sm:p-8">
                    @if ($box->items->isEmpty())
                        <x-empty-state
                            :title="__('No items in this box yet.')"
                            :description="$box->next_update_expected_at
                                ? __('Next update expected by :date', ['date' => $box->next_update_expected_at->translatedFormat('d.m.Y H:i')])
                                : __('When the week is curated, items will appear here.')"
                            variant="inset"
                        />
                    @else
                        @php
                            $grouped = $box->items->groupBy(function ($item) {
                                $cat = $item->product?->category;
                                $enum = $cat ? \App\Enums\ProductCategory::tryFrom($cat) : null;
                                return $enum?->stall() ?? 'other';
                            });
                            // D6/D7/F8 (UX-review notes): the new stall
                            // keys come from `ProductCategory::stall()`
                            // — `bread` (was `bakery`), `eggdairy`
                            // (was `dairy`).
                            $stallOrder = ['produce', 'bread', 'eggdairy', 'fish', 'meat', 'other'];
                            $stallChapter = [
                                'produce' => 'produce',
                                'bread' => 'bread',
                                'eggdairy' => 'eggdairy',
                                'fish' => 'fish',
                                'meat' => 'meat',
                                'other' => null,
                            ];
                            $stallColor = [
                                'produce' => 'text-chapter-produce',
                                'bread' => 'text-chapter-bread',
                                'eggdairy' => 'text-chapter-eggdairy',
                                'fish' => 'text-chapter-fish',
                                'meat' => 'text-chapter-meat',
                                'other' => 'text-brand-ink/60',
                            ];
                            $stallLabel = [
                                'produce' => __('Greengrocer'),
                                'bread' => __('Bakery'),
                                'eggdairy' => __('Dairy'),
                                'fish' => __('Fishmonger'),
                                'meat' => __('Butcher'),
                                'other' => __('Other'),
                            ];
                        @endphp
                        @php
                            // ERD-352: numbering spans the whole box, not just the current stall —
                            // matches the prototype's "№ 01 … № 06" continuous sequence so item
                            // numbers stay unique across the manifest.
                            $itemNumber = 0;
                        @endphp
                        <div class="space-y-12">
                            @foreach ($stallOrder as $stall)
                                @if (isset($grouped[$stall]))
                                    <div>
                                        <div class="flex items-center gap-3">
                                            @if ($stallChapter[$stall])
                                                <x-chapter-mark :chapter="$stallChapter[$stall]" size="xs" class="shrink-0 opacity-90" />
                                            @endif
                                            <p class="eyebrow {{ $stallColor[$stall] }}">{{ $stallLabel[$stall] }}</p>
                                        </div>
                                        <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach ($grouped[$stall] as $item)
                                                @php
                                                    $itemNumber++;
                                                    $product = $item->product;
                                                    $itemCat = $product?->category;
                                                    $itemPlaceholder = $categoryPlaceholders[$itemCat] ?? $categoryPlaceholders['produce'];
                                                    $itemImage = $product?->images->first()?->url()
                                                        ?? $product?->imageUrl()
                                                        ?? $itemPlaceholder;
                                                    $itemAllergens = collect((array) ($product?->allergens ?? []))
                                                        ->filter(fn ($v) => isset($allergenLabels[$v]))
                                                        ->map(fn ($v) => $allergenLabels[$v])
                                                        ->values()
                                                        ->all();
                                                    // Use the BoxItem's own quantity + unit string so the
                                                    // historical display (e.g. "1.50 kg") is preserved verbatim,
                                                    // independent of the product's canonical-unit conversions.
                                                    $qtyLabel = trim(number_format((float) $item->quantity, 2).' '.($item->unit ?? ''));
                                                @endphp
                                                <x-customer.item-card
                                                    :number="$itemNumber"
                                                    :name="$product?->name ?? __('Unknown product')"
                                                    :quantity-label="$qtyLabel"
                                                    :producer-name="$product?->producer?->name"
                                                    :producer-region="$product?->producer?->region"
                                                    :image-url="$itemImage"
                                                    :description="$product?->localizedTasteProfile()"
                                                    :allergens="$itemAllergens"
                                                    :is-extra="(bool) $item->is_extra"
                                                />
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <x-customer.producer-spotlight :producer="$boxProducer" />

            <div class="flex flex-col items-center gap-3 pt-4">
                <x-triple-star class="opacity-70" />
                <p class="font-editorial text-sm italic text-brand-ink/60">
                    {{ __('Gathered this week, for your table.') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
