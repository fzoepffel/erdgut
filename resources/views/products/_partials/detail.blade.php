{{-- Shared product-detail markup. Used by both the auth-shell view
     (resources/views/products/show.blade.php) and the public-shell
     view (resources/views/products/show-public.blade.php). The
     wrapping layout is the only difference between the two. --}}
@php
    /** @var \App\Models\Product $product */
    /** @var string $listRouteName */
    /** @var string $detailRouteName */

    $catEnum = \App\Enums\ProductCategory::tryFrom($product->category);
    // D6/D7/F8 (UX-review notes): chapter-aligned category keys.
    $categoryPlaceholders = [
        'produce' => asset('images/mock/products/vegetables.jpg'),
        'eggs_and_dairy' => asset('images/mock/products/dairy.jpg'),
        'bread' => asset('images/mock/products/bakery.jpg'),
        'fish' => asset('images/mock/products/fish.jpg'),
        'meat' => asset('images/mock/products/meat.jpg'),
    ];
    $placeholder = $categoryPlaceholders[$product->category] ?? $categoryPlaceholders['produce'];
    $style = $catEnum ? \App\Support\ProductCategoryStyle::for($catEnum) : null;
    $chapter = $style['chapter'] ?? null;

    $galleryUrls = $product->images
        ->map(fn ($img) => $img->url())
        ->filter()
        ->values();
    if ($galleryUrls->isEmpty() && $product->imageUrl() !== null) {
        $galleryUrls = collect([$product->imageUrl()]);
    }
    if ($galleryUrls->isEmpty()) {
        $galleryUrls = collect([$placeholder]);
    }

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
    $dietaryLabels = [
        \App\Enums\DietaryTag::Vegan->value => __('Vegan'),
        \App\Enums\DietaryTag::Vegetarian->value => __('Vegetarian'),
        \App\Enums\DietaryTag::ContainsMeat->value => __('Contains meat'),
        \App\Enums\DietaryTag::ContainsFish->value => __('Contains fish'),
        \App\Enums\DietaryTag::ContainsDairy->value => __('Contains dairy'),
        \App\Enums\DietaryTag::ContainsEggs->value => __('Contains eggs'),
    ];
    $tasteLabels = [
        \App\Enums\TasteProfile::Sweet->value => __('Sweet'),
        \App\Enums\TasteProfile::Earthy->value => __('Earthy'),
        \App\Enums\TasteProfile::Fresh->value => __('Fresh'),
        \App\Enums\TasteProfile::Peppery->value => __('Peppery'),
        \App\Enums\TasteProfile::Bitter->value => __('Bitter'),
        \App\Enums\TasteProfile::Umami->value => __('Umami'),
        \App\Enums\TasteProfile::Crisp->value => __('Crisp'),
        \App\Enums\TasteProfile::Herbal->value => __('Herbal'),
        \App\Enums\TasteProfile::Nutty->value => __('Nutty'),
        \App\Enums\TasteProfile::Spicy->value => __('Spicy'),
        \App\Enums\TasteProfile::Sour->value => __('Sour'),
        \App\Enums\TasteProfile::Rich->value => __('Rich'),
    ];

    $dietaryTags = array_values(array_filter((array) $product->dietary_tags, fn ($v) => isset($dietaryLabels[$v])));
    $allergens = array_values(array_filter((array) $product->allergens, fn ($v) => isset($allergenLabels[$v])));
    // Keep every non-empty tag; predefined ones get a localized label, custom
    // free-form ones fall back to their raw value (see display below).
    $tasteTags = array_values(array_filter((array) $product->taste_profile_tags, fn ($v) => is_string($v) && $v !== ''));
    $tasteProfile = $product->localizedTasteProfile();
@endphp

<div class="py-10 sm:py-16">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-3">
            <a href="{{ route($listRouteName) }}" class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                <span aria-hidden="true">&larr;</span> {{ __('products.back_to_list') }}
            </a>
            @if ($catEnum)
                <x-chapter-badge :category="$catEnum" class="w-fit" />
            @endif
            <h1 class="font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                {{ $product->name }}
            </h1>
        </div>

        <div class="grid gap-10 lg:grid-cols-12 lg:gap-16">
            <x-reveal variant="scale" class="lg:col-span-7">
                <div
                    x-data="{ active: 0, images: @js($galleryUrls->values()->all()) }"
                    class="space-y-3"
                >
                    <div class="relative aspect-[4/5] overflow-hidden bg-brand-ink/5">
                        <template x-for="(src, i) in images" :key="i">
                            <img
                                :src="src"
                                alt="{{ $product->name }}"
                                class="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
                                :class="active === i ? 'opacity-100' : 'opacity-0'"
                                loading="lazy"
                            />
                        </template>
                        @if ($chapter)
                            <div class="absolute right-4 top-4 flex h-20 w-20 items-center justify-center bg-brand-cream/90 backdrop-blur-sm sm:h-24 sm:w-24">
                                <x-chapter-mark :chapter="$chapter" size="sm" class="opacity-90" />
                            </div>
                        @endif
                    </div>

                    <div x-show="images.length > 1" class="flex gap-2 overflow-x-auto">
                        <template x-for="(src, i) in images" :key="i">
                            <button
                                type="button"
                                x-on:click="active = i"
                                class="relative h-16 w-16 shrink-0 overflow-hidden bg-brand-ink/5 transition"
                                :class="active === i ? 'ring-2 ring-brand-ink' : 'ring-1 ring-brand-ink/15 hover:ring-brand-ink/40'"
                                :aria-current="active === i ? 'true' : 'false'"
                            >
                                <img :src="src" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </button>
                        </template>
                    </div>
                </div>
            </x-reveal>
            <x-reveal variant="fade-up" delay="150" class="lg:col-span-5">
                <div class="space-y-6 lg:pt-8">
                    <div>
                        <p class="eyebrow text-brand-ink/60">{{ __('products.producer_label') }}</p>
                        <p class="mt-2 font-display text-2xl italic text-brand-ink">{{ $product->producer?->name ?? __('products.producer_unknown') }}</p>
                    </div>

                    <div class="border-y border-brand-ink/15 py-6">
                        <p class="eyebrow text-brand-ink/60">{{ __('Price') }}</p>
                        <p class="mt-2 font-display text-4xl font-semibold text-brand-ink">
                            {{ $product->displayPrice() }}
                        </p>
                    </div>

                    @if ($dietaryTags !== [])
                        <div class="flex flex-wrap gap-2">
                            @foreach ($dietaryTags as $tag)
                                <span class="inline-flex items-center border border-brand-ink/25 bg-brand-ink/[0.03] px-3 py-1 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink">
                                    {{ $dietaryLabels[$tag] }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if ($tasteProfile !== '')
                        <div class="space-y-3">
                            <p class="eyebrow text-brand-ink/60">{{ __('Description') }}</p>
                            <p class="font-editorial text-base italic leading-relaxed text-brand-ink/85">{{ $tasteProfile }}</p>
                        </div>
                    @endif

                    @if ($tasteTags !== [])
                        <div class="space-y-3">
                            <p class="eyebrow text-brand-ink/60">{{ __('Taste profile tags') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($tasteTags as $tag)
                                    <span class="inline-flex items-center border border-brand-ink/20 px-3 py-1 text-xs italic text-brand-ink/80">
                                        {{ $tasteLabels[$tag] ?? $tag }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($allergens !== [])
                        <div class="space-y-2">
                            <p class="eyebrow text-brand-ink/60">{{ __('Allergens') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($allergens as $a)
                                    <span class="inline-flex items-center border border-amber-700/30 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">
                                        {{ $allergenLabels[$a] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($chapter && $style)
                        <div class="flex items-center gap-3 pt-2">
                            <x-chapter-mark :chapter="$chapter" size="xs" class="opacity-80" />
                            <p class="font-editorial text-xs italic text-brand-ink/60">
                                {{ __('From the :chapter chapter.', ['chapter' => __('admin.category.' . $product->category)]) }}
                            </p>
                        </div>
                    @endif
                </div>
            </x-reveal>
        </div>

        @if ($product->pairings->isNotEmpty())
            <section class="mt-16 space-y-6" aria-labelledby="pairings-heading">
                <div class="flex items-center gap-3">
                    <x-triple-star class="opacity-60" />
                    <h2 id="pairings-heading" class="eyebrow text-brand-ink/70">{{ __('Pair well with') }}</h2>
                </div>
                <ul class="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($product->pairings as $pair)
                        @php
                            $pairCatEnum = \App\Enums\ProductCategory::tryFrom($pair->category);
                            $pairPlaceholder = $categoryPlaceholders[$pair->category] ?? $categoryPlaceholders['produce'];
                            $pairImage = $pair->images->first()?->url() ?? $pair->imageUrl() ?? $pairPlaceholder;
                        @endphp
                        <li>
                            <a href="{{ route($detailRouteName, $pair) }}" class="group flex flex-col gap-3">
                                <div class="relative aspect-square w-full overflow-hidden bg-brand-ink/5">
                                    <img src="{{ $pairImage }}" alt="{{ $pair->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" />
                                </div>
                                <div>
                                    @if ($pairCatEnum)
                                        <x-chapter-badge :category="$pairCatEnum" />
                                    @endif
                                    <p class="mt-2 font-display text-base leading-snug text-brand-ink">{{ $pair->name }}</p>
                                    <p class="font-editorial text-xs italic text-brand-ink/60">{{ $pair->producer?->name ?? __('products.producer_unknown') }}</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mt-16 flex flex-col items-center gap-3">
            <x-triple-star class="opacity-70" />
            <a href="{{ route($listRouteName) }}" class="font-editorial text-sm italic text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">
                {{ __('Back to the catalogue') }}
            </a>
        </div>
    </div>
</div>
