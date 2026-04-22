@extends('layouts.marketing')

@section('content')
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
            <div class="absolute inset-0 bg-gradient-to-b from-brand-ink/20 via-brand-ink/35 to-brand-ink/65"></div>

            <div class="relative mx-auto flex h-full max-w-7xl flex-col justify-end px-4 pb-16 sm:px-6 sm:pb-20 lg:px-8 lg:pb-24">
                <x-reveal variant="fade" class="mb-8">
                    <x-application-logo variant="white" :with-wordmark="false" class="h-20 w-auto opacity-90 sm:h-24" alt="" />
                </x-reveal>
                <x-reveal variant="fade-up" delay="100">
                    <p class="eyebrow text-brand-cream/80">{{ __('Farm-to-consumer in Berlin') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="200">
                    <h1 id="home-hero-heading" class="mt-4 max-w-4xl font-display text-5xl font-semibold leading-[1.05] tracking-tight text-brand-cream sm:text-6xl lg:text-7xl">
                        {{ __('Fresh weekly boxes from local farms, delivered transparently.') }}
                    </h1>
                </x-reveal>
                <x-reveal variant="fade-up" delay="300">
                    <p class="mt-6 max-w-xl text-base leading-relaxed text-brand-cream/85 sm:text-lg">
                        {{ __('Erdgut connects Berlin households with small-scale producers through a simple subscription. See where your food comes from and support fair pricing for farmers.') }}
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

    {{-- Trust signals --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="home-trust-heading">
        <h2 id="home-trust-heading" class="sr-only">{{ __('Why customers trust Erdgut') }}</h2>
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <ul class="grid gap-10 reveal-stagger sm:grid-cols-2 lg:grid-cols-4" data-testid="home-trust-row">
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-3" style="--i:0">
                    <span class="eyebrow text-chapter-produce">01</span>
                    <p class="font-display text-xl text-brand-ink">{{ __('Regional producers') }}</p>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Food from farms and growers near Berlin.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-3" style="--i:1">
                    <span class="eyebrow text-chapter-fish">02</span>
                    <p class="font-display text-xl text-brand-ink">{{ __('Transparent origins') }}</p>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Every product is tied to a named producer.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-3" style="--i:2">
                    <span class="eyebrow text-chapter-meat">03</span>
                    <p class="font-display text-xl text-brand-ink">{{ __('Flexible delivery') }}</p>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Skip, postpone, or adjust when life changes.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-3" style="--i:3">
                    <span class="eyebrow text-brand-ink">04</span>
                    <p class="font-display text-xl text-brand-ink">{{ __('Secure payment') }}</p>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Card handling via SumUp—no card data stored on our servers.') }}</p>
                </x-reveal>
            </ul>
        </div>
    </section>

    {{-- Stalls editorial strip --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="home-stalls-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <x-section-heading
                eyebrow="{{ __('Five stalls, one market') }}"
                color="produce"
                title="{{ __('Vegetables, fruit, bread, dairy, fish and meat — curated weekly.') }}"
                lead="{{ __('Each stall is run by producers we know by name. The colours travel with the food from profile to box to invoice.') }}"
                id="home-stalls-heading"
            />

            <div class="mt-16 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @php
                    $stalls = [
                        ['slug' => 'produce', 'label' => __('Greengrocer'), 'tagline' => __('Roots, greens, fruit'), 'image' => 'stalls/produce/scene.jpg'],
                        ['slug' => 'bakery', 'label' => __('Bakery'), 'tagline' => __('Sourdough & pastry'), 'image' => 'stalls/bakery/scene.jpg'],
                        ['slug' => 'dairy', 'label' => __('Dairy'), 'tagline' => __('Milk, butter, cheese'), 'image' => 'stalls/dairy/scene.jpg'],
                        ['slug' => 'fish', 'label' => __('Fishmonger'), 'tagline' => __('Line-caught & smoked'), 'image' => 'stalls/fish/scene.jpg'],
                        ['slug' => 'meat', 'label' => __('Butcher'), 'tagline' => __('Pasture-raised cuts'), 'image' => 'stalls/meat/scene.jpg'],
                    ];
                    $stallColor = [
                        'produce' => 'text-chapter-produce',
                        'bakery' => 'text-brand-ink',
                        'dairy' => 'text-brand-ink',
                        'fish' => 'text-chapter-fish',
                        'meat' => 'text-chapter-meat',
                    ];
                @endphp
                @foreach ($stalls as $i => $stall)
                    <x-reveal variant="fade-up" delay="{{ $i * 80 }}" class="group flex flex-col gap-4">
                        <div class="relative aspect-[3/4] overflow-hidden bg-brand-ink/5">
                            <img src="{{ asset('images/mock/' . $stall['image']) }}" alt="" class="h-full w-full object-cover transition duration-700 group-hover:scale-105" loading="lazy">
                        </div>
                        <div>
                            <p class="eyebrow {{ $stallColor[$stall['slug']] }}">{{ $stall['label'] }}</p>
                            <p class="mt-2 font-display text-lg text-brand-ink">{{ $stall['tagline'] }}</p>
                        </div>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Trial promo --}}
    @if ((bool) config('features.trial_orders_enabled', true))
    <section class="border-b border-brand-ink/10 bg-chapter-produce/5" aria-labelledby="home-trial-heading" data-testid="home-trial-section">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 grid gap-8 md:grid-cols-[1fr_auto] md:items-center">
            <div>
                <p class="eyebrow text-chapter-produce">{{ __('No commitment') }}</p>
                <h2 id="home-trial-heading" class="mt-3 font-display text-3xl font-semibold leading-tight text-brand-ink sm:text-4xl">
                    {{ __('Try one box before you commit.') }}
                </h2>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-brand-ink/75">
                    {{ __('EUR :amount for a single Erdgut box — one delivery, no subscription, no stored card. You pick the date; we handle the rest.', ['amount' => number_format((float) config('box.trial_price_oneoff_ttc', 35), 0)]) }}
                </p>
            </div>
            <a
                href="{{ route('app.onboarding.start', ['intent' => 'trial']) }}"
                class="btn-solid bg-chapter-produce text-white hover:bg-chapter-produce/90 px-6 py-3 text-sm font-semibold uppercase tracking-eyebrow"
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
                eyebrow="{{ __('How it works') }}"
                color="fish"
                title="{{ __('From signup to your doorstep in four steps.') }}"
                lead="{{ __('A predictable rhythm that leaves room for the unpredictable parts of your week.') }}"
                id="home-steps-heading"
            />
            <ol class="mt-16 grid gap-12 reveal-stagger md:grid-cols-2 lg:grid-cols-4" data-testid="home-how-it-works">
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:0">
                    <span class="font-display text-6xl font-semibold text-brand-ink/20">01</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Create your account') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Register and verify your email to get started.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:1">
                    <span class="font-display text-6xl font-semibold text-brand-ink/20">02</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Choose your plan') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Pick box size and set preferences and dietary notes.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:2">
                    <span class="font-display text-6xl font-semibold text-brand-ink/20">03</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('We build your week') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Your box is composed from seasonal, regional produce.') }}</p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4" style="--i:3">
                    <span class="font-display text-6xl font-semibold text-brand-ink/20">04</span>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('You stay in control') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">{{ __('Change delivery, skip a week, or add extras before the deadline.') }}</p>
                </x-reveal>
            </ol>
        </div>
    </section>

    {{-- Pull quote --}}
    <section class="bg-brand-ink text-brand-cream" aria-labelledby="home-social-heading">
        <div class="mx-auto max-w-5xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
            <div data-testid="home-social-proof">
                <x-reveal variant="fade-up">
                    <p class="eyebrow text-brand-cream/60">{{ __('From the community') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="150">
                    <blockquote class="mt-8 font-display text-3xl leading-snug text-brand-cream sm:text-4xl lg:text-5xl">
                        <span aria-hidden="true" class="text-chapter-produce/80">“</span>{{ __('Join Berlin households supporting local food') }}<span aria-hidden="true" class="text-chapter-produce/80">”</span>
                    </blockquote>
                </x-reveal>
                <x-reveal variant="fade-up" delay="300">
                    <p class="mt-8 max-w-xl text-base leading-relaxed text-brand-cream/80">
                        {{ __('We are onboarding early supporters—real stories and numbers will appear here as the community grows.') }}
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
                <x-reveal as="article" variant="fade-up" class="flex flex-col gap-4 border-t border-brand-ink/20 pt-8" style="--i:0">
                    <p class="eyebrow text-chapter-produce">{{ __('Producers') }}</p>
                    <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ __('Know every producer') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">
                        {{ __('Products link back to people and places—not anonymous supply chains—so you can trust what lands in your kitchen.') }}
                    </p>
                </x-reveal>
                <x-reveal as="article" variant="fade-up" class="flex flex-col gap-4 border-t border-brand-ink/20 pt-8" style="--i:1">
                    <p class="eyebrow text-chapter-fish">{{ __('Your week') }}</p>
                    <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ __('Built around your week') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">
                        {{ __('See deadlines, tweak your box when it is editable, and align delivery with your routine.') }}
                    </p>
                </x-reveal>
                <x-reveal as="article" variant="fade-up" class="flex flex-col gap-4 border-t border-brand-ink/20 pt-8" style="--i:2">
                    <p class="eyebrow text-chapter-meat">{{ __('Fair share') }}</p>
                    <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ __('Fair share for farmers') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/70">
                        {{ __('We focus on direct relationships and clear value so more of what you pay supports the people who grow your food.') }}
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
                <h2 id="home-bottom-cta-heading" class="mt-6 font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl lg:text-6xl">
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
