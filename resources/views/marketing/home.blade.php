@extends('layouts.marketing')

@section('content')
    {{-- Hero: single primary CTA per auth state (ERD-169) --}}
    <section class="bg-gradient-to-b from-emerald-50 to-white" aria-labelledby="home-hero-heading">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
            <div class="mx-auto max-w-3xl text-center sm:text-left">
                <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">
                    Farm-to-consumer in Berlin
                </p>
                <h1 id="home-hero-heading" class="mt-6 text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Fresh weekly boxes from local farms, delivered transparently.
                </h1>
                <p class="mt-6 text-lg leading-8 text-gray-600">
                    Erdgut connects Berlin households with small-scale producers through a simple subscription.
                    See where your food comes from and support fair pricing for farmers.
                </p>
                <div class="mt-10 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center" data-testid="home-hero-ctas">
                    @auth
                        <a
                            href="{{ route('app.dashboard') }}"
                            class="inline-flex justify-center rounded-md bg-emerald-700 px-6 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            data-testid="home-primary-cta-authenticated"
                        >
                            Go to dashboard
                        </a>
                        <a
                            href="{{ route('app.subscriptions.create') }}"
                            class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-6 py-3 text-center text-sm font-semibold text-gray-800 transition hover:border-emerald-600 hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            data-testid="home-secondary-cta-subscription"
                        >
                            Start subscription
                        </a>
                    @else
                        <a
                            href="{{ route('auth.register') }}"
                            class="inline-flex justify-center rounded-md bg-emerald-700 px-6 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            data-testid="home-primary-cta-guest"
                        >
                            Get started
                        </a>
                        <a
                            href="{{ route('auth.login') }}"
                            class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-6 py-3 text-center text-sm font-semibold text-gray-800 transition hover:border-emerald-600 hover:text-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                            data-testid="home-secondary-cta-login"
                        >
                            Log in
                        </a>
                    @endauth
                </div>
                <p class="mt-4 text-sm text-gray-500">
                    <a href="{{ route('public.about') }}" class="font-medium text-emerald-800 underline-offset-4 hover:underline">Learn how Erdgut works</a>
                </p>
            </div>
        </div>
    </section>

    {{-- Trust signals --}}
    <section class="border-y border-emerald-100 bg-white" aria-labelledby="home-trust-heading">
        <h2 id="home-trust-heading" class="sr-only">Why customers trust Erdgut</h2>
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4" data-testid="home-trust-row">
                <li class="flex gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Regional producers</p>
                        <p class="mt-1 text-sm text-gray-600">Food from farms and growers near Berlin.</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Transparent origins</p>
                        <p class="mt-1 text-sm text-gray-600">Every product is tied to a named producer.</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Flexible delivery</p>
                        <p class="mt-1 text-sm text-gray-600">Skip, postpone, or adjust when life changes.</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Secure payment</p>
                        <p class="mt-1 text-sm text-gray-600">Card handling via SumUp—no card data stored on our servers.</p>
                    </div>
                </li>
            </ul>
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="home-steps-heading">
        <div class="mx-auto max-w-2xl text-center">
            <h2 id="home-steps-heading" class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                How it works
            </h2>
            <p class="mt-4 text-base text-gray-600">
                From signup to your doorstep in four steps.
            </p>
        </div>
        <ol class="mx-auto mt-12 grid max-w-5xl gap-8 md:grid-cols-2 lg:grid-cols-4" data-testid="home-how-it-works">
            <li class="relative rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white" aria-hidden="true">1</span>
                <h3 class="mt-4 text-lg font-semibold text-gray-900">Create your account</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">Register and verify your email to get started.</p>
            </li>
            <li class="relative rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white" aria-hidden="true">2</span>
                <h3 class="mt-4 text-lg font-semibold text-gray-900">Choose your plan</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">Pick box size and set preferences and dietary notes.</p>
            </li>
            <li class="relative rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white" aria-hidden="true">3</span>
                <h3 class="mt-4 text-lg font-semibold text-gray-900">We build your week</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">Your box is composed from seasonal, regional produce.</p>
            </li>
            <li class="relative rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white" aria-hidden="true">4</span>
                <h3 class="mt-4 text-lg font-semibold text-gray-900">You stay in control</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">Change delivery, skip a week, or add extras before the deadline.</p>
            </li>
        </ol>
    </section>

    {{-- Social proof (placeholder pattern until real testimonials) --}}
    <section class="bg-emerald-900 text-white" aria-labelledby="home-social-heading">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center" data-testid="home-social-proof">
                <h2 id="home-social-heading" class="text-2xl font-bold tracking-tight sm:text-3xl">
                    Join Berlin households supporting local food
                </h2>
                <p class="mt-4 text-base text-emerald-100">
                    We are onboarding early supporters—real stories and numbers will appear here as the community grows.
                </p>
                <p class="mt-2 text-xs uppercase tracking-wide text-emerald-200/80">
                    TODO: replace with testimonials or impact stats when available
                </p>
            </div>
        </div>
    </section>

    {{-- Deeper value props (complements trust row) --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="home-values-heading">
        <h2 id="home-values-heading" class="sr-only">What you get with Erdgut</h2>
        <div class="grid gap-6 md:grid-cols-3" data-testid="home-value-cards">
            <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Know every producer</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    Products link back to people and places—not anonymous supply chains—so you can trust what lands in your kitchen.
                </p>
            </article>
            <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Built around your week</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    See deadlines, tweak your box when it is editable, and align delivery with your routine.
                </p>
            </article>
            <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Fair share for farmers</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    We focus on direct relationships and clear value so more of what you pay supports the people who grow your food.
                </p>
            </article>
        </div>
    </section>

    {{-- Bottom CTA (mobile long-scroll) --}}
    <section class="border-t border-gray-100 bg-gray-50" aria-labelledby="home-bottom-cta-heading">
        <div class="mx-auto max-w-7xl px-4 py-12 text-center sm:px-6 lg:px-8">
            <h2 id="home-bottom-cta-heading" class="text-xl font-bold text-gray-900 sm:text-2xl">
                Ready to eat better and support Berlin&apos;s producers?
            </h2>
            <div class="mt-6 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                @auth
                    <a
                        href="{{ route('app.dashboard') }}"
                        class="inline-flex justify-center rounded-md bg-emerald-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800"
                        data-testid="home-bottom-primary-authenticated"
                    >
                        Go to dashboard
                    </a>
                @else
                    <a
                        href="{{ route('auth.register') }}"
                        class="inline-flex justify-center rounded-md bg-emerald-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800"
                        data-testid="home-bottom-primary-guest"
                    >
                        Get started
                    </a>
                    <a
                        href="{{ route('auth.login') }}"
                        class="inline-flex justify-center text-sm font-semibold text-emerald-800 underline-offset-4 hover:underline"
                    >
                        Already have an account? Log in
                    </a>
                @endauth
            </div>
        </div>
    </section>
@endsection
