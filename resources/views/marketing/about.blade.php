@extends('layouts.marketing')

@section('content')
    <section class="border-b border-brand-ink/10" aria-labelledby="about-hero-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16" data-testid="about-hero">
                <div class="lg:col-span-6">
                    <x-reveal variant="fade-up">
                        <p class="eyebrow text-category-produce">{{ __('About Erdgut') }}</p>
                    </x-reveal>
                    <x-reveal variant="fade-up" delay="100">
                        <h1 id="about-hero-heading" class="mt-5 font-display text-5xl font-semibold leading-[1.05] tracking-tight text-brand-ink sm:text-6xl lg:text-7xl">
                            {{ __('A transparent bridge between Berlin households and regional organic food.') }}
                        </h1>
                    </x-reveal>
                    <x-reveal variant="fade-up" delay="200">
                        <p class="mt-8 max-w-xl text-lg leading-relaxed text-brand-ink/75">
                            {{ __('We make weekly local eating simple: one subscription, clear origins, and fair relationships with the people who grow your food.') }}
                        </p>
                    </x-reveal>
                    <x-reveal variant="fade-up" delay="300">
                        <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center" data-testid="about-hero-ctas">
                            <a
                                href="{{ route('auth.register') }}"
                                class="btn-solid"
                                data-testid="about-cta-register"
                            >
                                {{ __('Get started') }}
                            </a>
                            <a
                                href="{{ route('public.contact') }}"
                                class="btn-ghost"
                                data-testid="about-cta-contact"
                            >
                                {{ __('Contact us') }}
                            </a>
                            <a
                                href="{{ route('public.producers') }}"
                                class="inline-flex items-center justify-center text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                data-testid="about-cta-producers"
                            >
                                {{ __('Meet our producers') }}
                            </a>
                        </div>
                    </x-reveal>
                </div>
                <x-reveal variant="scale" class="lg:col-span-6">
                    <div class="aspect-[4/5] overflow-hidden bg-brand-ink/10">
                        <img
                            src="{{ asset('images/mock/hero/about-hero.jpg') }}"
                            alt=""
                            class="h-full w-full object-cover"
                            width="1600"
                            height="2000"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Story arc --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="about-story-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <x-section-heading
                eyebrow="{{ __('Our story') }}"
                color="produce"
                title="{{ __('A simple frustration grew into a clear idea.') }}"
                lead="{{ __('Erdgut grew from a simple frustration: great regional food exists around Berlin, but busy households rarely see who grew it or how value flows back to farms.') }}"
                id="about-story-heading"
            />
            <ol class="mt-16 grid gap-12 reveal-stagger md:grid-cols-3" data-testid="about-story-arc">
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4 border-t border-category-produce pt-8" style="--i:0">
                    <p class="eyebrow text-category-produce">{{ __('Chapter 01') }}</p>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Why Berlin') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/75">
                        {{ __("The city's appetite for organic and regional food keeps growing. We focus on connections that stay short, seasonal, and honest.") }}
                    </p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4 border-t border-category-produce pt-8" style="--i:1">
                    <p class="eyebrow text-category-produce">{{ __('Chapter 02') }}</p>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __('Why a weekly box') }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/75">
                        {{ __('A predictable rhythm helps producers plan harvests and lets you adjust one transparent order instead of juggling many small shops.') }}
                    </p>
                </x-reveal>
                <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4 border-t border-category-produce pt-8" style="--i:2">
                    <p class="eyebrow text-category-produce">{{ __('Chapter 03') }}</p>
                    <h3 class="font-display text-2xl text-brand-ink">{{ __("What we're building") }}</h3>
                    <p class="text-sm leading-relaxed text-brand-ink/75">
                        {{ __("A calm subscriber experience: know your next delivery, change your box when it's open, and see the names behind every product.") }}
                    </p>
                </x-reveal>
            </ol>
        </div>
    </section>

    {{-- Values --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="about-values-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <x-section-heading
                eyebrow="{{ __('Sourcing values') }}"
                color="fish"
                title="{{ __('Four principles guide every partnership.') }}"
                lead="{{ __('These principles guide how we choose partners and communicate with you.') }}"
                id="about-values-heading"
            />
            @php
                $values = [
                    ['eyebrow' => 'produce', 'title' => __('Seasonal first'), 'desc' => __('We follow the harvest, not the global year-round aisle.')],
                    ['eyebrow' => 'produce', 'title' => __('Regional & organic'), 'desc' => __('Prioritising producers near Berlin and certified organic where it matters.')],
                    ['eyebrow' => 'fish', 'title' => __('Transparent origins'), 'desc' => __('Every product traces to a named farm or grower you can read about.')],
                    ['eyebrow' => 'meat', 'title' => __('Fair share'), 'desc' => __('Pricing and relationships designed so more value stays with producers.')],
                ];
                $valueColors = [
                    'produce' => 'text-category-produce',
                    'fish' => 'text-category-fish',
                    'meat' => 'text-category-meat',
                ];
            @endphp
            <ul class="mt-16 grid gap-10 reveal-stagger sm:grid-cols-2 lg:grid-cols-4" data-testid="about-values-row">
                @foreach ($values as $i => $v)
                    <x-reveal as="li" variant="fade-up" class="flex flex-col gap-3" style="--i:{{ $i }}">
                        <p class="eyebrow {{ $valueColors[$v['eyebrow']] }}">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</p>
                        <h3 class="font-display text-xl text-brand-ink">{{ $v['title'] }}</h3>
                        <p class="text-sm leading-relaxed text-brand-ink/75">{{ $v['desc'] }}</p>
                    </x-reveal>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Team / partners --}}
    <section class="border-b border-brand-ink/10 bg-brand-ink/[0.03]" aria-labelledby="about-team-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-16" data-testid="about-team-place">
                <x-reveal variant="fade-up" class="lg:col-span-7">
                    <p class="eyebrow text-category-produce">{{ __('Team') }}</p>
                    <h2 id="about-team-heading" class="mt-4 font-display text-3xl font-semibold leading-tight text-brand-ink sm:text-4xl lg:text-5xl">
                        {{ __('Small team, local partners.') }}
                    </h2>
                    <p class="mt-6 max-w-2xl text-base leading-relaxed text-brand-ink/75">
                        {{ __("Erdgut is run by a small crew in Berlin working closely with growers and makers in the region. We're not a faceless marketplace — we're building tools and trust together with the people on your box labels.") }}
                    </p>
                    <p class="mt-6">
                        <a href="{{ route('public.producers') }}" class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                            {{ __('Browse producer profiles') }}
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

    {{-- FAQ --}}
    <section class="border-b border-brand-ink/10" aria-labelledby="about-faq-heading" id="faq">
        <div class="mx-auto max-w-4xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <x-section-heading
                eyebrow="{{ __('Common questions') }}"
                color="produce"
                title="{{ __('Short answers to the questions we hear most.') }}"
                lead="{{ __('A full FAQ hub may follow later.') }}"
                id="about-faq-heading"
            />
            <div class="mt-16 divide-y divide-brand-ink/15 border-y border-brand-ink/15" x-data="{ open: null }" data-testid="about-faq-accordion">
                @foreach ([
                    ['q' => __('Where do you deliver?'), 'a' => __('We focus on Berlin and nearby postcodes. Exact delivery areas evolve as we grow — contact us to check yours.')],
                    ['q' => __('Can I pause or skip weeks?'), 'a' => __('Yes. Subscribers can skip or postpone deliveries and adjust preferences from their account when those features are active for your plan.')],
                    ['q' => __('How do you choose producers?'), 'a' => __('We prioritise organic certification, regional distance, seasonality, and reliability. Every partner is listed with a profile so you can see who supplies your box.')],
                ] as $i => $item)
                    <div>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between py-6 text-left font-display text-lg text-brand-ink transition hover:text-category-produce"
                            @click="open = open === {{ $i + 1 }} ? null : {{ $i + 1 }}"
                            :aria-expanded="open === {{ $i + 1 }}"
                            aria-controls="about-faq-panel-{{ $i + 1 }}"
                            id="about-faq-trigger-{{ $i + 1 }}"
                        >
                            <span>{{ $item['q'] }}</span>
                            <span class="ml-4 text-category-produce text-2xl leading-none" x-text="open === {{ $i + 1 }} ? '−' : '+'"></span>
                        </button>
                        <div
                            id="about-faq-panel-{{ $i + 1 }}"
                            role="region"
                            aria-labelledby="about-faq-trigger-{{ $i + 1 }}"
                            x-show="open === {{ $i + 1 }}"
                            x-transition
                            class="pb-6 text-base leading-relaxed text-brand-ink/75"
                            style="display: none;"
                        >
                            {{ $item['a'] }}
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-10 text-center text-sm text-brand-ink/70">
                {{ __('More questions?') }}
                <a href="{{ route('public.contact') }}" class="font-semibold text-brand-ink underline-offset-4 hover:underline">{{ __('Write to us') }}</a>
            </p>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section aria-labelledby="about-bottom-cta-heading">
        <div class="mx-auto max-w-6xl px-4 py-24 text-center sm:px-6 sm:py-32 lg:px-8">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-category-produce">{{ __('Start your box') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="150">
                <h2 id="about-bottom-cta-heading" class="mt-6 font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl lg:text-6xl">
                    {{ __('Ready to eat more regional food?') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="300">
                <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center" data-testid="about-bottom-ctas">
                    <a
                        href="{{ route('auth.register') }}"
                        class="btn-solid"
                        data-testid="about-bottom-register"
                    >
                        {{ __('Get started') }}
                    </a>
                    <a
                        href="{{ route('public.contact') }}"
                        class="btn-ghost"
                        data-testid="about-bottom-contact"
                    >
                        {{ __('Contact us') }}
                    </a>
                </div>
            </x-reveal>
        </div>
    </section>
@endsection
