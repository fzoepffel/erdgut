{{-- Public product-catalogue index body, shared between the marketing-shell
     page (resources/views/products/public.blade.php) and the isolated shop
     catalogue (resources/views/shop/products.blade.php).

     Parameters (route names so the same markup links into either shell):
       $productsIndexRoute — index/category-filter route (default marketing)
       $productShowRoute   — product detail route       (default marketing)

     Required view data: $products (paginator), $activeCategory, $categories. --}}
@php
    use App\Enums\ProductCategory;
    use App\Support\ProductCategoryStyle;

    $productsIndexRoute = $productsIndexRoute ?? 'public.products.index';
    $productShowRoute = $productShowRoute ?? 'public.products.show';
    $isolated = $isolated ?? false;
    $producerShowRoute = $producerShowRoute ?? ($isolated ? 'public.shop.producers.show' : 'public.producers.show');
    $producerIndexRoute = $producerIndexRoute ?? ($isolated ? 'public.shop.producers.index' : 'public.producers');

    // Total catalogue count (for hero stat line — all visible products, unfiltered).
    $totalCount = \App\Models\Product::query()->visibleInCatalog()->count();

    // Per-category counts for the rail tiles.
    $categoryCounts = \App\Models\Product::query()
        ->visibleInCatalog()
        ->selectRaw('category, COUNT(*) as cnt')
        ->groupBy('category')
        ->pluck('cnt', 'category')
        ->map(fn ($v) => (int) $v)
        ->all();

    // Chapter-panel background classes (same palette as the homepage strip).
    $chapterPanelBg = [
        'produce'   => 'bg-chapter-produce',
        'meat'      => 'bg-chapter-meat',
        'fish'      => 'bg-chapter-fish',
        'eggdairy'  => 'bg-chapter-eggdairy',
        'bread'     => 'bg-chapter-bread',
    ];

    // Status strip background per chapter (used in product cards).
    $chapterStripBg = [
        'produce'   => 'bg-chapter-produce',
        'meat'      => 'bg-chapter-meat',
        'fish'      => 'bg-chapter-fish',
        'eggdairy'  => 'bg-chapter-eggdairy',
        'bread'     => 'bg-chapter-bread',
    ];

    // Category enum → display label.
    $categoryLabel = [
        'produce'   => __('Fruit & Vegetables'),
        'meat'      => __('Meat'),
        'fish'      => __('Fish & Seafood'),
        'eggdairy'  => __('Eggs & Dairy'),
        'bread'     => __('Bread'),
    ];

    // Chapter card subtitles.
    $chapterSubtitle = [
        'produce'  => __('products.chapter_subtitle_produce'),
        'meat'     => __('products.chapter_subtitle_meat'),
        'fish'     => __('products.chapter_subtitle_fish'),
        'eggdairy' => __('products.chapter_subtitle_eggdairy'),
        'bread'    => __('products.chapter_subtitle_bread'),
    ];

    // Catalogue-strip illustration images for the left spine (same assets as homepage).
    $chapterImage = [
        'produce'  => 'catalog-strip-cabbage.webp',
        'meat'     => 'catalog-strip-waguy.webp',
        'fish'     => 'catalog-strip-sardines.webp',
        'eggdairy' => 'catalog-strip-eggs.webp',
        'bread'    => 'catalog-strip-wheat.webp',
    ];

    // Active category chapter key.
    $activeChapter = $activeCategory
        ? (ProductCategoryStyle::for($activeCategory)['chapter'] ?? null)
        : null;

    // Category placeholder images (used as fallback in product cards).
    $categoryPlaceholders = [
        'produce'       => asset('images/mock/products/vegetables.jpg'),
        'eggs_and_dairy'=> asset('images/mock/products/dairy.jpg'),
        'bread'         => asset('images/mock/products/bakery.jpg'),
        'fish'          => asset('images/mock/products/fish.jpg'),
        'meat'          => asset('images/mock/products/meat.jpg'),
    ];

    // Current month stamp for hero caption.
    $monthStamp = mb_strtoupper(now()->locale(app()->getLocale())->translatedFormat('M')) . ' · ' . now()->format('Y');
@endphp

{{-- § 1 — Hero --}}
<section class="relative border-b border-brand-ink/10 py-16 lg:py-24">
    <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

        {{-- Mono stamp top-right (lg+) --}}
        <x-reveal variant="fade-up" delay="0">
            <p class="absolute right-12 top-8 hidden font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce lg:block">
                {{ $monthStamp }} · {{ __('Catalogue') }}
            </p>
        </x-reveal>

        <div class="grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-16">

            {{-- Left: eyebrow + H1 --}}
            <div>
                <x-reveal variant="fade-up" delay="50">
                    <p class="eyebrow text-chapter-produce">{{ __('The catalogue') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="150">
                    <h1 class="mt-4 font-display text-5xl leading-[1.02] tracking-tight text-brand-ink md:text-6xl lg:text-7xl">
                        {{ __('Five stalls,') }}<br>
                        <em class="font-normal italic text-chapter-produce">{{ __('products.hero_heading_suffix') }}</em>
                    </h1>
                </x-reveal>
            </div>

            {{-- Right: lead + stats --}}
            <div class="flex flex-col justify-end">
                <x-reveal variant="fade-up" delay="250">
                    <p class="max-w-md text-base leading-relaxed text-brand-ink/60">
                        {{ $isolated ? __('shop.products_index_intro') : __('products.index_intro') }}
                    </p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="350">
                    <p class="mt-8 font-mono text-xs uppercase tracking-widest text-chapter-produce">
                        {{ $totalCount }} {{ __('entries') }} · {{ __('products.hero_stat_farms') }}
                    </p>
                </x-reveal>
            </div>

        </div>
    </div>
</section>

{{-- § 2 — Category Rail --}}
<section class="border-b border-brand-ink/10 bg-brand-paper py-12 lg:py-16">
    <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

        {{-- Header: eyebrow + "All chapters" button + active label --}}
        <x-reveal variant="fade-up">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="eyebrow text-chapter-produce">{{ __('Stalls') }}</p>
            <div class="flex items-center gap-5">
                <a href="{{ route($productsIndexRoute) }}"
                   class="inline-flex items-center px-4 py-2 font-mono text-[0.6rem] uppercase tracking-[0.2em] transition
                          @if(! $activeCategory) bg-brand-ink text-brand-cream @else border border-brand-ink/20 text-brand-ink/60 hover:border-brand-ink/50 hover:text-brand-ink @endif"
                   aria-current="{{ ! $activeCategory ? 'true' : 'false' }}"
                   data-testid="products-chapter-tile-all">
                    {{ __('All chapters') }}
                </a>
                <span class="font-mono text-[0.6rem] uppercase tracking-[0.2em] text-brand-ink/50">
                    @if ($activeCategory)
                        {{ $categoryLabel[$activeChapter] ?? $activeCategory->value }}
                    @else
                        {{ __('Showing every stall') }}
                    @endif
                </span>
            </div>
        </div>
        </x-reveal>

        {{-- Chapter cards — portrait book-cover style --}}
        <div class="mt-8 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($categories as $cat)
                @php
                    $catStyle  = ProductCategoryStyle::for($cat);
                    $chapter   = $catStyle['chapter'];
                    $isActive  = $activeCategory && $activeCategory->value === $cat->value;
                    $catCount  = $categoryCounts[$cat->value] ?? 0;
                    $bgClass   = $chapterPanelBg[$chapter] ?? 'bg-chapter-produce';
                    $label     = $categoryLabel[$chapter] ?? __('admin.category.' . $cat->value);
                    $subtitle  = $chapterSubtitle[$chapter] ?? '';
                    $img       = $chapterImage[$chapter] ?? null;
                    $idx       = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
                @endphp
                <x-reveal variant="fade-up" delay="{{ $loop->index * 80 }}">
                <a href="{{ route($productsIndexRoute, ['category' => $cat->value]) }}"
                   class="group relative flex aspect-[3/4] flex-col overflow-hidden {{ $bgClass }} text-brand-cream transition duration-150 hover:-translate-y-1
                          @if($isActive) ring-2 ring-offset-2 ring-brand-ink @endif"
                   aria-current="{{ $isActive ? 'true' : 'false' }}"
                   data-testid="products-chapter-tile-{{ $chapter }}">

                    @if ($img)
                        <div class="pointer-events-none absolute inset-y-0 left-0 overflow-hidden" style="width: 22%">
                            <img
                                src="{{ asset('images/chapters/' . $img) }}"
                                alt=""
                                aria-hidden="true"
                                class="h-full w-full select-none object-cover object-center"
                                style="filter: brightness(0) invert(1); opacity: 0.9; transform: scale(1.1);"
                            />
                        </div>
                    @endif

                    {{-- Top bar: chapter index + "· Chapter" label --}}
                    <div class="relative flex items-center justify-end gap-3 px-5 pt-5">
                        <span class="font-mono text-[0.7rem] tabular-nums tracking-widest text-brand-cream/90">{{ $idx }}</span>
                        <span class="font-mono text-[0.6rem] uppercase tracking-[0.25em] text-brand-cream/90">· {{ __('Chapter') }}</span>
                    </div>

                    {{-- Title block pinned to bottom --}}
                    <div class="relative mt-auto flex flex-col" style="padding-left: 32%; padding-right: 1.5rem; padding-bottom: 1.5rem;">
                        <h3 class="font-display text-xl leading-[1.05] text-brand-cream lg:text-2xl">{{ $label }}</h3>
                        <p class="mt-1 font-display text-[0.8rem] italic text-brand-cream/85 lg:text-sm">{{ $subtitle }}</p>
                        <div class="mt-3 h-px w-full bg-brand-cream/30"></div>
                        <div class="mt-3 flex items-baseline justify-between gap-2">
                            <span class="font-mono text-[0.6rem] uppercase tracking-[0.25em] text-brand-cream/90 transition duration-150 group-hover:translate-x-1">
                                @if ($catCount > 0)
                                    {{ __('Open chapter') }} →
                                @else
                                    {{ __('Coming soon') }}
                                @endif
                            </span>
                            <span class="font-mono text-[0.6rem] tabular-nums text-brand-cream/70">
                                {{ str_pad((string) $catCount, 2, '0', STR_PAD_LEFT) }} {{ __('entries') }}
                            </span>
                        </div>
                    </div>
                </a>
                </x-reveal>
            @endforeach
        </div>

    </div>
</section>

{{-- § 3 — Catalog OR Empty Category --}}
@if ($products->isEmpty() && $activeCategory)

    {{-- Editorial empty state for a selected stall with no products --}}
    <section class="border-b border-brand-ink/10 py-24 lg:py-32">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-12 lg:grid-cols-[1fr_1.2fr] lg:gap-20 lg:items-center">

                {{-- Illustration tile --}}
                <div class="mx-auto grid aspect-square max-w-sm w-full place-items-center bg-brand-paper">
                    @if ($activeChapter)
                        <x-chapter-mark :chapter="$activeChapter" size="xl" class="opacity-80" />
                    @endif
                </div>

                {{-- Editorial copy --}}
                <div>
                    <p class="eyebrow text-chapter-produce">{{ $categoryLabel[$activeChapter] ?? $activeCategory->value }}</p>
                    <h2 class="mt-4 font-display text-4xl leading-tight text-brand-ink md:text-5xl">
                        {{ __('This stall opens later in the season.') }}
                    </h2>
                    <p class="mt-6 max-w-md text-base leading-relaxed text-brand-ink/60">
                        {{ __('products.empty_stall_lead') }}
                    </p>
                    <a href="{{ route($productsIndexRoute) }}"
                       class="mt-8 inline-flex items-center gap-2 border-b border-chapter-produce pb-0.5 text-sm text-chapter-produce hover:text-chapter-produce/80">
                        {{ __('Show all products') }} <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>

            </div>
        </div>
    </section>

@elseif (! $products->isEmpty())

    {{-- Catalog grid --}}
    <section class="border-b border-brand-ink/10 py-20 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            {{-- Section header --}}
            <x-reveal variant="fade-up">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('The manifest') }}</p>
                    <h2 class="mt-2 font-display text-4xl text-brand-ink md:text-5xl">
                        {{-- TODO: split by season so users can browse past/upcoming seasons --}}
                        {{ __('products.manifest_heading') }}
                    </h2>
                </div>
                <p class="max-w-sm text-sm leading-relaxed text-brand-ink/60">
                    {{ $isolated ? __('shop.products_manifest_note') : __('products.manifest_pricing_note') }}
                </p>
            </div>
            </x-reveal>

            {{-- Product grid --}}
            <ul class="mt-14 grid grid-cols-1 gap-x-10 gap-y-14 md:grid-cols-2 lg:grid-cols-3" data-testid="products-grid">
                @foreach ($products as $i => $product)
                    @php
                        $catEnum     = ProductCategory::tryFrom($product->category);
                        $catStyle    = $catEnum ? ProductCategoryStyle::for($catEnum) : null;
                        $chapter     = $catStyle['chapter'] ?? 'produce';
                        $stripBg     = $chapterStripBg[$chapter] ?? 'bg-chapter-produce';
                        $placeholder = $categoryPlaceholders[$product->category] ?? $categoryPlaceholders['produce'];
                        $cardNumber  = ($products->firstItem() ?? 1) + $i;
                        $catLabelStr = $categoryLabel[$chapter] ?? __('admin.category.' . ($product->category ?? ''));
                        $statusLabel = $product->is_seasonal ? __('In season') : __('Available');
                        $note        = $product->cardDescription();
                    @endphp
                    <x-reveal as="li" variant="fade-up" style="--i:{{ $i % 6 }}" class="list-none">
                        <article class="group flex flex-col">
                            <a href="{{ route($productShowRoute, $product) }}" class="flex flex-col">
                                {{-- Image well --}}
                                <div class="relative aspect-square overflow-hidden bg-brand-paper">
                                    <img
                                        src="{{ $product->thumbUrl() ?? $placeholder }}"
                                        alt="{{ $product->name }}"
                                        width="600"
                                        height="600"
                                        class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                                        loading="lazy"
                                    />
                                    {{-- № badge top-left --}}
                                    <span class="absolute left-3 top-3 bg-chapter-produce px-2 py-1 font-mono text-[0.65rem] uppercase tracking-widest text-brand-cream">
                                        № {{ str_pad((string) $cardNumber, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    {{-- Category chip top-right --}}
                                    <span class="absolute right-3 top-3 grid h-12 w-12 place-items-center bg-brand-cream/90">
                                        <x-chapter-mark :chapter="$chapter" size="xs" class="opacity-90" />
                                    </span>
                                    {{-- Status strip bottom --}}
                                    <div class="absolute bottom-0 left-0 right-0 {{ $stripBg }} px-3 py-2">
                                        <span class="font-mono text-[0.6rem] uppercase tracking-widest text-brand-cream">
                                            {{ $catLabelStr }} · {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Title row --}}
                                <div class="mt-5 flex items-baseline justify-between gap-4">
                                    <h3 class="font-display text-2xl text-brand-ink">{{ $product->name }}</h3>
                                    <span class="shrink-0 font-mono text-xs tabular-nums text-brand-ink/60">{{ $product->displayPrice() }}</span>
                                </div>
                            </a>

                            {{-- Hairline --}}
                            <div class="mt-2 h-px bg-brand-ink/10"></div>

                            {{-- Producer --}}
                            <p class="mt-3 font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce">
                                <x-producer-links
                                    :producers="\App\Support\ProducerLabels::forProduct($product)"
                                    :show-route="$producerShowRoute"
                                    :index-route="$producerIndexRoute"
                                    :fallback="__('products.producer_unknown')"
                                />
                            </p>

                            <a href="{{ route($productShowRoute, $product) }}" class="flex flex-col">
                                {{-- Unit descriptor: weight/volume per piece or bunch --}}
                                @if ($product->unit_weight_grams || $product->unit_volume_ml)
                                    <p class="mt-1 font-mono text-[0.65rem] text-brand-ink/50">
                                        {{ __('ca.') }}
                                        {{ $product->displayUnitWeight() ?? $product->displayUnitVolume() }}
                                        {{ __('per') }} {{ $product->localizedUnit() }}
                                    </p>
                                @endif

                                {{-- Short description (falls back to the long one for
                                     products migrated before short descriptions existed.
                                     Clamped to 3 lines so a long fallback never overflows. --}}
                                @if ($note)
                                    <p class="mt-2 text-sm leading-relaxed text-brand-ink/60 line-clamp-3">{{ $note }}</p>
                                @endif

                                {{-- CTA --}}
                                <span class="mt-5 inline-flex items-center gap-2 self-start border-b border-chapter-produce pb-0.5 text-xs text-chapter-produce">
                                    {{ __('Read the entry') }} <span aria-hidden="true">&rarr;</span>
                                </span>
                            </a>
                        </article>
                    </x-reveal>
                @endforeach
            </ul>

            {{-- Pagination --}}
            @if ($products->hasPages())
                <div class="mt-16">{{ $products->links() }}</div>
            @endif

        </div>
    </section>

@else

    {{-- Completely empty catalogue --}}
    <section class="border-b border-brand-ink/10 py-20 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <x-empty-state
                :title="__('products.empty')"
                :description="__('Check back soon — new products are added each week.')"
                variant="card"
            />
        </div>
    </section>

@endif

{{-- § 4 — Almanac closer --}}
<section class="bg-brand-paper py-16">
    <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div class="flex flex-col items-center gap-4 text-center">
            <x-triple-star class="opacity-70" />
            <p class="font-editorial text-lg italic text-brand-ink/60">
                {{ __('Fresh entries every week, in the rhythm of the harvest.') }}
            </p>
        </div>
    </div>
</section>
