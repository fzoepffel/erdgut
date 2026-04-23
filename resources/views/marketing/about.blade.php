@extends('layouts.marketing')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-brand-paper" aria-labelledby="about-hero-heading">
        <div class="mx-auto max-w-5xl px-4 pb-16 pt-24 text-center sm:px-6 sm:pb-20 sm:pt-32 lg:px-8">
            <x-reveal variant="fade-up">
                <x-month-label color="ink" />
            </x-reveal>
            <x-reveal variant="fade-up" delay="100">
                <p class="eyebrow mt-8 text-chapter-almanach">{{ __('The Erdgut almanac') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="200">
                <h1 id="about-hero-heading" class="mt-6 font-display text-5xl font-semibold leading-[1.04] tracking-tight text-brand-moss sm:text-6xl lg:text-7xl">
                    {{ __('A short book about one weekly box from Berlin.') }}
                </h1>
            </x-reveal>
            <x-reveal variant="fade-up" delay="300">
                <p class="mx-auto mt-10 max-w-2xl font-editorial text-lg italic leading-relaxed text-brand-ink/80 sm:text-xl">
                    {{ __('We cook, shop and eat with the seasons — and we want our neighbours to do the same without a second thought.') }}
                </p>
            </x-reveal>
            <x-triple-star class="mt-12" />
        </div>
    </section>

    {{-- Chapter 1: the manifesto / founder letter --}}
    <x-chapter-divider
        chapter="almanach"
        eyebrow="{{ __('Chapter 01') }}"
        title="{{ __('Why we started Erdgut.') }}"
        subtitle="{{ __('A letter from Lucille, Arthur and Milan.') }}"
        :showMark="false"
    />

    <section class="border-b border-brand-ink/10" aria-labelledby="about-letter-heading">
        <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8" data-testid="about-founder-letter">
            <h2 id="about-letter-heading" class="sr-only">{{ __('Founder letter') }}</h2>
            <x-reveal variant="fade-up">
                <p class="drop-cap font-editorial text-lg leading-relaxed text-brand-ink/90 sm:text-xl" style="--chapter-ink: theme('colors.chapter.almanach.DEFAULT');">
                    {{ __('We grew up around tables where everybody at the meal knew whose hands had grown what was on it — the baker two villages over, the cousin with the sheep, the neighbour with more cucumbers than she could eat. In Berlin, that chain is mostly broken. Most of us shop well, but we shop blind.') }}
                </p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="100">
                <p class="mt-6 font-editorial text-lg leading-relaxed text-brand-ink/85">
                    {{ __('Erdgut is our attempt to mend that, in the smallest, most domestic way possible: one box a week, from a circle of farms, bakeries and fisheries we picked one by one. No marketplace, no anonymous catalogue. A short list of people, and the food they make.') }}
                </p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="200">
                <p class="mt-6 font-editorial text-lg leading-relaxed text-brand-ink/85">
                    {{ __('We are a small crew — three friends who like to cook, mostly — and we are building Erdgut slowly, so that the people behind the box keep the lead role and the technology stays useful and invisible.') }}
                </p>
            </x-reveal>
            <x-triple-star class="mt-14" />
            <x-reveal variant="fade-up" delay="300">
                <div class="mt-10 flex justify-center">
                    <x-founder-signature size="lg" />
                </div>
            </x-reveal>
        </div>
    </section>

    {{-- Chapter 2: how a box is assembled --}}
    <x-chapter-divider
        chapter="produce"
        eyebrow="{{ __('Chapter 02') }}"
        title="{{ __('How a box is put together.') }}"
        subtitle="{{ __('From Brandenburg fields to your kitchen, in four honest steps.') }}"
    />

    <section class="border-b border-brand-ink/10" aria-labelledby="about-how-heading">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <h2 id="about-how-heading" class="sr-only">{{ __('How a box is assembled') }}</h2>
            @php
                $steps = [
                    ['chapter' => 'produce', 'title' => __('We follow the harvest.'), 'copy' => __('Every Monday we gather a short list of what is ripe, what is rested, what has been caught or baked that week. Nothing travels more than a few hundred kilometres.')],
                    ['chapter' => 'eggdairy', 'title' => __('We compose, not configure.'), 'copy' => __('Boxes are composed by us, not by an algorithm. We balance colour, weight and variety, then leave room for one or two small surprises from new producers.')],
                    ['chapter' => 'bread', 'title' => __('We pack, we leave a note.'), 'copy' => __('Every box travels with a one-page letter: who made what, what to cook first, and one recipe signed by somebody in the circle.')],
                    ['chapter' => 'fish', 'title' => __('We deliver and we listen.'), 'copy' => __('Deliveries happen midweek across Berlin. You skip, change or resume from a single screen — and we read every reply that comes back.')],
                ];
                $chapterBorder = [
                    'produce' => 'border-chapter-produce',
                    'meat' => 'border-chapter-meat',
                    'fish' => 'border-chapter-fish',
                    'eggdairy' => 'border-chapter-eggdairy',
                    'bread' => 'border-chapter-bread',
                    'producers' => 'border-chapter-producers',
                    'almanach' => 'border-chapter-almanach',
                ];
                $chapterText = [
                    'produce' => 'text-chapter-produce',
                    'meat' => 'text-chapter-meat',
                    'fish' => 'text-chapter-fish',
                    'eggdairy' => 'text-chapter-eggdairy',
                    'bread' => 'text-chapter-bread',
                    'producers' => 'text-chapter-producers',
                    'almanach' => 'text-chapter-almanach',
                ];
            @endphp
            <ol class="grid gap-10 reveal-stagger md:grid-cols-2 lg:grid-cols-4" data-testid="about-how-steps">
                @foreach ($steps as $i => $step)
                    <x-reveal as="li" variant="fade-up" class="flex flex-col gap-5 border-t-2 {{ $chapterBorder[$step['chapter']] }} pt-6" style="--i:{{ $i }}">
                        <div class="flex items-baseline justify-between">
                            <p class="eyebrow {{ $chapterText[$step['chapter']] }}">{{ sprintf('%02d', $i + 1) }}</p>
                            <x-chapter-mark :chapter="$step['chapter']" size="xs" class="opacity-80" />
                        </div>
                        <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ $step['title'] }}</h3>
                        <p class="font-editorial text-base leading-relaxed text-brand-ink/80">{{ $step['copy'] }}</p>
                    </x-reveal>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Chapter 3: principles --}}
    <x-chapter-divider
        chapter="meat"
        eyebrow="{{ __('Chapter 03') }}"
        title="{{ __('Four principles we don\'t trade away.') }}"
        subtitle="{{ __('The rules of the house.') }}"
    />

    <section class="border-b border-brand-ink/10 bg-brand-paper" aria-labelledby="about-values-heading">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <h2 id="about-values-heading" class="sr-only">{{ __('Our principles') }}</h2>
            @php
                $values = [
                    ['chapter' => 'produce', 'title' => __('Seasonal first.'), 'copy' => __('We follow the harvest, not the global year-round aisle. In March you will not find strawberries in your box — and in July you will barely see anything else.')],
                    ['chapter' => 'fish', 'title' => __('Short circle, clear origins.'), 'copy' => __('Every product traces to a named farm, bakery or fishery within reach of Berlin. You can read their story before, during and after it lands in your kitchen.')],
                    ['chapter' => 'meat', 'title' => __('Fair share for producers.'), 'copy' => __('Pricing and relationships are designed so a larger share of what you pay stays with the people who actually grew or made your food.')],
                    ['chapter' => 'eggdairy', 'title' => __('Calm, not constant.'), 'copy' => __('One box a week, one deadline, one delivery window. No infinite scroll, no flash sales, no notifications begging for attention.')],
                ];
            @endphp
            @php
                $vBorder = [
                    'produce' => 'border-chapter-produce',
                    'meat' => 'border-chapter-meat',
                    'fish' => 'border-chapter-fish',
                    'eggdairy' => 'border-chapter-eggdairy',
                    'bread' => 'border-chapter-bread',
                    'producers' => 'border-chapter-producers',
                    'almanach' => 'border-chapter-almanach',
                ];
                $vText = [
                    'produce' => 'text-chapter-produce',
                    'meat' => 'text-chapter-meat',
                    'fish' => 'text-chapter-fish',
                    'eggdairy' => 'text-chapter-eggdairy',
                    'bread' => 'text-chapter-bread',
                    'producers' => 'text-chapter-producers',
                    'almanach' => 'text-chapter-almanach',
                ];
            @endphp
            <ul class="grid gap-10 reveal-stagger sm:grid-cols-2" data-testid="about-values-row">
                @foreach ($values as $i => $v)
                    <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4 border-t {{ $vBorder[$v['chapter']] }} pt-6" style="--i:{{ $i }}">
                        <div class="flex items-center justify-between">
                            <p class="eyebrow {{ $vText[$v['chapter']] }}">{{ sprintf('%02d', $i + 1) }}</p>
                            <x-chapter-mark :chapter="$v['chapter']" size="xs" class="opacity-80" />
                        </div>
                        <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ $v['title'] }}</h3>
                        <p class="font-editorial text-base leading-relaxed text-brand-ink/80">{{ $v['copy'] }}</p>
                    </x-reveal>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Chapter 4: producers invitation --}}
    <section class="bg-brand-moss text-brand-cream" aria-labelledby="about-producers-heading">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-16" data-testid="about-producers-pitch">
                <x-reveal variant="fade-up" class="lg:col-span-7">
                    <p class="eyebrow text-brand-cream/70">{{ __('Chapter 04') }}</p>
                    <h2 id="about-producers-heading" class="mt-4 font-display text-3xl font-semibold leading-tight text-brand-cream sm:text-4xl lg:text-5xl">
                        {{ __('A small circle of hands.') }}
                    </h2>
                    <p class="mt-8 font-editorial text-lg italic leading-relaxed text-brand-cream/85 sm:text-xl">
                        {{ __('We grow Erdgut the same way we cook: slowly, with the people we already trust. Every producer joins the circle by invitation, not by listing.') }}
                    </p>
                    <p class="mt-8">
                        <a href="{{ route('public.producers') }}" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-eyebrow text-brand-cream underline-offset-4 hover:underline" data-testid="about-producers-link">
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

    {{-- Chapter 5: FAQ --}}
    <x-chapter-divider
        chapter="producers"
        eyebrow="{{ __('Chapter 05') }}"
        title="{{ __('Short answers, out loud.') }}"
        subtitle="{{ __('The questions we hear most, answered without footnotes.') }}"
    />

    <section class="border-b border-brand-ink/10" aria-labelledby="about-faq-heading" id="faq">
        <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <h2 id="about-faq-heading" class="sr-only">{{ __('Frequently asked questions') }}</h2>
            <div class="divide-y divide-brand-ink/15 border-y border-brand-ink/15" x-data="{ open: null }" data-testid="about-faq-accordion">
                @foreach ([
                    ['q' => __('Where do you deliver?'), 'a' => __('We focus on Berlin and nearby postcodes. Exact delivery areas evolve as we grow — write to us to check yours.')],
                    ['q' => __('Can I pause or skip weeks?'), 'a' => __('Yes. Subscribers can skip, postpone or adjust preferences from their account when those features are open for the week.')],
                    ['q' => __('How do you choose producers?'), 'a' => __('By hand. Every farm, bakery or fishery in the circle joins by invitation. We favour organic certification, short distances, seasonality and honest people.')],
                    ['q' => __('What if I don\'t like something in my box?'), 'a' => __('Reply to the weekly note. We read every message and adjust future boxes accordingly — no anonymous tickets, no support queue.')],
                ] as $i => $item)
                    <div>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between py-6 text-left font-display text-lg text-brand-ink transition hover:text-chapter-producers"
                            @click="open = open === {{ $i + 1 }} ? null : {{ $i + 1 }}"
                            :aria-expanded="open === {{ $i + 1 }}"
                            aria-controls="about-faq-panel-{{ $i + 1 }}"
                            id="about-faq-trigger-{{ $i + 1 }}"
                        >
                            <span>{{ $item['q'] }}</span>
                            <span class="ml-4 text-2xl leading-none text-chapter-producers" x-text="open === {{ $i + 1 }} ? '−' : '+'"></span>
                        </button>
                        <div
                            id="about-faq-panel-{{ $i + 1 }}"
                            role="region"
                            aria-labelledby="about-faq-trigger-{{ $i + 1 }}"
                            x-show="open === {{ $i + 1 }}"
                            x-transition
                            class="pb-6 font-editorial text-base leading-relaxed text-brand-ink/80"
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
    <section class="relative bg-brand-paper" aria-labelledby="about-bottom-cta-heading">
        <div class="mx-auto max-w-5xl px-4 py-24 text-center sm:px-6 sm:py-32 lg:px-8">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-chapter-produce">{{ __('Start your box') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="150">
                <h2 id="about-bottom-cta-heading" class="mt-6 font-display text-4xl font-semibold leading-tight text-brand-moss sm:text-5xl lg:text-6xl">
                    {{ __('Join the table.') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="250">
                <p class="mx-auto mt-6 max-w-2xl font-editorial text-lg italic leading-relaxed text-brand-ink/80">
                    {{ __('One subscription, a short list of producers, and a weekly parcel that tastes like where it came from.') }}
                </p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="350">
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
            <x-triple-star class="mt-16" />
        </div>
    </section>
@endsection
