{{--
    ERD-170: Optional hero photo — add public/images/about-hero.jpg (e.g. 1200×800) to show a real image on md+ screens.
    Without the file, the decorative panel (gradient + illustration) is shown instead.
--}}
@extends('layouts.marketing')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-white" aria-labelledby="about-hero-heading">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-12" data-testid="about-hero">
                <div>
                    <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">
                        About Erdgut
                    </p>
                    <h1 id="about-hero-heading" class="mt-6 text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                        A transparent bridge between Berlin households and regional organic food.
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        We make weekly local eating simple: one subscription, clear origins, and fair relationships with the people who grow your food.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap" data-testid="about-hero-ctas">
                        <a
                            href="{{ route('auth.register') }}"
                            class="inline-flex justify-center rounded-md bg-emerald-700 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            data-testid="about-cta-register"
                        >
                            Get started
                        </a>
                        <a
                            href="{{ route('public.contact') }}"
                            class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-800 transition hover:border-emerald-600 hover:text-emerald-800"
                            data-testid="about-cta-contact"
                        >
                            Contact us
                        </a>
                        <a
                            href="{{ route('public.producers') }}"
                            class="inline-flex justify-center text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline sm:items-center"
                            data-testid="about-cta-producers"
                        >
                            Meet our producers
                        </a>
                    </div>
                </div>
                <div class="relative min-h-[220px] overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-200/80 via-emerald-100 to-white shadow-sm sm:min-h-[280px]">
                    @if (file_exists(public_path('images/about-hero.jpg')))
                        <img
                            src="{{ asset('images/about-hero.jpg') }}"
                            alt=""
                            class="h-full w-full object-cover"
                            width="1200"
                            height="800"
                            decoding="async"
                        >
                        <span class="sr-only">Regional produce and landscape — decorative hero image</span>
                    @else
                        <div class="flex h-full min-h-[220px] flex-col items-center justify-center p-8 text-emerald-900/80 sm:min-h-[280px]" aria-hidden="true">
                            <svg class="h-32 w-32 text-emerald-700/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="mt-4 max-w-xs text-center text-sm text-emerald-900/70">
                                Placeholder visual — add <code class="rounded bg-white/60 px-1 text-xs">public/images/about-hero.jpg</code> for photography.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Story arc --}}
    <section class="border-y border-gray-100 bg-white" aria-labelledby="about-story-heading">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <h2 id="about-story-heading" class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                Our story
            </h2>
            <p class="mt-4 max-w-3xl text-base text-gray-600">
                Erdgut grew from a simple frustration: great regional food exists around Berlin, but busy households rarely see who grew it or how value flows back to farms.
            </p>
            <ol class="mt-12 grid gap-8 md:grid-cols-3" data-testid="about-story-arc">
                <li class="relative border-l-4 border-emerald-600 pl-6">
                    <span class="absolute -left-2 top-0 flex h-4 w-4 rounded-full bg-emerald-600 ring-4 ring-white" aria-hidden="true"></span>
                    <h3 class="text-lg font-semibold text-gray-900">Why Berlin</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        The city&apos;s appetite for organic and regional food keeps growing. We focus on connections that stay short, seasonal, and honest.
                    </p>
                </li>
                <li class="relative border-l-4 border-emerald-600 pl-6">
                    <span class="absolute -left-2 top-0 flex h-4 w-4 rounded-full bg-emerald-600 ring-4 ring-white" aria-hidden="true"></span>
                    <h3 class="text-lg font-semibold text-gray-900">Why a weekly box</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        A predictable rhythm helps producers plan harvests and lets you adjust one transparent order instead of juggling many small shops.
                    </p>
                </li>
                <li class="relative border-l-4 border-emerald-600 pl-6">
                    <span class="absolute -left-2 top-0 flex h-4 w-4 rounded-full bg-emerald-600 ring-4 ring-white" aria-hidden="true"></span>
                    <h3 class="text-lg font-semibold text-gray-900">What we&apos;re building</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        A calm subscriber experience: know your next delivery, change your box when it&apos;s open, and see the names behind every product.
                    </p>
                </li>
            </ol>
        </div>
    </section>

    {{-- Values / sourcing --}}
    <section class="bg-gray-50" aria-labelledby="about-values-heading">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <h2 id="about-values-heading" class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                Sourcing values
            </h2>
            <p class="mt-4 max-w-3xl text-base text-gray-600">
                These principles guide how we choose partners and communicate with you.
            </p>
            <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4" data-testid="about-values-row">
                <li class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Seasonal first</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">We follow the harvest, not the global year-round aisle.</p>
                </li>
                <li class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Regional &amp; organic</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">Prioritising producers near Berlin and certified organic where it matters.</p>
                </li>
                <li class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Transparent origins</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">Every product traces to a named farm or grower you can read about.</p>
                </li>
                <li class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900">Fair share</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">Pricing and relationships designed so more value stays with producers.</p>
                </li>
            </ul>
        </div>
    </section>

    {{-- Team / partners --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="about-team-heading">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-8 sm:p-10" data-testid="about-team-place">
            <h2 id="about-team-heading" class="text-xl font-semibold text-emerald-950 sm:text-2xl">
                Small team, local partners
            </h2>
            <p class="mt-4 text-sm leading-6 text-emerald-900/90 sm:text-base">
                Erdgut is run by a small crew in Berlin working closely with growers and makers in the region.
                We&apos;re not a faceless marketplace — we&apos;re building tools and trust together with the people on your box labels.
            </p>
            <p class="mt-4 text-sm leading-6 text-emerald-900/90">
                <a href="{{ route('public.producers') }}" class="font-semibold text-emerald-900 underline-offset-4 hover:underline">Browse producer profiles</a>
                to see who we work with today.
            </p>
        </div>
    </section>

    {{-- FAQ mini (Alpine accordion) --}}
    <section class="border-t border-gray-100 bg-white" aria-labelledby="about-faq-heading" id="faq">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <h2 id="about-faq-heading" class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                Common questions
            </h2>
            <p class="mt-4 text-base text-gray-600">
                Quick answers — a full FAQ hub may follow later.
            </p>
            <div class="mt-8 space-y-2" x-data="{ open: null }" data-testid="about-faq-accordion">
                <div class="rounded-lg border border-gray-200 bg-white">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-4 text-left text-sm font-semibold text-gray-900"
                        @click="open = open === 1 ? null : 1"
                        :aria-expanded="open === 1"
                        aria-controls="about-faq-panel-1"
                        id="about-faq-trigger-1"
                    >
                        Where do you deliver?
                        <span class="text-emerald-700" x-text="open === 1 ? '−' : '+'"></span>
                    </button>
                    <div
                        id="about-faq-panel-1"
                        role="region"
                        aria-labelledby="about-faq-trigger-1"
                        x-show="open === 1"
                        x-transition
                        class="border-t border-gray-100 px-4 py-3 text-sm leading-6 text-gray-600"
                    >
                        We focus on Berlin and nearby postcodes. Exact delivery areas evolve as we grow — <a href="{{ route('public.contact') }}" class="font-medium text-emerald-800 underline-offset-2 hover:underline">contact us</a> to check yours.
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-4 text-left text-sm font-semibold text-gray-900"
                        @click="open = open === 2 ? null : 2"
                        :aria-expanded="open === 2"
                        aria-controls="about-faq-panel-2"
                        id="about-faq-trigger-2"
                    >
                        Can I pause or skip weeks?
                        <span class="text-emerald-700" x-text="open === 2 ? '−' : '+'"></span>
                    </button>
                    <div
                        id="about-faq-panel-2"
                        role="region"
                        aria-labelledby="about-faq-trigger-2"
                        x-show="open === 2"
                        x-transition
                        class="border-t border-gray-100 px-4 py-3 text-sm leading-6 text-gray-600"
                    >
                        Yes. Subscribers can skip or postpone deliveries and adjust preferences from their account when those features are active for your plan.
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-4 text-left text-sm font-semibold text-gray-900"
                        @click="open = open === 3 ? null : 3"
                        :aria-expanded="open === 3"
                        aria-controls="about-faq-panel-3"
                        id="about-faq-trigger-3"
                    >
                        How do you choose producers?
                        <span class="text-emerald-700" x-text="open === 3 ? '−' : '+'"></span>
                    </button>
                    <div
                        id="about-faq-panel-3"
                        role="region"
                        aria-labelledby="about-faq-trigger-3"
                        x-show="open === 3"
                        x-transition
                        class="border-t border-gray-100 px-4 py-3 text-sm leading-6 text-gray-600"
                    >
                        We prioritise organic certification, regional distance, seasonality, and reliability. Every partner is listed with a profile so you can see who supplies your box.
                    </div>
                </div>
            </div>
            <p class="mt-8 text-center text-sm text-gray-600">
                More questions?
                <a href="{{ route('public.contact') }}" class="font-semibold text-emerald-800 underline-offset-4 hover:underline">Write to us</a>
            </p>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="border-t border-gray-100 bg-gray-50" aria-labelledby="about-bottom-cta-heading">
        <div class="mx-auto max-w-7xl px-4 py-12 text-center sm:px-6 lg:px-8">
            <h2 id="about-bottom-cta-heading" class="text-xl font-bold text-gray-900 sm:text-2xl">
                Ready to eat more regional food?
            </h2>
            <div class="mt-6 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center" data-testid="about-bottom-ctas">
                <a
                    href="{{ route('auth.register') }}"
                    class="inline-flex justify-center rounded-md bg-emerald-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800"
                    data-testid="about-bottom-register"
                >
                    Get started
                </a>
                <a
                    href="{{ route('public.contact') }}"
                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-800 transition hover:border-emerald-600"
                    data-testid="about-bottom-contact"
                >
                    Contact us
                </a>
            </div>
        </div>
    </section>
@endsection
