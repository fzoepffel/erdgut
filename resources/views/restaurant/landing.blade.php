<x-shop-layout :title="__('restaurant.landing_title')">
    {{-- Hero --}}
    <section class="mx-auto max-w-5xl px-4 py-16 sm:px-6 sm:py-24">
        <p class="eyebrow text-shop-primary">{{ __('restaurant.nav_public') }}</p>
        <h1 class="mt-3 max-w-3xl font-display text-4xl leading-tight text-shop-foreground sm:text-5xl">{{ __('restaurant.landing_title') }}</h1>
        <p class="mt-5 max-w-2xl text-lg leading-relaxed text-shop-muted">{{ __('restaurant.landing_intro') }}</p>

        <div class="mt-8 flex flex-wrap items-center gap-4">
            <a href="{{ route('restaurant.register') }}" class="inline-flex h-[3.25rem] items-center justify-center border border-shop-primary bg-shop-primary px-7 font-mono text-[0.72rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                {{ __('restaurant.landing_cta_apply') }}
            </a>
            <a href="{{ route('auth.login') }}" class="inline-flex h-[3.25rem] items-center justify-center border border-shop-border px-7 font-mono text-[0.72rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                {{ __('restaurant.landing_cta_login') }}
            </a>
        </div>
    </section>

    {{-- Value props --}}
    <section class="border-t border-shop-border bg-shop-card/40">
        <div class="mx-auto grid max-w-5xl gap-6 px-4 py-16 sm:grid-cols-2 sm:px-6">
            @foreach ([
                'prices' => ['title' => __('restaurant.landing_feature_prices_title'), 'body' => __('restaurant.landing_feature_prices_body')],
                'range' => ['title' => __('restaurant.landing_feature_range_title'), 'body' => __('restaurant.landing_feature_range_body')],
                'delivery' => ['title' => __('restaurant.landing_feature_delivery_title'), 'body' => __('restaurant.landing_feature_delivery_body')],
                'invoice' => ['title' => __('restaurant.landing_feature_invoice_title'), 'body' => __('restaurant.landing_feature_invoice_body')],
            ] as $feature)
                <div class="shop-card p-6">
                    <h2 class="font-display text-xl text-shop-foreground">{{ $feature['title'] }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-shop-muted">{{ $feature['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-5xl px-4 py-16 sm:px-6">
        <h2 class="font-display text-2xl text-shop-foreground sm:text-3xl">{{ __('restaurant.landing_how_title') }}</h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-3">
            @foreach (['1', '2', '3'] as $step)
                <li class="border-t-2 border-shop-primary pt-4">
                    <span class="font-mono text-sm text-shop-primary">0{{ $step }}</span>
                    <h3 class="mt-1 font-display text-lg text-shop-foreground">{{ __('restaurant.landing_step'.$step.'_title') }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-shop-muted">{{ __('restaurant.landing_step'.$step.'_body') }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Closing CTA --}}
    <section class="border-t border-shop-border">
        <div class="mx-auto flex max-w-5xl flex-col items-start gap-5 px-4 py-16 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h2 class="font-display text-2xl text-shop-foreground">{{ __('restaurant.landing_closing_title') }}</h2>
                <p class="mt-2 text-sm text-shop-muted">{{ __('restaurant.landing_closing_body') }}</p>
            </div>
            <a href="{{ route('restaurant.register') }}" class="shrink-0 inline-flex h-[3.25rem] items-center justify-center border border-shop-primary bg-shop-primary px-7 font-mono text-[0.72rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                {{ __('restaurant.landing_cta_apply') }}
            </a>
        </div>
    </section>
</x-shop-layout>
