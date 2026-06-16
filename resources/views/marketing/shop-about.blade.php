@extends('layouts.shop-marketing')

@section('content')
    {{-- Chapter 1: Why we started Erdgut --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper pt-24 pb-16 sm:pt-32 sm:pb-20" aria-labelledby="shop-about-letter-heading">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8" data-testid="shop-about-founder-letter">
            <div class="mx-auto max-w-3xl text-center">
                <x-reveal variant="fade-up">
                    <p class="eyebrow text-chapter-almanach">{{ __('Chapter 1') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="100">
                    <h1 id="shop-about-letter-heading" class="mt-4 font-display text-4xl font-semibold leading-tight tracking-tight text-brand-moss sm:text-5xl lg:text-6xl">
                        {{ __('Why we started Erdgut.') }}
                    </h1>
                </x-reveal>
                <x-reveal variant="fade-up" delay="150">
                    <p class="mx-auto mt-4 max-w-2xl font-editorial text-lg italic leading-relaxed text-brand-ink/80">
                        {{ __('A letter from Lucille, Arthur and Milan.') }}
                    </p>
                </x-reveal>
            </div>

            <div class="mt-10 grid gap-12 lg:grid-cols-12 lg:items-center lg:gap-16">
                <div class="lg:col-span-7">
                    <x-reveal variant="fade-up" delay="200">
                        <p class="drop-cap font-editorial text-lg leading-relaxed text-brand-ink/90" style="--chapter-ink: theme('colors.chapter.produce.DEFAULT');">
                            {{ __('How did we come to no longer know where our food comes from? To eat tomatoes in February instead of seasonal vegetables such as parsnips or crosnes? To lose the experience of true flavour in favour of uniformity? To ingest chemicals on a daily basis, or tolerate that some animals never see sunlight? To slowly accept the disappearance of small farms and endemic species that make up the richness of our regions?') }}
                        </p>
                    </x-reveal>
                    <x-reveal variant="fade-up" delay="100">
                        <p class="mt-8 font-editorial text-lg leading-relaxed text-brand-ink/85">
                            <span class="font-semibold italic text-brand-ink">{{ __('Food is never just food.') }}</span>
                            {{ __('Behind every vegetable, every fruit, every cheese and every cut of meat lies a landscape, a season, a craft and the work of people who dedicate their lives to growing and raising food with care.') }}
                            {{ __('Today, these producers are becoming increasingly rare. In Germany, 10 farms disappear every day, replaced by larger and more standardized models of production. As food becomes more uniform, we lose not only biodiversity and local know-how, but also our connection to where our food comes from.') }}
                        </p>
                    </x-reveal>
                    <x-reveal variant="fade-up" delay="200">
                        <p class="mt-8 font-editorial text-lg leading-relaxed text-brand-ink/85">
                            {{ __('Yet another path exists, and Erdgut was created to bring it to Berlin.') }}
                            {{ __('Across Germany, France and Italy, farmers and artisans continue to cultivate their land with care. They preserve local varieties, respect natural cycles, raise animals with care, and harvest when flavour is at its best.') }}
                            {{ __('Our role is simple: to connect their work with your table. Seasonal food, rooted in its terroir, delivered across Berlin.') }}
                        </p>
                    </x-reveal>
                    <x-reveal variant="fade-up" delay="300">
                        <p class="mt-8 pb-8 font-editorial text-lg italic leading-relaxed text-brand-ink/75 sm:pb-12">
                            {{ __('We hope you will take as much pleasure in discovering their worlds as we did in bringing them to you.') }}
                        </p>
                    </x-reveal>
                </div>
                <x-reveal variant="scale" class="lg:col-span-5">
                    <div class="aspect-[4/5] overflow-hidden">
                        <img
                            src="{{ asset('images/about/founder-letter.jpg') }}"
                            alt=""
                            class="h-full w-full object-cover"
                            loading="lazy"
                            data-testid="shop-about-founder-image"
                        >
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Chapter 2: Erdgut's model --}}
    <x-chapter-divider
        chapter="almanach"
        :eyebrow="__('Chapter 2')"
        :title="__('Erdgut\'s model')"
        :showMark="false"
    />

    <section class="border-b border-brand-ink/10 bg-brand-paper" aria-labelledby="shop-about-model-heading" data-testid="shop-about-model">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <h2 id="shop-about-model-heading" class="sr-only">{{ __('Erdgut\'s model') }}</h2>
            <div class="grid gap-12 lg:grid-cols-12 lg:items-center lg:gap-16">
                <x-reveal variant="fade-up" class="lg:col-span-7">
                    <p class="font-editorial text-lg leading-relaxed text-brand-ink/85">
                        {{ __('We favour regional producers whenever possible, supporting German farms that embody a more sustainable and flavour-driven model of agriculture.') }}
                        {{ __('Yet some products only reach their full expression elsewhere. This is why we also work with small-scale farms and fisheries in France and Italy, whose terroirs give rise to exceptional fruits, vegetables and seafood.') }}
                    </p>
                    <p class="mt-8 font-editorial text-lg leading-relaxed text-brand-ink/85">
                        {{ __('Above all, we source directly from people, not through anonymous supply chains. Behind every product is a producer, a craft and a story worth sharing.') }}
                        {{ __('We sell these products and the passion behind them to some of Berlin\'s most demanding chefs, who seek ingredients with character, flavour and a deep connection to their origin. Through their craft, they transform the work of producers into memorable experiences at the table.') }}
                    </p>
                    <p class="mt-8 font-editorial text-lg leading-relaxed text-brand-ink/85">
                        {{ __('We believe such products deserve to be shared as widely as possible. Erdgut exists to make them accessible across Berlin, while remaining true to the values and producers that make them exceptional in the first place.') }}
                    </p>
                </x-reveal>
                <x-reveal variant="scale" class="lg:col-span-5">
                    <div class="aspect-[4/5] overflow-hidden">
                        <img
                            src="{{ asset('images/about/model.jpg') }}"
                            alt=""
                            class="h-full w-full object-cover"
                            loading="lazy"
                            data-testid="shop-about-model-image"
                        >
                    </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Chapter 3: Four principles --}}
    <x-chapter-divider
        chapter="meat"
        :eyebrow="__('Chapter 3')"
        :title="__('Four principles we don\'t trade away.')"
        :subtitle="__('The rules of the house.')"
        :showMark="false"
    />

    <section class="border-b border-brand-ink/10 bg-brand-paper" aria-labelledby="shop-about-values-heading">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <h2 id="shop-about-values-heading" class="sr-only">{{ __('Our principles') }}</h2>
            @php
                $values = [
                    ['chapter' => 'produce', 'title' => __('Seasonal first.'), 'copy' => __('We follow the harvest, not the global year-round aisle. In March you will not find strawberries in the shop — and in July you will barely see anything else.')],
                    ['chapter' => 'fish', 'title' => __('Short circle, clear origins.'), 'copy' => __('Food should never be anonymous. Behind every product is a producer, a craft and a story. We believe knowing who grows, raises or makes your food is just as important as knowing what is on your plate.')],
                    ['chapter' => 'meat', 'title' => __('Fair share for producers.'), 'copy' => __('Pricing and relationships are designed so a larger share of what you pay stays with the people who actually grew or made your food.')],
                    ['chapter' => 'eggdairy', 'title' => __('Old varieties, kept alive.'), 'copy' => __('We seek out heirloom and lesser-known varieties that rarely reach the mass market, maintaining biodiversity, flavours and stories from disappearing.')],
                ];
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
            <ul class="grid gap-10 reveal-stagger sm:grid-cols-2" data-testid="shop-about-values-row">
                @foreach ($values as $i => $v)
                    <x-reveal as="li" variant="fade-up" class="flex flex-col gap-4 border-t {{ $vBorder[$v['chapter']] }} pt-6" style="--i:{{ $i }}">
                        <div class="flex items-center justify-between">
                            <p class="eyebrow {{ $vText[$v['chapter']] }}">{{ sprintf('%02d', $i + 1) }}</p>
                            <x-chapter-mark :chapter="$v['chapter']" size="sm" class="opacity-90" />
                        </div>
                        <h3 class="font-display text-2xl leading-snug text-brand-ink">{{ $v['title'] }}</h3>
                        <p class="font-editorial text-base leading-relaxed text-brand-ink/80">{{ $v['copy'] }}</p>
                    </x-reveal>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Chapter 4: FAQ --}}
    <x-chapter-divider
        chapter="producers"
        :eyebrow="__('Chapter 4')"
        :title="__('Good to know.')"
        :subtitle="__('On delivery, ordering, and the people behind the shop.')"
        :showMark="false"
    />

    <section class="border-b border-brand-ink/10 bg-brand-paper" aria-labelledby="shop-about-faq-heading" id="faq">
        <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6 sm:py-24 lg:px-8">
            <h2 id="shop-about-faq-heading" class="sr-only">{{ __('Frequently asked questions') }}</h2>
            <div class="divide-y divide-brand-ink/15 border-y border-brand-ink/15" x-data="{ open: null }" data-testid="shop-about-faq-accordion">
                @foreach ([
                    ['q' => __('Where do you deliver?'), 'a' => __('We focus on Berlin and nearby postcodes. Exact delivery areas evolve as we grow, write to us to check yours.')],
                    ['q' => __('Do I need a subscription to order?'), 'a' => __('No. The shop is open to anyone. Order whatever you like, whenever you like, with no commitment and no stored card you did not ask for. Box subscription is also on its way to benefit from weekly discoveries and better prices.')],
                    ['q' => __('How do you choose producers?'), 'a' => __('By hand. We meet the farmers, breeders, fishermen and artisans we work with. We visit their farms, understand their practices, and get to know the people behind the products. We favour farming methods that respect natural cycles. But we also believe that agriculture cannot be reduced to a checklist. Every farm faces different constraints, climates and realities. Rather than applying the same grid to everyone, we try to understand each producer and support them. In fact, most of the producers we work with go well beyond the requirements of organic certification.')],
                    ['q' => __('What if something in my order is not right?'), 'a' => __('Reply to the note that travels with your order. We read every message and make it right: no anonymous tickets, no support queue.')],
                ] as $i => $item)
                    <div>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between py-6 text-left font-display text-lg text-brand-ink transition hover:text-chapter-producers"
                            @click="open = open === {{ $i + 1 }} ? null : {{ $i + 1 }}"
                            :aria-expanded="open === {{ $i + 1 }}"
                            aria-controls="shop-about-faq-panel-{{ $i + 1 }}"
                            id="shop-about-faq-trigger-{{ $i + 1 }}"
                        >
                            <span>{{ $item['q'] }}</span>
                            <span class="ml-4 text-2xl leading-none text-chapter-producers" x-text="open === {{ $i + 1 }} ? '−' : '+'"></span>
                        </button>
                        <div
                            id="shop-about-faq-panel-{{ $i + 1 }}"
                            role="region"
                            aria-labelledby="shop-about-faq-trigger-{{ $i + 1 }}"
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
                <a href="{{ route('public.shop-contact') }}" class="font-semibold text-brand-ink underline-offset-4 hover:underline">{{ __('Write to us') }}</a>
            </p>
        </div>
    </section>

    {{-- Closing CTA --}}
    <section class="relative bg-brand-paper" aria-labelledby="shop-about-bottom-cta-heading">
        <div class="mx-auto max-w-5xl px-4 py-24 text-center sm:px-6 sm:py-32 lg:px-8">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-chapter-produce">{{ __('Start your order') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="150">
                <h2 id="shop-about-bottom-cta-heading" class="mt-6 font-display text-4xl font-semibold leading-tight text-brand-moss sm:text-5xl lg:text-6xl">
                    {{ __('Join the table.') }}
                </h2>
            </x-reveal>
            <x-reveal variant="fade-up" delay="250">
                <p class="mx-auto mt-6 max-w-2xl font-editorial text-lg italic leading-relaxed text-brand-ink/80">
                    {{ __('A short list of producers, a shop that follows the seasons, and a parcel that tastes like where it came from.') }}
                </p>
            </x-reveal>
            <x-reveal variant="fade-up" delay="350">
                <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center" data-testid="shop-about-bottom-ctas">
                    <a
                        href="{{ route('public.shop.index') }}"
                        class="btn-solid"
                        data-testid="shop-about-bottom-shop"
                    >
                        {{ __('Visit the shop') }}
                    </a>
                    <a
                        href="{{ route('public.shop-contact') }}"
                        class="btn-ghost"
                        data-testid="shop-about-bottom-contact"
                    >
                        {{ __('Contact us') }}
                    </a>
                </div>
            </x-reveal>
            <x-triple-star class="mt-16" />
        </div>
    </section>
@endsection
