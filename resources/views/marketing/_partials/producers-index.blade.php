{{-- Public producer-directory body, shared between the marketing-shell page
     (resources/views/marketing/producers.blade.php) and the isolated shop
     directory (resources/views/shop/producers.blade.php).

     Parameters:
       $producersIndexRoute — directory/filter route   (default marketing)
       $producerShowRoute   — producer detail route     (default marketing)
       $productsIndexRoute  — products index route, used by the per-card
                              "browse products" link in isolated mode
       $isolated            — bool; when true, drop all auth/dashboard/contact
                              links so nothing escapes the shop chrome

     Required view data: $hasAnyProducers, $producers, $regions, $filters,
     $filtersActive. --}}
@php
    $producersIndexRoute = $producersIndexRoute ?? 'public.producers';
    $producerShowRoute = $producerShowRoute ?? 'public.producers.show';
    $productsIndexRoute = $productsIndexRoute ?? 'public.products.index';
    $isolated = $isolated ?? false;
@endphp

{{-- Hero: ochre chapter --}}
<section class="relative overflow-hidden bg-chapter-producers-soft/60" aria-labelledby="producers-hero-heading">
    <div class="mx-auto max-w-5xl px-4 pb-16 pt-24 text-center sm:px-6 sm:pb-20 sm:pt-32 lg:px-8">
        <x-reveal variant="fade-up">
            <x-month-label color="ink" />
        </x-reveal>
        <x-reveal variant="fade-up" delay="100">
            <p class="eyebrow mt-8 text-chapter-producers">{{ __('Chapter 06 — Producers') }}</p>
        </x-reveal>
        <x-reveal variant="fade-up" delay="200">
            <h1 id="producers-hero-heading" class="mt-6 font-display text-5xl font-semibold leading-[1.04] tracking-tight text-brand-moss sm:text-6xl lg:text-7xl">
                {{ $isolated ? __('shop.producers_hero_heading') : __('The people behind your weekly box.') }}
            </h1>
        </x-reveal>
        <x-reveal variant="fade-up" delay="300">
            <p class="mx-auto mt-10 max-w-2xl font-editorial text-lg italic leading-relaxed text-brand-ink/80 sm:text-xl">
                {{ $isolated ? __('shop.producers_hero_subheading') : __('Farms, bakeries, fisheries and a small circle of makers — each one picked by hand, each one within reach of Berlin.') }}
            </p>
        </x-reveal>
        <x-triple-star class="mt-12" />
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8" aria-labelledby="producers-directory-heading">
    <h2 id="producers-directory-heading" class="sr-only">{{ __('Producer directory') }}</h2>

    @if ($hasAnyProducers)
        <x-reveal variant="fade">
            <form
                method="get"
                action="{{ route($producersIndexRoute) }}"
                class="mb-14 flex flex-col gap-4 border-y border-brand-ink/15 py-5 sm:flex-row sm:flex-wrap sm:items-end"
                data-testid="producers-toolbar"
            >
                <div class="w-full min-w-0 flex-1 sm:max-w-xs">
                    <label for="filter-region" class="eyebrow text-brand-ink/60">{{ __('Region') }}</label>
                    <select
                        id="filter-region"
                        name="region"
                        class="mt-2 block w-full border-0 border-b border-brand-ink/25 bg-transparent px-0 py-2 text-sm text-brand-ink focus:border-chapter-producers focus:outline-none focus:ring-0"
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
                        class="mt-2 block w-full border-0 border-b border-brand-ink/25 bg-transparent px-0 py-2 text-sm text-brand-ink placeholder:text-brand-ink/40 focus:border-chapter-producers focus:outline-none focus:ring-0"
                    >
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <button type="submit" class="btn-solid">
                        {{ __('Apply') }}
                    </button>
                    @if ($filtersActive)
                        <a href="{{ route($producersIndexRoute) }}" class="btn-ghost">
                            {{ __('Clear filters') }}
                        </a>
                    @endif
                </div>
            </form>
        </x-reveal>
    @endif

    @if (! $hasAnyProducers)
        <x-empty-state
            :title="__('No producers on the page yet')"
            :description="__('The circle is small and slow to grow. New profiles arrive a few at a time — please check back soon.')"
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
                    href="{{ route($producersIndexRoute) }}"
                    class="inline-block text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                >
                    {{ __('Clear filters') }}
                </a>
            </x-slot>
        </x-empty-state>
    @else
        <div class="grid gap-12 reveal-stagger sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-10 lg:gap-y-16" data-testid="producers-grid">
            @foreach ($producers as $index => $producer)
                @php
                    $number = sprintf('%02d', $index + 1);
                    $hasImage = (bool) $producer->primaryImageUrl();
                @endphp
                <x-reveal as="article" variant="fade-up"
                    class="group flex flex-col gap-5"
                    x-data="{ expanded: false }"
                    data-testid="producer-card"
                    data-producer-id="{{ $producer->id }}"
                    style="--i:{{ $index % 6 }}"
                >
                    <a
                        href="{{ route($producerShowRoute, $producer) }}"
                        class="relative block aspect-[4/5] overflow-hidden bg-chapter-producers-soft/40"
                        data-testid="producer-card-profile-image-link"
                    >
                        @if ($hasImage)
                            <img
                                src="{{ $producer->primaryImageUrl() }}"
                                alt=""
                                class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                                loading="lazy"
                            >
                        @else
                            {{-- No photo: neutral branded placeholder, no random mock image --}}
                            <div class="flex h-full w-full items-end justify-end bg-chapter-producers-soft/60 p-4 transition duration-700 group-hover:bg-chapter-producers-soft/80">
                                <span class="font-mono text-[2rem] font-bold leading-none text-chapter-producers/20 select-none">
                                    {{ mb_strtoupper(mb_substr($producer->name, 0, 1)) }}
                                </span>
                            </div>
                        @endif
                        <span class="absolute left-0 top-0 bg-chapter-producers px-3 py-2 font-display text-xs font-bold uppercase tracking-eyebrow text-brand-cream">{{ $number }}</span>
                    </a>
                    <div class="flex flex-1 flex-col gap-3">
                        <p class="eyebrow text-chapter-producers">{{ $producer->region }}</p>
                        <h3 class="font-display text-2xl leading-snug text-brand-ink">
                            <a href="{{ route($producerShowRoute, $producer) }}" class="underline-offset-4 hover:underline" data-testid="producer-card-profile-link">{{ $producer->name }}</a>
                        </h3>
                        @php
                            $desc = $producer->description ?? '';
                            $descLong = mb_strlen($desc) > 180;
                        @endphp
                        <div class="flex-1 font-editorial text-base leading-relaxed text-brand-ink/80">
                            @if ($desc === '')
                                <p class="italic text-brand-ink/50">{{ __('No description yet.') }}</p>
                            @else
                                <p :class="expanded ? '' : 'line-clamp-4'">{{ $desc }}</p>
                                @if ($descLong)
                                    <button
                                        type="button"
                                        class="mt-3 text-xs font-semibold uppercase tracking-eyebrow text-chapter-producers underline-offset-4 hover:underline"
                                        @click="expanded = !expanded"
                                        :aria-expanded="expanded"
                                        x-text="expanded ? '{{ __('Show less') }}' : '{{ __('Read more') }}'"
                                    ></button>
                                @endif
                            @endif
                        </div>
                        <div class="pt-4">
                            @if ($isolated)
                                {{-- Shop chrome: stay inside the shop, link to the shop catalogue. --}}
                                <a
                                    href="{{ route($productsIndexRoute) }}"
                                    class="inline-flex items-center gap-2 text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline"
                                    data-testid="producer-card-products-link"
                                >
                                    {{ __('Browse products') }}
                                    <span aria-hidden="true">&rarr;</span>
                                </a>
                            @else
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
                            @endif
                        </div>
                    </div>
                </x-reveal>
            @endforeach
        </div>
    @endif
</section>

{{-- Closing invitation --}}
<section class="bg-brand-moss text-brand-cream" aria-labelledby="producers-cta-heading">
    <div class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6 sm:py-28 lg:px-8">
        <x-reveal variant="fade-up">
            <p class="eyebrow text-brand-cream/70">{{ __('Grow with us') }}</p>
        </x-reveal>
        <x-reveal variant="fade-up" delay="150">
            <h2 id="producers-cta-heading" class="mt-6 font-display text-3xl font-semibold leading-tight text-brand-cream sm:text-4xl lg:text-5xl">
                {{ $isolated ? __('shop.producers_cta_heading') : __('A farm, a bakery, a fishery?') }}
            </h2>
        </x-reveal>
        <x-reveal variant="fade-up" delay="250">
            <p class="mx-auto mt-8 max-w-2xl font-editorial text-lg italic leading-relaxed text-brand-cream/85">
                {{ $isolated ? __('shop.producers_cta_body') : __('If you work the land, a oven or a boat within reach of Berlin, we\'d love to hear from you — the circle grows slowly and by invitation, but it does keep growing.') }}
            </p>
        </x-reveal>
        <x-reveal variant="fade-up" delay="350">
            <div class="mt-10 flex justify-center">
                <a
                    href="{{ $isolated ? 'mailto:kontakt@erd-gut.de' : route('public.contact') }}"
                    class="inline-flex items-center justify-center border border-brand-cream px-6 py-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream transition hover:bg-brand-cream hover:text-brand-moss"
                    data-testid="producers-contact-cta"
                >
                    {{ __('Write to us') }}
                </a>
            </div>
        </x-reveal>
        <x-triple-star color="cream" class="mt-14" />
    </div>
</section>
