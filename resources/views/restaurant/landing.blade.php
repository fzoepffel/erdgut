<x-shop-layout :title="__('restaurant.landing_title')">
    {{-- ── Hero: moss band, editorial headline ─────────────────────────── --}}
    <section class="border-b border-brand-ink/10 bg-brand-moss py-20 text-brand-cream sm:py-28">
        <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
            <x-reveal variant="fade-up">
                <p class="eyebrow text-brand-cream/70">{{ __('restaurant.nav_public') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" :delay="100">
                <h1 class="mt-4 max-w-3xl font-display text-5xl leading-[1.05] tracking-tight sm:text-6xl">{{ __('restaurant.landing_title') }}</h1>
            </x-reveal>
            <x-reveal variant="fade-up" :delay="200">
                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-brand-cream/85">{{ __('restaurant.landing_intro') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" :delay="300">
                <div class="mt-9 flex flex-wrap items-center gap-5">
                    <a href="{{ route('restaurant.register') }}"
                       class="inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-7 py-3 font-mono text-[0.7rem] uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream">
                        {{ __('restaurant.landing_cta_apply') }} &rarr;
                    </a>
                    <a href="{{ route('auth.login') }}" class="font-mono text-[0.7rem] uppercase tracking-eyebrow text-brand-cream underline underline-offset-4 hover:opacity-80">
                        {{ __('restaurant.landing_cta_login') }}
                    </a>
                </div>
            </x-reveal>
        </div>
    </section>

    {{-- ── Partner kitchens: the restaurants are the heroes ─────────────── --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper py-20 sm:py-24">
        <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
            <x-section-heading
                :eyebrow="__('restaurant.landing_partners_eyebrow')"
                :title="__('restaurant.landing_partners_title')"
                color="meat"
            />
            <div class="reveal-stagger mt-10 grid max-w-none gap-x-12 gap-y-6 lg:grid-cols-2">
                <x-reveal as="p" style="--i:0" class="text-base leading-relaxed text-brand-ink/80">{{ __('restaurant.landing_partners_p1') }}</x-reveal>
                <div class="space-y-6">
                    <x-reveal as="p" style="--i:1" class="text-base leading-relaxed text-brand-ink/80">{{ __('restaurant.landing_partners_p2') }}</x-reveal>
                    <x-reveal as="p" style="--i:2" class="font-display text-xl leading-snug text-brand-ink">{{ __('restaurant.landing_partners_p3') }}</x-reveal>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Opportunities: what your kitchen can do here ─────────────────── --}}
    <section class="border-b border-brand-ink/10 py-20 sm:py-24">
        <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
            <x-section-heading
                :eyebrow="__('restaurant.landing_features_eyebrow')"
                :title="__('restaurant.landing_features_title')"
                color="produce"
            />
            <div class="reveal-stagger mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Full literal class names (Tailwind's scanner can't see interpolated fragments). --}}
                @foreach ([
                    ['border' => 'border-chapter-produce', 'text' => 'text-chapter-produce', 'title' => __('restaurant.landing_feature_prices_title'), 'body' => __('restaurant.landing_feature_prices_body')],
                    ['border' => 'border-chapter-eggdairy', 'text' => 'text-chapter-eggdairy', 'title' => __('restaurant.opportunity_weekly_title'), 'body' => __('restaurant.opportunity_weekly_body')],
                    ['border' => 'border-chapter-bread', 'text' => 'text-chapter-bread', 'title' => __('restaurant.opportunity_amounts_title'), 'body' => __('restaurant.opportunity_amounts_body')],
                    ['border' => 'border-chapter-almanach', 'text' => 'text-chapter-almanach', 'title' => __('restaurant.opportunity_special_title'), 'body' => __('restaurant.opportunity_special_body')],
                ] as $i => $f)
                    <x-reveal as="div" style="--i:{{ $i }}" class="border-t-2 {{ $f['border'] }} pt-5">
                        <p class="eyebrow {{ $f['text'] }}">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</p>
                        <h3 class="mt-2 font-display text-2xl text-brand-ink">{{ $f['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-brand-ink/70">{{ $f['body'] }}</p>
                    </x-reveal>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How it works: 4 steps ────────────────────────────────────────── --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper py-20 sm:py-24">
        <div class="mx-auto max-w-[1100px] px-4 sm:px-6 lg:px-12">
            <x-section-heading
                :eyebrow="__('restaurant.landing_how_eyebrow')"
                :title="__('restaurant.landing_how_title')"
                :lead="__('restaurant.landing_how_lead')"
                color="fish"
            />
            <ol class="reveal-stagger mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['1', '2', '3', '4'] as $i => $step)
                    <x-reveal as="li" style="--i:{{ $i }}" class="border-t-2 border-chapter-fish pt-5">
                        <p class="eyebrow text-chapter-fish">{{ str_pad($step, 2, '0', STR_PAD_LEFT) }}</p>
                        <h3 class="mt-2 font-display text-2xl text-brand-ink">{{ __('restaurant.landing_step'.$step.'_title') }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-brand-ink/70">{{ __('restaurant.landing_step'.$step.'_body') }}</p>
                    </x-reveal>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ── Invoice note + closing CTA (dark band) ───────────────────────── --}}
    <section class="bg-brand-ink py-20 text-brand-cream sm:py-24">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6">
            <x-reveal variant="fade-up">
                <x-triple-star color="cream" />
            </x-reveal>
            <x-reveal variant="fade-up" :delay="80">
                <h2 class="mt-6 font-display text-4xl leading-tight sm:text-5xl">{{ __('restaurant.landing_closing_title') }}</h2>
            </x-reveal>
            <x-reveal variant="fade-up" :delay="160">
                <p class="mx-auto mt-4 max-w-xl text-base text-brand-cream/80">{{ __('restaurant.landing_feature_invoice_body') }}</p>
            </x-reveal>
            <x-reveal variant="fade-up" :delay="240">
                <a href="{{ route('restaurant.register') }}"
                   class="mt-8 inline-flex items-center justify-center border border-brand-cream bg-brand-cream px-7 py-3 font-mono text-[0.7rem] uppercase tracking-eyebrow text-brand-ink transition hover:bg-transparent hover:text-brand-cream">
                    {{ __('restaurant.landing_cta_apply') }} &rarr;
                </a>
            </x-reveal>
        </div>
    </section>
</x-shop-layout>
