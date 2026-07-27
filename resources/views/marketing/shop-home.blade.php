@extends('layouts.shop-marketing')

@push('head')
@php
    $postalCity = config('shop.company_postal_city', '10829 Berlin');
    [$postalCode, $addressLocality] = array_pad(explode(' ', $postalCity, 2), 2, '');
@endphp
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "GroceryStore",
    "name": "Erdgut",
    "description": "Hofladen online: Gemüse, Obst, Eier, Brot und mehr von kleinen Höfen aus Berlin und Brandenburg. Saisonal geerntet, berlinweit geliefert.",
    "url": "{{ config('app.url') }}",
    "logo": "{{ asset('images/logo/erdgut-favicon-512.png') }}",
    "image": "{{ asset('images/og/erdgut-og-default.png') }}",
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "{{ config('shop.company_street', 'Gotenstraße 19') }}",
        "postalCode": "{{ $postalCode }}",
        "addressLocality": "{{ $addressLocality }}",
        "addressCountry": "DE"
    },
    "areaServed": {
        "@type": "City",
        "name": "Berlin"
    },
    "currenciesAccepted": "EUR",
    "priceRange": "€€",
    "hasOfferCatalog": {
        "@type": "OfferCatalog",
        "name": "Saisonales Sortiment",
        "itemListElement": [
            { "@type": "Offer", "itemOffered": { "@type": "Product", "name": "Gemüse" } },
            { "@type": "Offer", "itemOffered": { "@type": "Product", "name": "Obst" } },
            { "@type": "Offer", "itemOffered": { "@type": "Product", "name": "Eier und Milchprodukte" } },
            { "@type": "Offer", "itemOffered": { "@type": "Product", "name": "Brot und Backwaren" } },
            { "@type": "Offer", "itemOffered": { "@type": "Product", "name": "Fisch und Meeresfrüchte" } }
        ]
    }
}
</script>
@endpush

@section('content')
    {{-- Hero: video + editorial headline, sits below the olive-green masthead. --}}
    <section class="relative overflow-hidden" aria-labelledby="shop-home-hero-heading">
        <div class="relative h-[82vh] min-h-[620px] w-full overflow-hidden bg-brand-ink/80">
            {{-- The still image is a real <img> under the video: it paints
                 immediately while the video streams in and stays as the hero
                 when autoplay is blocked (e.g. iOS battery-saving mode),
                 without the native play overlay a video poster would get. --}}
            <img
                src="{{ asset('images/mock/hero/shop-hero-poster.jpg') }}"
                alt=""
                aria-hidden="true"
                fetchpriority="high"
                class="absolute inset-0 h-full w-full object-cover opacity-90"
                data-parallax
                data-testid="shop-home-hero-still"
            >
            {{-- No <source> in the markup: hero-video.js attaches it on
                 viewports from 640px up, so phones only load the still image.
                 When autoplay is refused it hides the video entirely. --}}
            <video
                class="absolute inset-0 h-full w-full object-cover opacity-90"
                autoplay
                muted
                loop
                playsinline
                preload="none"
                data-video-src="{{ asset('videos/mock/shop-hero.mp4') }}"
                data-video-type="video/mp4"
                data-parallax
            ></video>
            <div class="absolute inset-0 bg-gradient-to-b from-brand-ink/25 via-brand-ink/45 to-brand-ink/75"></div>

            {{-- Month stamp, catalogue style --}}
            <div class="absolute right-4 top-6 sm:right-8 sm:top-10 lg:right-12 lg:top-12">
                <x-month-label color="cream" :year="now()->format('Y')" />
            </div>

            <div class="relative mx-auto flex h-full max-w-7xl flex-col justify-end px-4 pb-16 sm:px-6 sm:pb-20 lg:px-8 lg:pb-24">
                <x-reveal variant="fade-up" delay="100">
                    <p class="eyebrow text-brand-cream/85">{{ __('Seasonal food for Berlin') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="200">
                    <h1 id="shop-home-hero-heading" class="mt-4 max-w-4xl font-display text-5xl leading-[1.05] tracking-tight text-brand-cream sm:text-6xl lg:text-7xl">
                        {{ __('Seasonal food from small farms, delivered across Berlin.') }}
                    </h1>
                </x-reveal>
                <x-reveal variant="fade-up" delay="300">
                    <p class="mt-8 max-w-2xl text-lg leading-relaxed text-brand-cream/90">
                        {{ __('Browse the shop and order exactly what you like — by producers we know by name, harvested in the rhythm of the seasons.') }}
                    </p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="400">
                    {{-- One clear primary action (the date-based ordering flow),
                         with the catalogue as a quiet secondary link so the two
                         don't read as the same button. --}}
                    <div class="mt-10 flex flex-col items-start gap-5 sm:flex-row sm:items-center sm:gap-8" data-testid="shop-home-hero-ctas">
                        <a
                            href="{{ route('public.shop.index') }}"
                            class="inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cream"
                            data-testid="shop-home-cta-shop"
                        >
                            {{ __('Start your order') }}
                        </a>
                        <a
                            href="{{ route('public.shop.products.index') }}"
                            class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream/90 underline-offset-4 transition hover:text-brand-cream hover:underline"
                            data-testid="shop-home-cta-products"
                        >
                            {{ __('Or browse the full catalogue') }}
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                        @auth
                            <a
                                href="{{ route('app.dashboard') }}"
                                class="inline-flex items-center justify-center text-sm font-semibold uppercase tracking-eyebrow text-brand-cream/90 hover:underline"
                                data-testid="shop-home-cta-dashboard"
                            >
                                {{ __('Go to dashboard') }}
                            </a>
                        @endauth
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Founder letter: short manifesto + "Read our story" link to About --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="shop-home-letter-heading">
        <div class="mx-auto max-w-3xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8" data-testid="shop-home-founder-letter">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-chapter-produce">{{ __('A letter from Lucille, Arthur and Milan.') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="100">
                <h2 id="shop-home-letter-heading" class="mt-6 font-display text-3xl leading-tight text-brand-moss sm:text-4xl">
                    {{ __('Why we started Erdgut.') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="200">
                <p class="drop-cap mt-8 font-editorial text-lg leading-relaxed text-brand-ink/85" style="--chapter-ink: theme('colors.chapter.produce.DEFAULT');">
                    {{ __('Food is never just food.') }} {{ __('Behind every vegetable, fruit, cheese, and cut of meat lies a landscape, a season, a craft, and the dedication of those who produce it.') }}
                </p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="280">
                <p class="mt-6 font-editorial text-lg leading-relaxed text-brand-ink/85">
                    {{ __('Yet today, we have become disconnected from where our food comes from. Small farms disappear, biodiversity declines, and true flavour is increasingly replaced by uniformity.') }}
                </p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="360">
                <p class="mt-6 font-editorial text-lg leading-relaxed text-brand-ink/85">
                    {{ __('Erdgut was created to preserve another path, reconnecting Berliners with the farmers and artisans who cultivate with care.') }}
                </p>
            </x-reveal>

            <x-triple-star class="mt-12" />
            <x-reveal variant="fade-up" delay="460">
                <div class="mt-10 flex flex-col items-center gap-6">
                    <x-founder-signature size="lg" />
                    <a href="{{ route('public.shop-about') }}" class="inline-flex items-center gap-2 border-b border-chapter-produce pb-0.5 font-mono text-xs uppercase tracking-widest text-chapter-produce transition hover:text-chapter-produce/80" data-testid="shop-home-read-story">
                        {{ __('Read our story') }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </x-reveal>

            {{-- The bigger picture: three figures behind the letter, set apart
                 from the signed note above. Each opens a different door — the
                 loss of farms → meet the producers; the loss of varieties →
                 the products; the squeeze on producers → the shop. --}}
            @php
                // Each column states a problem (the figure), then what Erdgut
                // does about it, then a door to act on it.
                $letterFigures = [
                    [
                        'value' => '10',
                        'caption' => __('farms disappear every day in Germany'),
                        'answer' => __('We buy directly from small farms and stay with them season after season.'),
                        'cta' => __('Get to know our producers'),
                        'href' => route('public.shop.producers.index'),
                    ],
                    [
                        'value' => __('~75%'),
                        'caption' => __('of crop varieties lost in the last century'),
                        'answer' => __('We seek out rare and heirloom varieties on purpose, to give them space to grow again.'),
                        'cta' => __('Browse the products'),
                        'href' => route('public.shop.products.index'),
                    ],
                    [
                        'value' => __('~25%'),
                        'caption' => __('of the food price reaches the producer'),
                        'answer' => __('With us, producers keep around 70% of the price on average.'),
                        'cta' => __('Visit the shop'),
                        'href' => route('public.shop.index'),
                    ],
                ];
            @endphp
            <dl class="mt-20 grid gap-x-8 gap-y-12 border-t border-brand-ink/10 pt-14 sm:grid-cols-3" data-testid="shop-home-figures">
                @foreach ($letterFigures as $i => $figure)
                    <x-reveal variant="fade-up" delay="{{ $i * 80 }}" class="flex flex-col text-center">
                        {{-- The problem --}}
                        <dt class="font-display text-4xl leading-none text-accent-star sm:text-5xl">{{ $figure['value'] }}</dt>
                        <dd class="mt-3 font-editorial text-sm leading-snug text-brand-ink/55">{{ $figure['caption'] }}</dd>
                        {{-- Erdgut's answer --}}
                        <p class="mt-5 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-produce">{{ __('Our answer') }}</p>
                        <p class="mt-2 font-editorial text-base leading-snug text-brand-moss">{{ $figure['answer'] }}</p>
                        <a
                            href="{{ $figure['href'] }}"
                            class="mt-auto self-center pt-5 font-mono text-xs uppercase leading-relaxed tracking-widest text-chapter-produce underline decoration-chapter-produce/40 underline-offset-4 transition hover:decoration-chapter-produce"
                        >
                            {{ $figure['cta'] }} <span aria-hidden="true">&rarr;</span>
                        </a>
                    </x-reveal>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- TODO: Re-enable producer pull quote when we have a real quote — restore
         attribution / speaker name in the blockquote when this goes live.
    <section class="bg-brand-ink text-brand-cream" aria-labelledby="shop-home-producer-quote-heading">
        <div class="mx-auto max-w-5xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-brand-cream/60">{{ __('A word from the producers') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="150">
                <blockquote class="mt-8 font-editorial text-3xl italic leading-snug text-brand-cream sm:text-4xl lg:text-5xl" id="shop-home-producer-quote-heading">
                    <span aria-hidden="true" class="text-accent-star/80">"</span>{{ __('We have grown vegetables here for three generations. With Erdgut, our crates finally reach people in Berlin who want to know who pulled the carrots that morning.') }}<span aria-hidden="true" class="text-accent-star/80">"</span>
                </blockquote>
            </x-reveal>
        </div>
    </section>
    --}}

    {{-- Producers invitation --}}
    <section class="bg-brand-moss text-brand-cream" aria-labelledby="shop-home-producers-heading">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12 lg:items-center lg:gap-16" data-testid="shop-home-producers-pitch">
                <x-reveal variant="fade-up" class="lg:col-span-7">
                    <p class="eyebrow text-brand-cream/70">{{ __('The producers') }}</p>
                    <h2 id="shop-home-producers-heading" class="mt-4 font-display text-3xl font-semibold leading-tight text-brand-cream sm:text-4xl lg:text-5xl">
                        {{ __('The people behind our food') }}
                    </h2>
                    <p class="mt-8 max-w-2xl text-lg leading-relaxed text-brand-cream/90">
                        {{ __('Behind every product lies a story of land, tradition, and craftsmanship. Discover the farmers and artisans who preserve local varieties, respect natural rhythms, and dedicate their lives to producing food with care.') }}
                    </p>
                    <p class="mt-8">
                        <a href="{{ route('public.shop.producers.index') }}" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream underline-offset-4 hover:underline" data-testid="shop-home-producers-link">
                            {{ __('Discover their stories') }}
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </p>
                </x-reveal>
                <x-reveal variant="scale" class="lg:col-span-5">
                    <div class="aspect-[4/5] overflow-hidden">
                        <img src="{{ asset('images/about/producers-circle.jpg') }}" alt="" class="h-full w-full object-cover" loading="lazy" decoding="async" data-testid="shop-home-producers-image">
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- In season now: real products available in the current two-week window,
         shown as editorial image+text rows. Hidden when nothing qualifies. --}}
    @if (! empty($inSeasonProducts) && count($inSeasonProducts) > 0)
        <section class="border-b border-brand-ink/10" aria-labelledby="shop-home-inseason-heading">
            <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
                <x-section-heading
                    :eyebrow="__('From this week\'s harvest')"
                    color="produce"
                    :title="__('In season now.')"
                    :lead="__('A few things on the shelves right now, picked from what our producers are harvesting this week.')"
                    id="shop-home-inseason-heading"
                />

                <div class="mt-16 flex flex-col gap-16 sm:gap-20" data-testid="shop-home-in-season">
                    @foreach ($inSeasonProducts as $product)
                        <x-reveal variant="fade-up" class="grid gap-8 lg:grid-cols-2 lg:items-center lg:gap-14" data-testid="shop-home-in-season-item">
                            <div @class(['aspect-[4/3] overflow-hidden', 'lg:order-2' => $loop->odd])>
                                {{-- thumbUrl() serves the ~600px card thumbnail and only
                                     falls back to the full-size original when none exists. --}}
                                <img
                                    src="{{ $product->thumbUrl() }}"
                                    alt="{{ $product->name }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </div>
                            <div @class(['lg:order-1' => $loop->odd])>
                                @php
                                    $featuredProducers = \App\Support\ProducerLabels::forProduct($product);
                                @endphp
                                @if ($featuredProducers->isNotEmpty())
                                    <p class="eyebrow text-chapter-produce">
                                        <x-producer-links
                                            :producers="$featuredProducers"
                                            class="text-inherit"
                                        />
                                    </p>
                                @endif
                                <h3 class="mt-3 font-display text-3xl leading-tight text-brand-ink sm:text-4xl">{{ $product->name }}</h3>
                                <p class="mt-3 font-mono text-sm text-brand-ink/60">{{ $product->displayPrice() }}</p>
                                <p class="mt-5 font-editorial text-lg leading-relaxed text-brand-ink/80">{{ $product->localizedTasteProfile() }}</p>
                                <a
                                    href="{{ route('public.shop.products.show', $product) }}"
                                    class="mt-7 inline-flex items-center gap-2 border-b border-chapter-produce pb-0.5 font-mono text-xs uppercase tracking-widest text-chapter-produce transition hover:text-chapter-produce/80"
                                >
                                    {{ __('See it in the shop') }}
                                    <span aria-hidden="true">&rarr;</span>
                                </a>
                            </div>
                        </x-reveal>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Five chapters: portrait book-cover grid, same design as products page --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper" aria-labelledby="shop-home-chapters-heading">
        <div class="mx-auto max-w-[1400px] px-6 py-20 sm:py-24 lg:px-12">
            <x-section-heading
                :eyebrow="__('What is in the shop')"
                color="producers"
                :title="__('Five chapters, one season.')"
                :lead="__('Five chapters of producers, one season of food.')"
                id="shop-home-chapters-heading"
            />

            @php
                use App\Support\ProductCategoryStyle;

                // Five product chapters only — Producers and Almanac removed.
                // `category` drives the coming-soon check against $availableCategories.
                $homeChapters = [
                    ['slug' => 'produce',  'category' => 'produce',       'label' => __('Fruit & Vegetables'), 'tagline' => __('Roots, greens, fruit'),   'href' => route('public.shop.products.index', ['category' => 'produce']),        'image' => 'catalog-strip-cabbage.webp'],
                    ['slug' => 'eggdairy', 'category' => 'eggs_and_dairy','label' => __('Eggs & Dairy'),      'tagline' => __('Milk, butter, cheese'),   'href' => route('public.shop.products.index', ['category' => 'eggs_and_dairy']), 'image' => 'catalog-strip-eggs.webp'],
                    ['slug' => 'meat',     'category' => 'meat',          'label' => __('Meat'),               'tagline' => __('Pasture-raised cuts'),    'href' => route('public.shop.products.index', ['category' => 'meat']),           'image' => 'catalog-strip-waguy.webp'],
                    ['slug' => 'fish',     'category' => 'fish',          'label' => __('Fish & Seafood'),     'tagline' => __('products.chapter_subtitle_fish'),   'href' => route('public.shop.products.index', ['category' => 'fish']),           'image' => 'catalog-strip-sardines.webp'],
                    ['slug' => 'bread',    'category' => 'bread',         'label' => __('Bread'),             'tagline' => __('products.chapter_subtitle_bread'),      'href' => route('public.shop.products.index', ['category' => 'bread']),          'image' => 'catalog-strip-wheat.webp'],
                ];
                $chapterPanelBg = [
                    'produce'  => 'bg-chapter-produce',
                    'meat'     => 'bg-chapter-meat',
                    'fish'     => 'bg-chapter-fish',
                    'eggdairy' => 'bg-chapter-eggdairy',
                    'bread'    => 'bg-chapter-bread',
                ];
            @endphp

            {{-- Portrait book-cover grid: 2 cols mobile → 3 sm → 5 lg,
                 identical to the products page chapter rail. --}}
            <div class="mt-16 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5" data-testid="shop-home-chapter-strip">
                @foreach ($homeChapters as $i => $chapter)
                    @php
                        $comingSoon = ! in_array($chapter['category'], $availableCategories ?? [], true);
                        $bgClass    = $chapterPanelBg[$chapter['slug']];
                        $idx        = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                    @endphp
                    <x-reveal variant="fade-up" delay="{{ $i * 80 }}">
                        {{-- <a> without href is inert when coming soon --}}
                        <a
                            @if (! $comingSoon) href="{{ $chapter['href'] }}" @else aria-disabled="true" @endif
                            @class(['group relative flex aspect-[3/4] flex-col overflow-hidden text-brand-cream', $bgClass, 'transition duration-150 hover:-translate-y-1' => ! $comingSoon, 'cursor-default' => $comingSoon])
                            data-testid="shop-home-chapter-tile-{{ $chapter['slug'] }}"
                        >
                            {{-- Left-spine catalogue illustration --}}
                            <div class="pointer-events-none absolute inset-y-0 left-0 overflow-hidden {{ $comingSoon ? 'opacity-50' : '' }}" style="width: 22%">
                                <img
                                    src="{{ asset('images/chapters/' . $chapter['image']) }}"
                                    alt=""
                                    aria-hidden="true"
                                    class="h-full w-full select-none object-cover object-center"
                                    style="filter: brightness(0) invert(1); opacity: 0.9; transform: scale(1.1);"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>

                            {{-- Top bar: chapter index --}}
                            <div class="relative flex items-center justify-end gap-3 px-5 pt-5">
                                <span class="font-mono text-[0.7rem] tabular-nums tracking-widest text-brand-cream/90">{{ $idx }}</span>
                                <span class="font-mono text-[0.6rem] uppercase tracking-[0.25em] text-brand-cream/90">· {{ __('Chapter') }}</span>
                            </div>

                            {{-- Title block pinned to bottom --}}
                            <div class="relative mt-auto flex flex-col {{ $comingSoon ? 'opacity-75' : '' }}" style="padding-left: 32%; padding-right: 1.25rem; padding-bottom: 1.25rem;">
                                <h3 class="font-display text-xl leading-[1.05] text-brand-cream lg:text-2xl">{{ $chapter['label'] }}</h3>
                                <p class="mt-1 font-display text-[0.8rem] italic text-brand-cream/85">{{ $chapter['tagline'] }}</p>
                                <div class="mt-3 h-px w-full bg-brand-cream/30"></div>
                                <div class="mt-3 flex items-baseline justify-between gap-2">
                                    @if ($comingSoon)
                                        <span class="font-mono text-[0.6rem] uppercase tracking-[0.25em] text-brand-cream/70" data-testid="shop-home-chapter-comingsoon-{{ $chapter['slug'] }}">
                                            {{ __('Coming soon') }}
                                        </span>
                                    @else
                                        <span class="font-mono text-[0.6rem] uppercase tracking-[0.25em] text-brand-cream/90 transition duration-150 group-hover:translate-x-1">
                                            {{ __('Open chapter') }} →
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- TODO: Re-enable customer / chef pull quote when we have a real quote —
         restore attribution / speaker name in the blockquote when this goes live.
    <section class="bg-brand-moss text-brand-cream" aria-labelledby="shop-home-social-heading">
        <div class="mx-auto max-w-5xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div data-testid="shop-home-social-proof">
                <x-reveal variant="fade-up">
                    <p class="eyebrow text-brand-cream/70">{{ __('From the table') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="150">
                    <blockquote class="mt-8 font-editorial text-3xl italic leading-snug text-brand-cream sm:text-4xl lg:text-5xl">
                        <span aria-hidden="true" class="text-accent-star/80">"</span>{{ __('I let the Erdgut crate write half my menu. When the produce is this honest, my job in the kitchen is mostly to stay out of its way.') }}<span aria-hidden="true" class="text-accent-star/80">"</span>
                    </blockquote>
                </x-reveal>
            </div>
        </div>
    </section>
    --}}

    {{-- How it works --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="shop-home-steps-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <x-section-heading
                :eyebrow="__('The rhythm')"
                color="fish"
                :title="__('From the shop to your doorstep in four steps.')"
                :lead="__('A simple order, delivered in the rhythm of the season — no account gymnastics required.')"
                id="shop-home-steps-heading"
            />
            @php
                $deliveryFee = \App\Support\Money::format(config('shop.delivery_fee_eur', 2.90));
                $homeSteps = [
                    ['chapter' => 'produce', 'title' => __('Browse the shop'), 'copy' => __('Explore what is in season across our five categories. We deliver everywhere in Berlin.')],
                    ['chapter' => 'eggdairy', 'title' => __('Choose a day and fill your basket'), 'copy' => __('Pick a delivery day from the calendar, then add what is available that day. Delivery days follow the harvest, so what is fresh and in stock changes through the season.')],
                    ['chapter' => 'bread', 'title' => __('Check out securely'), 'copy' => __('Order as much or as little as you like — there is no minimum. Pay online, or in cash or by card when you receive your products.')],
                    ['chapter' => 'fish', 'title' => __('Delivery or pickup'), 'copy' => __('Delivery anywhere in Berlin is a flat :fee €, or collect for free at one of our two pickup points. We email you the details, and your order arrives fresh on the chosen day.', ['fee' => $deliveryFee])],
                ];
                $stepChapterBorder = [
                    'produce' => 'border-chapter-produce',
                    'meat' => 'border-chapter-meat',
                    'fish' => 'border-chapter-fish',
                    'eggdairy' => 'border-chapter-eggdairy',
                    'bread' => 'border-chapter-bread',
                ];
                $stepChapterText = [
                    'produce' => 'text-chapter-produce',
                    'meat' => 'text-chapter-meat',
                    'fish' => 'text-chapter-fish',
                    'eggdairy' => 'text-chapter-eggdairy',
                    'bread' => 'text-chapter-bread',
                ];
            @endphp
            <ol class="mt-16 grid gap-10 reveal-stagger md:grid-cols-2 lg:grid-cols-4" data-testid="shop-home-how-it-works">
                @foreach ($homeSteps as $i => $step)
                    <x-reveal as="li" variant="fade-up" class="flex flex-col gap-5 border-t-2 {{ $stepChapterBorder[$step['chapter']] }} pt-6" style="--i:{{ $i }}">
                        <div class="flex items-baseline justify-between">
                            <p class="eyebrow {{ $stepChapterText[$step['chapter']] }}">{{ sprintf('%02d', $i + 1) }}</p>
                            <x-chapter-mark :chapter="$step['chapter']" size="sm" class="opacity-90" />
                        </div>
                        <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ $step['title'] }}</h3>
                        <p class="text-sm leading-relaxed text-brand-ink/70">{{ $step['copy'] }}</p>
                    </x-reveal>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Box waitlist: capture interest in the upcoming subscription boxes --}}
    <section class="bg-brand-ink text-brand-cream" aria-labelledby="shop-home-waitlist-heading">
        <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 sm:py-24 lg:px-8" data-testid="shop-home-waitlist">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-accent-star/90">{{ __('Coming soon') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="100">
                <h2 id="shop-home-waitlist-heading" class="mt-6 font-display text-3xl leading-tight text-brand-cream sm:text-4xl">
                    {{ __('Weekly boxes are on their way.') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="200">
                <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-brand-cream/80">
                    {{ __('We are putting together a subscription box: a seasonal selection from our producers, delivered every week at a friendlier price. Leave your email and we will tell you the moment it launches.') }}
                </p>
            </x-reveal>

            @if (session('status') === 'box-waitlist-subscribed')
                <x-reveal variant="fade-up" delay="100">
                    <p class="mx-auto mt-10 max-w-md border border-brand-cream/30 px-6 py-4 font-editorial text-lg italic text-brand-cream" data-testid="shop-home-waitlist-success">
                        {{ __('Thank you — we will be in touch the moment boxes launch.') }}
                    </p>
                </x-reveal>
            @else
                <x-reveal variant="fade-up" delay="300">
                    <form
                        method="POST"
                        action="{{ route('public.box-waitlist.store') }}"
                        class="mx-auto mt-10 flex max-w-md flex-col gap-3 sm:flex-row"
                        data-testid="shop-home-waitlist-form"
                    >
                        @csrf
                        {{-- Honeypot: hidden from humans, must stay empty. --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="shop-home-waitlist-website">{{ __('Leave this field empty') }}</label>
                            <input type="text" id="shop-home-waitlist-website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <label for="shop-home-waitlist-email" class="sr-only">{{ __('Your email address') }}</label>
                        <input
                            type="email"
                            id="shop-home-waitlist-email"
                            name="email"
                            required
                            value="{{ old('email') }}"
                            placeholder="{{ __('Your email address') }}"
                            class="w-full flex-1 border-b border-brand-cream/40 bg-transparent py-3 text-base text-brand-cream placeholder:text-brand-cream/50 focus:border-brand-cream focus:outline-none"
                        >
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-6 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream"
                        >
                            {{ __('Keep me posted') }}
                        </button>
                    </form>
                    @error('email')
                        <p class="mt-3 text-sm text-accent-star" data-testid="shop-home-waitlist-error">{{ $message }}</p>
                    @enderror
                </x-reveal>
            @endif
        </div>
    </section>

    {{-- Bottom CTA --}}
    <section class="relative" aria-labelledby="shop-home-bottom-cta-heading">
        <div class="mx-auto max-w-6xl px-4 py-24 text-center sm:px-6 sm:py-32 lg:px-8">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-chapter-produce">{{ __('Join the table') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="150">
                <h2 id="shop-home-bottom-cta-heading" class="mt-6 font-display text-4xl leading-tight text-brand-moss sm:text-5xl lg:text-6xl">
                    {{ __('Ready to eat better and support Berlin\'s producers?') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="300">
                <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    <a
                        href="{{ route('public.shop.index') }}"
                        class="btn-solid"
                        data-testid="shop-home-bottom-primary"
                    >
                        {{ __('Start your order') }}
                    </a>
                    <a
                        href="{{ route('public.shop-about') }}"
                        class="inline-flex items-center justify-center text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                    >
                        {{ __('Learn more about Erdgut') }}
                    </a>
                </div>
            </x-reveal>
        </div>
    </section>
@endsection
