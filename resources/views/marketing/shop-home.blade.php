@extends('layouts.shop-marketing')

@section('content')
    {{-- Hero: video + editorial headline, sits below the olive-green masthead. --}}
    <section class="relative overflow-hidden" aria-labelledby="shop-home-hero-heading">
        <div class="relative h-[82vh] min-h-[620px] w-full overflow-hidden bg-brand-ink/80">
            <video
                class="absolute inset-0 h-full w-full object-cover opacity-90"
                autoplay
                muted
                loop
                playsinline
                preload="metadata"
                poster="{{ asset('images/mock/hero/hero-poster.jpg') }}"
                data-parallax
            >
                <source src="{{ asset('videos/mock/shop-hero.mp4') }}" type="video/mp4">
            </video>
            <div class="absolute inset-0 bg-gradient-to-b from-brand-ink/25 via-brand-ink/45 to-brand-ink/75"></div>

            {{-- Month stamp, catalogue style --}}
            <div class="absolute right-4 top-6 sm:right-8 sm:top-10 lg:right-12 lg:top-12">
                <x-month-label color="cream" :year="now()->format('Y')" />
            </div>

            <div class="relative mx-auto flex h-full max-w-7xl flex-col justify-end px-4 pb-16 sm:px-6 sm:pb-20 lg:px-8 lg:pb-24">
                <x-reveal variant="fade-up" delay="100">
                    <p class="eyebrow text-brand-cream/85">{{ __('An Erdgut for Berlin') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="200">
                    <h1 id="shop-home-hero-heading" class="mt-4 max-w-4xl font-display text-5xl leading-[1.05] tracking-tight text-brand-cream sm:text-6xl lg:text-7xl">
                        {{ __('Seasonal food from small farms, delivered across Berlin.') }}
                    </h1>
                </x-reveal>
                <x-reveal variant="fade-up" delay="300">
                    <p class="mt-6 max-w-xl font-editorial text-base italic leading-relaxed text-brand-cream/90 sm:text-lg">
                        {{ __('Browse the shop and order exactly what you like — by producers we know by name, harvested in the rhythm of the seasons.') }}
                    </p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="400">
                    <div class="mt-10 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center" data-testid="shop-home-hero-ctas">
                        <a
                            href="{{ route('public.shop.index') }}"
                            class="inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cream"
                            data-testid="shop-home-cta-shop"
                        >
                            {{ __('Visit the shop') }}
                        </a>
                        <a
                            href="{{ route('public.shop.products.index') }}"
                            class="inline-flex items-center justify-center border border-brand-cream/70 px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream transition hover:border-brand-cream hover:bg-brand-cream/10"
                            data-testid="shop-home-cta-products"
                        >
                            {{ __('Browse products') }}
                        </a>
                        @auth
                            <a
                                href="{{ route('app.dashboard') }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream/90 hover:underline"
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
                <p class="eyebrow text-chapter-produce">{{ __('A word from the founders') }}</p>
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

    {{-- Producers invitation — same chapter as shop-about until we have a real pull quote. --}}
    <section class="bg-brand-moss text-brand-cream" aria-labelledby="shop-home-producers-heading">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-16" data-testid="shop-home-producers-pitch">
                <x-reveal variant="fade-up" class="lg:col-span-7">
                    <p class="eyebrow text-brand-cream/70">{{ __('Chapter 04') }}</p>
                    <h2 id="shop-home-producers-heading" class="mt-4 font-display text-3xl font-semibold leading-tight text-brand-cream sm:text-4xl lg:text-5xl">
                        {{ __('A small circle of hands.') }}
                    </h2>
                    <p class="mt-8 font-editorial text-lg italic leading-relaxed text-brand-cream/85 sm:text-xl">
                        {{ __('We grow Erdgut the same way we cook: slowly, with the people we already trust. Every producer joins the circle by invitation, not by listing.') }}
                    </p>
                    <p class="mt-8">
                        <a href="{{ route('public.shop.producers.index') }}" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream underline-offset-4 hover:underline" data-testid="shop-home-producers-link">
                            {{ __('Meet the circle') }}
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </p>
                </x-reveal>
                <x-reveal variant="scale" class="lg:col-span-5">
                    <div class="aspect-[4/5] overflow-hidden">
                        <img src="{{ asset('images/mock/producers/marcus.jpg') }}" alt="" class="h-full w-full object-cover" loading="lazy">
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Five chapters: portrait book-cover grid, same design as products page --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper" aria-labelledby="shop-home-chapters-heading">
        <div class="mx-auto max-w-[1400px] px-6 py-20 sm:py-24 lg:px-12">
            <x-section-heading
                eyebrow="{{ __('What is in the shop') }}"
                color="producers"
                title="{{ __('Five chapters, one season.') }}"
                lead="{{ __('Five chapters of producers, one season of food.') }}"
                id="shop-home-chapters-heading"
            />

            @php
                use App\Support\ProductCategoryStyle;

                // Five product chapters only — Producers and Almanac removed.
                // `category` drives the coming-soon check against $availableCategories.
                $homeChapters = [
                    ['slug' => 'produce',  'category' => 'produce',       'label' => __('Fruit & Vegetables'), 'tagline' => __('Roots, greens, fruit'),   'href' => route('public.shop.products.index', ['category' => 'produce']),        'image' => 'catalog-strip-cabbage.png'],
                    ['slug' => 'eggdairy', 'category' => 'eggs_and_dairy','label' => __('Eggs & Dairy'),      'tagline' => __('Milk, butter, cheese'),   'href' => route('public.shop.products.index', ['category' => 'eggs_and_dairy']), 'image' => 'catalog-strip-eggs.png'],
                    ['slug' => 'meat',     'category' => 'meat',          'label' => __('Meat'),               'tagline' => __('Pasture-raised cuts'),    'href' => route('public.shop.products.index', ['category' => 'meat']),           'image' => 'catalog-strip-waguy.png'],
                    ['slug' => 'fish',     'category' => 'fish',          'label' => __('Fish & Seafood'),     'tagline' => __('Line-caught & smoked'),   'href' => route('public.shop.products.index', ['category' => 'fish']),           'image' => 'catalog-strip-sardines.png'],
                    ['slug' => 'bread',    'category' => 'bread',         'label' => __('Bread'),             'tagline' => __('Sourdough & pastry'),      'href' => route('public.shop.products.index', ['category' => 'bread']),          'image' => 'catalog-strip-wheat.png'],
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
                eyebrow="{{ __('The rhythm') }}"
                color="fish"
                title="{{ __('From the shop to your doorstep in four steps.') }}"
                lead="{{ __('A simple order, delivered in the rhythm of the season — no account gymnastics required.') }}"
                id="shop-home-steps-heading"
            />
            <ol class="mt-16 grid gap-12 reveal-stagger md:grid-cols-2 lg:grid-cols-4" data-testid="shop-home-how-it-works">
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:0">
                    <span class="font-display text-6xl text-chapter-produce/40">01</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Browse the shop') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Explore what is in season across seven chapters of producers.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:1">
                    <span class="font-display text-6xl text-chapter-fish/40">02</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Fill your basket') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Add exactly the products you want, in the quantities you need.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:2">
                    <span class="font-display text-6xl text-chapter-eggdairy/40">03</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Check out securely') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Pick a delivery date and pay safely — no subscription required.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:3">
                    <span class="font-display text-6xl text-chapter-meat/40">04</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('We deliver to Berlin') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Your order arrives fresh, with a note from the producers behind it.') }}</p>
                </x-reveal>
            </ol>
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
                        {{ __('Visit the shop') }}
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
