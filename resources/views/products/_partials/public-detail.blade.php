{{-- Shared public product-detail body. Rendered inside two different
     isolated chromes:
       - marketing shell (products/show-public.blade.php) — links to the
         public catalogue + producer pages;
       - preview-shop shell (shop/product.blade.php) — links stay inside
         the hidden /shop surface.
     The wrapping layout and the internal link route names are the only
     differences, passed in as params.

     Required params:
       $product            — App\Models\Product
       $indexUrl           — href for the breadcrumb middle crumb
       $indexLabel         — label for that crumb (e.g. "Products" / "Shop")
       $productRouteName   — route name for pairing links
       $producerRouteName  — route name for the producer link --}}
@php
    /** @var \App\Models\Product $product */

    // Headline: first word italicised in moss, remainder in regular weight
    // (product names read as descriptive phrases, e.g. "Organic green asparagus").
    $name = $product->name;
    $firstSpace = mb_strpos($name, ' ');
    $firstWord = $firstSpace === false ? $name : mb_substr($name, 0, $firstSpace);
    $restWords = $firstSpace === false ? '' : mb_substr($name, $firstSpace + 1);

    $origin = $product->localizedOrigin();
    $variety = $product->localizedVariety();
    $tasteProfile = $product->localizedTasteProfile();
    $producer = $product->producer;

    $catEnum = \App\Enums\ProductCategory::tryFrom($product->category);
    $categoryLabel = $catEnum ? __('admin.category.'.$product->category) : '';

    // Category placeholder images, matching the public catalogue fallbacks.
    $categoryPlaceholders = [
        'produce' => asset('images/mock/products/vegetables.jpg'),
        'eggs_and_dairy' => asset('images/mock/products/dairy.jpg'),
        'bread' => asset('images/mock/products/bakery.jpg'),
        'fish' => asset('images/mock/products/fish.jpg'),
        'meat' => asset('images/mock/products/meat.jpg'),
    ];
    $placeholder = $categoryPlaceholders[$product->category] ?? $categoryPlaceholders['produce'];
    $heroImage = $product->imageUrl() ?? $placeholder;

    // Gallery = additional images only; section hidden when empty.
    $galleryUrls = $product->images
        ->map(fn ($img) => $img->url())
        ->filter()
        ->values();

    // Tag dictionaries (value → localised label).
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
    $dietaryLabels = [
        \App\Enums\DietaryTag::Vegan->value => __('Vegan'),
        \App\Enums\DietaryTag::Vegetarian->value => __('Vegetarian'),
        \App\Enums\DietaryTag::ContainsMeat->value => __('Contains meat'),
        \App\Enums\DietaryTag::ContainsFish->value => __('Contains fish'),
        \App\Enums\DietaryTag::ContainsDairy->value => __('Contains dairy'),
        \App\Enums\DietaryTag::ContainsEggs->value => __('Contains eggs'),
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

    // Display labels: predefined enum tags resolved to the current locale,
    // then the trilingual custom tags resolved to the same locale.
    $tasteTags = array_map(
        fn ($v) => $tasteLabels[$v],
        array_values(array_filter((array) $product->taste_profile_tags, fn ($v) => isset($tasteLabels[$v])))
    );
    $tasteTags = array_merge($tasteTags, $product->localizedCustomTasteTags());
    $dietaryTags = array_values(array_filter((array) $product->dietary_tags, fn ($v) => isset($dietaryLabels[$v])));
    $allergens = array_values(array_filter((array) $product->allergens, fn ($v) => isset($allergenLabels[$v])));
@endphp

{{-- Breadcrumb: mono uppercase, hairline top & bottom --}}
<nav aria-label="{{ __('Breadcrumb') }}" class="border-y border-brand-ink/15">
    <div class="mx-auto max-w-[1400px] px-6 py-4 lg:px-12">
        <ol class="flex flex-wrap items-center gap-2 text-xs uppercase tracking-eyebrow text-brand-ink/55">
            <li>{{ config('app.name', 'Erdgut') }}</li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ $indexUrl }}" class="underline-offset-4 hover:text-brand-ink hover:underline">{{ $indexLabel }}</a></li>
            <li aria-hidden="true">/</li>
            <li class="text-brand-ink/80">{{ $name }}</li>
        </ol>
    </div>
</nav>

{{-- Hero --}}
<section class="mx-auto max-w-[1400px] px-6 py-16 lg:px-12 lg:py-20" aria-labelledby="product-hero-heading">
    <div class="grid items-start gap-12 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
        {{-- Left: copy --}}
        <div>
            <p class="eyebrow text-brand-moss">{{ __('product.detail.eyebrow') }}</p>
            <h1 id="product-hero-heading" class="mt-6 font-display text-5xl font-semibold leading-[1.04] tracking-tight text-brand-ink md:text-7xl lg:text-[5.25rem]">
                <span class="italic font-normal text-brand-moss">{{ $firstWord }}</span>@if ($restWords !== '') {{ $restWords }}@endif
            </h1>

            {{-- Meta-line: origin · producer · variety --}}
            <p class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs uppercase tracking-eyebrow text-brand-ink/55">
                @php $metaShown = false; @endphp
                @if ($origin !== '')
                    <span>{{ $origin }}</span>
                    @php $metaShown = true; @endphp
                @endif
                @if ($producer)
                    @if ($metaShown)<span aria-hidden="true">·</span>@endif
                    <a href="{{ route($producerRouteName, $producer) }}" class="underline-offset-4 hover:text-brand-ink hover:underline">{{ $producer->name }}</a>
                    @php $metaShown = true; @endphp
                @endif
                @if ($variety !== '')
                    @if ($metaShown)<span aria-hidden="true">·</span>@endif
                    <span>{{ $variety }}</span>
                @endif
            </p>

            @if ($tasteProfile !== '')
                <p class="mt-8 max-w-xl font-editorial text-base leading-relaxed text-brand-ink/70">{{ $tasteProfile }}</p>
            @endif

            {{-- Detail bar: category only, never price/status --}}
            <div class="mt-8 border-t border-brand-ink/15 pt-8">
                <p class="eyebrow text-brand-ink/55">{{ __('Category') }}</p>
                <p class="mt-2 text-base text-brand-ink">{{ $categoryLabel !== '' ? $categoryLabel : __('products.producer_unknown') }}</p>
            </div>
        </div>

        {{-- Right: hero image with floating caption --}}
        <figure class="relative">
            <div class="relative aspect-[4/5] overflow-hidden bg-brand-ink/5">
                <img src="{{ $heroImage }}" alt="{{ $name }}" class="h-full w-full object-cover" />
                <figcaption class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-4 bg-brand-cream/90 px-4 py-3 text-xs uppercase tracking-eyebrow text-brand-ink/80 backdrop-blur">
                    <span class="truncate">{{ $name }}</span>
                    @if ($origin !== '')
                        <span class="shrink-0 text-brand-ink/55">{{ $origin }}</span>
                    @endif
                </figcaption>
            </div>
        </figure>
    </div>
</section>

{{-- Details: tag blocks + optional gallery, on paper --}}
<section class="border-y border-brand-ink/15 bg-brand-paper" aria-labelledby="product-details-heading">
    <h2 id="product-details-heading" class="sr-only">{{ __('Details') }}</h2>
    <div class="mx-auto max-w-[1400px] px-6 py-16 lg:px-12 lg:py-20">
        {{-- Tag blocks --}}
        <div class="grid gap-10 lg:grid-cols-3 lg:gap-16">
            <div>
                <p class="eyebrow text-brand-moss">{{ __('product.detail.taste_label') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse ($tasteTags as $tag)
                        <span class="inline-flex items-center border border-brand-ink/25 px-3 py-1.5 text-[0.65rem] uppercase tracking-eyebrow text-brand-ink">{{ $tag }}</span>
                    @empty
                        <span class="text-sm text-brand-ink/55">{{ __('product.detail.allergens_none') }}</span>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="eyebrow text-brand-moss">{{ __('product.detail.dietary_label') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse ($dietaryTags as $tag)
                        <span class="inline-flex items-center border border-brand-ink/25 px-3 py-1.5 text-[0.65rem] uppercase tracking-eyebrow text-brand-ink">{{ $dietaryLabels[$tag] }}</span>
                    @empty
                        <span class="text-sm text-brand-ink/55">{{ __('product.detail.allergens_none') }}</span>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="eyebrow text-brand-moss">{{ __('Allergens') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse ($allergens as $a)
                        <span class="inline-flex items-center border border-brand-ink/25 px-3 py-1.5 text-[0.65rem] uppercase tracking-eyebrow text-brand-ink">{{ $allergenLabels[$a] }}</span>
                    @empty
                        <span class="text-sm text-brand-ink/55">{{ __('product.detail.allergens_none') }}</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Gallery: only when additional images exist --}}
        @if ($galleryUrls->isNotEmpty())
            <div class="mt-16">
                <p class="eyebrow text-brand-moss">{{ __('Gallery') }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="product-gallery">
                    @foreach ($galleryUrls as $i => $url)
                        <div class="aspect-[4/3] overflow-hidden bg-brand-ink/5">
                            <img src="{{ $url }}" alt="{{ $name }} — {{ $i + 1 }}" class="h-full w-full object-cover" loading="lazy" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>

{{-- Pairings: hidden when empty --}}
@if ($product->pairings->isNotEmpty())
    <section class="mx-auto max-w-[1400px] px-6 py-16 lg:px-12 lg:py-20" aria-labelledby="product-pairings-heading">
        <div class="grid gap-12 lg:grid-cols-[1fr_1.2fr] lg:gap-16">
            <div>
                <p class="eyebrow text-brand-moss">{{ __('product.detail.pairings_eyebrow') }}</p>
                <h2 id="product-pairings-heading" class="mt-6 font-display text-3xl font-semibold leading-tight text-brand-ink sm:text-4xl">
                    {{ __('product.detail.pairings_heading') }}
                </h2>
                <p class="mt-6 max-w-md font-editorial text-base leading-relaxed text-brand-ink/70">
                    {{ __('product.detail.pairings_sub') }}
                </p>
            </div>
            <ul class="divide-y divide-brand-ink/15 border-y border-brand-ink/15" data-testid="product-pairings">
                @foreach ($product->pairings as $index => $pair)
                    <li class="flex items-baseline gap-6 py-5">
                        <span class="font-mono text-xs text-brand-ink/45 tabular-nums">{{ sprintf('%02d', $index + 1) }}</span>
                        <a href="{{ route($productRouteName, $pair) }}" class="font-display text-2xl leading-snug text-brand-ink underline-offset-4 hover:underline md:text-3xl">{{ $pair->name }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
