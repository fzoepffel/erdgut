@extends('layouts.marketing')

@section('content')
    {{-- Hero: video + editorial headline, sits below the olive-green masthead. --}}
    <section class="relative overflow-hidden" aria-labelledby="home-hero-heading">
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
                <source src="{{ asset('videos/mock/newvid.mp4') }}" type="video/mp4">
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
                    <h1 id="home-hero-heading" class="mt-4 max-w-4xl font-display text-5xl font-semibold leading-[1.05] tracking-tight text-brand-cream sm:text-6xl lg:text-7xl">
                        {{ __('Weekly from small farms, in the rhythm of the seasons.') }}
                    </h1>
                </x-reveal>
                <x-reveal variant="fade-up" delay="300">
                    <p class="mt-6 max-w-xl font-editorial text-base italic leading-relaxed text-brand-cream/90 sm:text-lg">
                        {{ __('Curated by Lucille, Arthur and Milan — one box a week, by producers we know by name.') }}
                    </p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="400">
                    @php
                        $trialEnabled = (bool) config('features.trial_orders_enabled', true);
                    @endphp
                    <div class="mt-10 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center" data-testid="home-hero-ctas">
                        @auth
                            <a
                                href="{{ route('app.dashboard') }}"
                                class="inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cream"
                                data-testid="home-primary-cta-authenticated"
                            >
                                {{ __('Go to dashboard') }}
                            </a>
                            @if ($trialEnabled)
                                <a
                                    href="{{ route('app.onboarding.start', ['intent' => 'trial']) }}"
                                    class="inline-flex items-center justify-center border border-brand-cream/70 px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream transition hover:border-brand-cream hover:bg-brand-cream/10"
                                    data-testid="home-cta-try-box"
                                >
                                    {{ __('Try a box') }}
                                </a>
                            @endif
                            <a
                                href="{{ route('app.onboarding.start', ['intent' => 'subscription']) }}"
                                class="inline-flex items-center justify-center border border-brand-cream/70 px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream transition hover:border-brand-cream hover:bg-brand-cream/10"
                                data-testid="home-cta-subscribe"
                            >
                                {{ __('Subscribe') }}
                            </a>
                        @else
                            @if ($trialEnabled)
                                <a
                                    href="{{ route('app.onboarding.start', ['intent' => 'trial']) }}"
                                    class="inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cream"
                                    data-testid="home-cta-try-box"
                                >
                                    {{ __('Try a box') }}
                                </a>
                            @endif
                            <a
                                href="{{ route('app.onboarding.start', ['intent' => 'subscription']) }}"
                                class="inline-flex items-center justify-center border {{ $trialEnabled ? 'border-brand-cream/70' : 'border-brand-cream bg-brand-cream text-brand-ink' }} px-8 py-3 text-sm font-semibold uppercase tracking-eyebrow {{ $trialEnabled ? 'text-brand-cream' : '' }} transition hover:border-brand-cream hover:bg-brand-cream/10"
                                data-testid="home-cta-subscribe"
                            >
                                {{ __('Subscribe') }}
                            </a>
                            <a
                                href="{{ route('auth.login') }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream/90 hover:underline"
                                data-testid="home-secondary-cta-login"
                            >
                                {{ __('Log in') }}
                            </a>
                        @endauth
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Founder letter: drop-cap editorial paragraph, triple-star, signed --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="home-letter-heading">
        <div class="mx-auto max-w-3xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8" data-testid="home-founder-letter">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-chapter-produce">{{ __('A word from the founders') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="100">
                <h2 id="home-letter-heading" class="mt-6 font-display text-3xl font-semibold leading-tight text-brand-moss sm:text-4xl">
                    {{ __('Why we started Erdgut.') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="200">
                <p class="drop-cap mt-8 font-editorial text-lg leading-relaxed text-brand-ink/85 sm:text-xl" style="--chapter-ink: theme('colors.chapter.produce.DEFAULT');">
                    {{ __('We grew up with grandparents who knew where every egg, every tomato, every loaf came from. Today, Berlin households rarely have that relationship with their food. Erdgut is our attempt to rebuild it — one box a week, from a small circle of farms, bakeries and fisheries we picked ourselves.') }}
                </p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="300">
                <p class="mt-6 font-editorial text-lg leading-relaxed text-brand-ink/85">
                    {{ __('No anonymous supply chains. No algorithmic curation. Just seasonal food, signed by the hands that grew it, delivered in the rhythm of the year.') }}
                </p>
            </x-reveal>
            <x-triple-star class="mt-12" />
            <x-reveal variant="fade-up" delay="400">
                <div class="mt-10 flex justify-center">
                    <x-founder-signature size="lg" />
                </div>
            </x-reveal>
        </div>
    </section>

    {{-- Seven chapters: table of contents, catalogue style --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper" aria-labelledby="home-chapters-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <x-section-heading
                eyebrow="{{ __('What is in the book') }}"
                color="producers"
                title="{{ __('Seven chapters, one season.') }}"
                lead="{{ __('Like the pages of an almanac, each chapter runs with its own colour, its own producers, its own rhythm.') }}"
                id="home-chapters-heading"
            />

            <div class="-mx-4 mt-16 flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth px-4 pb-2 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" data-testid="home-chapter-strip">
                @php
                    // Each chapter reuses the catalogue strip crop (transparent PNG
                    // with the chapter-coloured illustration on the left 32% of the
                    // original A4 cover). We render the illustration white via a
                    // `brightness(0) invert(1)` filter and set the left column to the
                    // full chapter colour, producing catalogue-style coloured plates.
                    $chapters = [
                        ['slug' => 'produce', 'label' => __('Fruit & Vegetables'), 'tagline' => __('Roots, greens, fruit'), 'href' => route('app.products.index', ['category' => 'vegetables']), 'image' => 'catalog-strip-cabbage.png'],
                        ['slug' => 'meat', 'label' => __('Meat'), 'tagline' => __('Pasture-raised cuts'), 'href' => route('app.products.index', ['category' => 'meat']), 'image' => 'catalog-strip-waguy.png'],
                        ['slug' => 'fish', 'label' => __('Fish & Seafood'), 'tagline' => __('Line-caught & smoked'), 'href' => route('app.products.index', ['category' => 'fish']), 'image' => 'catalog-strip-sardines.png'],
                        ['slug' => 'eggdairy', 'label' => __('Eggs & Dairy'), 'tagline' => __('Milk, butter, cheese'), 'href' => route('app.products.index', ['category' => 'dairy']), 'image' => 'catalog-strip-eggs.png'],
                        ['slug' => 'bread', 'label' => __('Bread'), 'tagline' => __('Sourdough & pastry'), 'href' => route('app.products.index', ['category' => 'bakery']), 'image' => 'catalog-strip-wheat.png'],
                        ['slug' => 'producers', 'label' => __('Producers'), 'tagline' => __('The people behind the food'), 'href' => route('public.producers'), 'image' => 'catalog-strip-producers.png'],
                        ['slug' => 'almanach', 'label' => __('Almanac'), 'tagline' => __('Seasons, stories, traditions'), 'href' => route('public.about'), 'image' => 'catalog-strip-almanach.png'],
                    ];
                    $chapterPanelBg = [
                        'produce' => 'bg-chapter-produce',
                        'meat' => 'bg-chapter-meat',
                        'fish' => 'bg-chapter-fish',
                        'eggdairy' => 'bg-chapter-eggdairy',
                        'bread' => 'bg-chapter-bread',
                        'producers' => 'bg-chapter-producers',
                        'almanach' => 'bg-chapter-almanach',
                    ];
                @endphp
                @foreach ($chapters as $i => $chapter)
                    <x-reveal variant="fade-up" delay="{{ $i * 60 }}" class="group flex w-[280px] shrink-0 snap-start sm:w-[320px]">
                        <a
                            href="{{ $chapter['href'] }}"
                            class="flex h-full min-h-[220px] w-full overflow-hidden text-brand-cream transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_32px_-20px_rgba(44,36,28,0.35)] {{ $chapterPanelBg[$chapter['slug']] }}"
                            data-testid="home-chapter-tile-{{ $chapter['slug'] }}"
                        >
                            <div class="relative flex w-16 shrink-0 items-stretch overflow-hidden sm:w-20">
                                <img
                                    src="{{ asset('images/chapters/' . $chapter['image']) }}"
                                    alt=""
                                    aria-hidden="true"
                                    class="h-full w-full select-none object-contain object-center"
                                    style="filter: brightness(0) invert(1);"
                                />
                            </div>
                            <div class="flex flex-1 flex-col justify-between gap-6 p-6 sm:p-7">
                                <p class="eyebrow text-brand-cream/80">{{ sprintf('%02d', $i + 1) }} &nbsp;·&nbsp; {{ __('Chapter') }}</p>
                                <div>
                                    <h3 class="font-display text-2xl leading-tight hyphens-auto break-words sm:text-3xl" lang="{{ app()->getLocale() }}">{{ $chapter['label'] }}</h3>
                                    <p class="mt-2 font-editorial text-sm italic leading-relaxed text-brand-cream/80 sm:text-base">{{ $chapter['tagline'] }}</p>
                                </div>
                                <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-cream/90 transition-[gap] duration-200 group-hover:gap-3 group-hover:text-brand-cream">
                                    {{ __('Open chapter') }}
                                    <span aria-hidden="true">&rarr;</span>
                                </span>
                            </div>
                        </a>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Trial promo --}}
    @if ((bool) config('features.trial_orders_enabled', true))
    <section class="border-b border-brand-ink/10 bg-chapter-produce-soft/40" aria-labelledby="home-trial-heading" data-testid="home-trial-section">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-16 sm:px-6 sm:py-20 md:grid-cols-[1fr_auto] md:items-center lg:px-8">
            <div>
                <p class="eyebrow text-chapter-produce">{{ __('No commitment') }}</p>
                <h2 id="home-trial-heading" class="mt-3 font-display text-3xl font-semibold leading-tight text-brand-moss sm:text-4xl">
                    {{ __('Try one box before you commit.') }}
                </h2>
                <p class="mt-4 max-w-2xl font-editorial text-base leading-relaxed text-brand-ink/80 sm:text-lg">
                    {{ __('EUR :amount for a single Erdgut box — one delivery, no subscription, no stored card. You pick the date; we handle the rest.', ['amount' => \App\Support\Money::format(config('box.trial_price_oneoff_ttc', 35), 0)]) }}
                </p>
            </div>
            <a
                href="{{ route('app.onboarding.start', ['intent' => 'trial']) }}"
                class="inline-flex items-center justify-center bg-chapter-produce px-6 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream transition hover:bg-brand-moss"
                data-testid="home-trial-cta"
            >
                {{ __('Try a box') }}
            </a>
        </div>
    </section>
    @endif

    {{-- How it works --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="home-steps-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <x-section-heading
                eyebrow="{{ __('The rhythm') }}"
                color="fish"
                title="{{ __('From signup to your doorstep in four steps.') }}"
                lead="{{ __('A predictable weekly beat that leaves room for the unpredictable parts of your life.') }}"
                id="home-steps-heading"
            />
            <ol class="mt-16 grid gap-12 reveal-stagger md:grid-cols-2 lg:grid-cols-4" data-testid="home-how-it-works">
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:0">
                    <span class="font-display text-6xl font-semibold text-chapter-produce/40">01</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Create your account') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Register and verify your email to get started.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:1">
                    <span class="font-display text-6xl font-semibold text-chapter-fish/40">02</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Choose your plan') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Pick box size and set preferences and dietary notes.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:2">
                    <span class="font-display text-6xl font-semibold text-chapter-eggdairy/40">03</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('We build your week') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Your box is composed from seasonal, regional produce.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:3">
                    <span class="font-display text-6xl font-semibold text-chapter-meat/40">04</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('You stay in control') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Change delivery, skip a week, or add extras before the deadline.') }}</p>
                </x-reveal>
            </ol>
        </div>
    </section>

    {{-- Pull quote / community --}}
    <section class="bg-brand-moss text-brand-cream" aria-labelledby="home-social-heading">
        <div class="mx-auto max-w-5xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div data-testid="home-social-proof">
                <x-reveal variant="fade-up">
                    <p class="eyebrow text-brand-cream/70">{{ __('From the table') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="150">
                    <blockquote class="mt-8 font-editorial text-3xl italic leading-snug text-brand-cream sm:text-4xl lg:text-5xl">
                        <span aria-hidden="true" class="text-accent-star/80">“</span>{{ __('Every week a small piece of the countryside lands on my table, and I know exactly whose hands prepared it.') }}<span aria-hidden="true" class="text-accent-star/80">”</span>
                    </blockquote>
                </x-reveal>
                <x-reveal variant="fade-up" delay="300">
                    <p class="mt-8 max-w-xl text-base leading-relaxed text-brand-cream/80">
                        {{ __('We are onboarding early supporters — real stories and numbers will appear here as the community grows.') }}
                    </p>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Value props --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="home-values-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <h2 id="home-values-heading" class="sr-only">{{ __('What you get with Erdgut') }}</h2>
            <div class="grid gap-12 reveal-stagger md:grid-cols-3" data-testid="home-value-cards">
                <x-reveal as="article" variant="fade-up" class="flex flex-col gap-4 border-t border-chapter-produce pt-8" style="--i:0">
                    <p class="eyebrow text-chapter-produce">{{ __('Producers') }}</p>
                    <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ __('Every name, every field.') }}</h3>
                    <p class="font-editorial text-base leading-relaxed text-brand-ink/80">
                        {{ __('Products link back to real people and real places — not anonymous supply chains — so you can trust what lands in your kitchen.') }}
                    </p>
                </x-reveal>
                <x-reveal as="article" variant="fade-up" class="flex flex-col gap-4 border-t border-chapter-fish pt-8" style="--i:1">
                    <p class="eyebrow text-chapter-fish">{{ __('Your week') }}</p>
                    <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ __('Built around your week.') }}</h3>
                    <p class="font-editorial text-base leading-relaxed text-brand-ink/80">
                        {{ __('See deadlines, tweak your box when it is editable, align delivery with your routine.') }}
                    </p>
                </x-reveal>
                <x-reveal as="article" variant="fade-up" class="flex flex-col gap-4 border-t border-chapter-meat pt-8" style="--i:2">
                    <p class="eyebrow text-chapter-meat">{{ __('Fair share') }}</p>
                    <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ __('Fair share for farmers.') }}</h3>
                    <p class="font-editorial text-base leading-relaxed text-brand-ink/80">
                        {{ __('We focus on direct relationships and clear pricing so more of what you pay supports the people who grow your food.') }}
                    </p>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Bottom CTA --}}
    <section class="relative" aria-labelledby="home-bottom-cta-heading">
        <div class="mx-auto max-w-6xl px-4 py-24 text-center sm:px-6 sm:py-32 lg:px-8">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-chapter-produce">{{ __('Join the table') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="150">
                <h2 id="home-bottom-cta-heading" class="mt-6 font-display text-4xl font-semibold leading-tight text-brand-moss sm:text-5xl lg:text-6xl">
                    {{ __('Ready to eat better and support Berlin\'s producers?') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="300">
                <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                    @auth
                        <a
                            href="{{ route('app.dashboard') }}"
                            class="btn-solid"
                            data-testid="home-bottom-primary-authenticated"
                        >
                            {{ __('Go to dashboard') }}
                        </a>
                    @else
                        <a
                            href="{{ route('app.onboarding.start') }}"
                            class="btn-solid"
                            data-testid="home-bottom-primary-guest"
                        >
                            {{ __('Get started') }}
                        </a>
                        <a
                            href="{{ route('auth.login') }}"
                            class="inline-flex items-center justify-center text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                        >
                            {{ __('Already have an account? Log in') }}
                        </a>
                    @endauth
                </div>
            </x-reveal>
        </div>
    </section>
@endsection
