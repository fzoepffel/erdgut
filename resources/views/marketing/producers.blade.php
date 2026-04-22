@extends('layouts.marketing')

@section('content')
    <section class="border-b border-brand-ink/10" aria-labelledby="producers-hero-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="max-w-4xl">
                <x-reveal variant="fade-up">
                    <p class="eyebrow text-chapter-produce">{{ __('Local producer network') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="100">
                    <h1 id="producers-hero-heading" class="mt-5 font-display text-5xl font-semibold leading-[1.05] tracking-tight text-brand-ink sm:text-6xl lg:text-7xl">
                        {{ __('Meet the producers behind your weekly Erdgut box.') }}
                    </h1>
                </x-reveal>
                <x-reveal variant="fade-up" delay="200">
                    <p class="mt-8 max-w-2xl text-lg leading-relaxed text-brand-ink/75">
                        {{ __('We partner with farms and small food makers focused on quality, transparency, and fair pricing. Filter by region or search by name—every profile shows where they work and what they stand for.') }}
                    </p>
                </x-reveal>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8" aria-labelledby="producers-directory-heading">
        <h2 id="producers-directory-heading" class="sr-only">{{ __('Producer directory') }}</h2>

        @if ($hasAnyProducers)
            <x-reveal variant="fade">
                <form
                    method="get"
                    action="{{ route('public.producers') }}"
                    class="mb-14 flex flex-col gap-4 border-y border-brand-ink/15 py-5 sm:flex-row sm:flex-wrap sm:items-end"
                    data-testid="producers-toolbar"
                >
                    <div class="w-full min-w-0 flex-1 sm:max-w-xs">
                        <label for="filter-region" class="eyebrow text-brand-ink/60">{{ __('Region') }}</label>
                        <select
                            id="filter-region"
                            name="region"
                            class="mt-2 block w-full border-0 border-b border-brand-ink/25 bg-transparent px-0 py-2 text-sm text-brand-ink focus:border-chapter-produce focus:outline-none focus:ring-0"
                        >
                            <option value="">{{ __('All regions') }}</option>
                            @foreach ($regions as $regionOption)
                                <option value="{{ $regionOption }}" @selected($filters['region'] === $regionOption)>{{ $regionOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full min-w-0 flex-1 sm:max-w-md">
                        <label for="filter-q" class="eyebrow text-brand-ink/60">{{ __('Search') }}</label>
                        <input
                            type="search"
                            id="filter-q"
                            name="q"
                            value="{{ $filters['q'] }}"
                            maxlength="100"
                            placeholder="{{ __('Producer name or keywords…') }}"
                            class="mt-2 block w-full border-0 border-b border-brand-ink/25 bg-transparent px-0 py-2 text-sm text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-produce focus:outline-none focus:ring-0"
                        >
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <button type="submit" class="btn-solid">
                            {{ __('Apply') }}
                        </button>
                        @if ($filtersActive)
                            <a href="{{ route('public.producers') }}" class="btn-ghost">
                                {{ __('Clear filters') }}
                            </a>
                        @endif
                    </div>
                </form>
            </x-reveal>
        @endif

        @if (! $hasAnyProducers)
            <x-empty-state
                :title="__('No producers available yet')"
                :description="__('We are currently preparing producer profiles. Please check back soon.')"
                variant="card"
            />
        @elseif ($producers->isEmpty())
            <x-empty-state
                variant="card"
                :title="__('No producers match your filters')"
                :description="__('Try another region or search term, or clear filters to see everyone.')"
            >
                <x-slot name="action">
                    <a
                        href="{{ route('public.producers') }}"
                        class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                    >
                        {{ __('Clear filters') }}
                    </a>
                </x-slot>
            </x-empty-state>
        @else
            @php
                $fallbackProducerImages = [
                    asset('images/mock/producers/elena.jpg'),
                    asset('images/mock/producers/marcus.jpg'),
                    asset('images/mock/producers/sophia.jpg'),
                    asset('images/mock/producers/thomas.jpg'),
                    asset('images/mock/producers/anna.jpg'),
                    asset('images/mock/producers/jakob.jpg'),
                ];
            @endphp
            <div class="grid gap-12 reveal-stagger sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-10 lg:gap-y-16" data-testid="producers-grid">
                @foreach ($producers as $index => $producer)
                    @php
                        $fallback = $fallbackProducerImages[$index % count($fallbackProducerImages)];
                    @endphp
                    <x-reveal as="article" variant="fade-up"
                        class="group flex flex-col gap-5"
                        x-data="{ expanded: false }"
                        data-testid="producer-card"
                        data-producer-id="{{ $producer->id }}"
                        style="--i:{{ $index % 6 }}"
                    >
                        <div class="relative aspect-[4/5] overflow-hidden bg-brand-ink/5">
                            <img
                                src="{{ $producer->primaryImageUrl() ?? $fallback }}"
                                alt=""
                                class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                                loading="lazy"
                            >
                        </div>
                        <div class="flex flex-1 flex-col gap-3">
                            <p class="eyebrow text-chapter-produce">{{ $producer->region }}</p>
                            <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ $producer->name }}</h3>
                            @php
                                $desc = $producer->description ?? '';
                                $descLong = mb_strlen($desc) > 180;
                            @endphp
                            <div class="flex-1 text-sm leading-relaxed text-brand-ink/75">
                                @if ($desc === '')
                                    <p class="text-brand-ink/50 italic">{{ __('No description yet.') }}</p>
                                @else
                                    <p :class="expanded ? '' : 'line-clamp-4'">{{ $desc }}</p>
                                    @if ($descLong)
                                        <button
                                            type="button"
                                            class="mt-3 text-xs font-semibold uppercase tracking-eyebrow text-chapter-produce underline-offset-4 hover:underline"
                                            @click="expanded = !expanded"
                                            :aria-expanded="expanded"
                                            x-text="expanded ? '{{ __('Show less') }}' : '{{ __('Read more') }}'"
                                        ></button>
                                    @endif
                                @endif
                            </div>
                            <div class="pt-4">
                                @auth
                                    <a
                                        href="{{ route('app.products.index') }}"
                                        class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                        data-testid="producer-card-products-link"
                                    >
                                        {{ __('Browse products') }}
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                @else
                                    <a
                                        href="{{ route('auth.register') }}"
                                        class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                        data-testid="producer-card-signup-link"
                                    >
                                        {{ __('Sign up to browse products') }}
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                    <p class="mt-2 text-xs text-brand-ink/60">
                                        {{ __('Already a customer?') }}
                                        <a href="{{ route('auth.login') }}" class="font-semibold text-brand-ink underline-offset-2 hover:underline">{{ __('Log in') }}</a>
                                    </p>
                                @endauth
                            </div>
                        </div>
                    </x-reveal>
                @endforeach
            </div>
        @endif
    </section>
@endsection
