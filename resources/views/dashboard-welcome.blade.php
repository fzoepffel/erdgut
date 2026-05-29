@php
    $trialEnabled = (bool) config('features.trial_orders_enabled', true);
    $trialPrice = \App\Support\Money::format(config('box.trial_price_oneoff_ttc', 35), 0);
    $user = auth()->user();
    $firstName = str($user->name)->before(' ')->toString() ?: $user->name;
    $deliveryMin = config('delivery.zones.0.postal_code_min', 10115);
    $deliveryMax = config('delivery.zones.0.postal_code_max', 14199);
@endphp

<x-app-layout :hide-app-footer="true">

    {{-- ═══════════════════════════════════════════
         HERO — account confirmation + welcome copy
    ═══════════════════════════════════════════ --}}
    <section class="relative border-b border-brand-ink/10 py-16 lg:py-24">

        {{-- Step stamp top-right --}}
        <span class="absolute right-6 top-6 hidden font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce lg:block" aria-hidden="true">
            {{ __('welcome.step_stamp') }}
        </span>

        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-12 lg:grid-cols-[1.2fr_1fr] lg:gap-16 lg:items-start">

                {{-- Left: editorial welcome copy --}}
                <div>
                    <x-reveal><p class="eyebrow text-chapter-produce">{{ __('welcome.hero_eyebrow') }}</p></x-reveal>

                    <x-reveal variant="fade-up" delay="100">
                    <h1 class="mt-6 font-display text-5xl leading-[1.02] tracking-tight text-brand-ink md:text-6xl lg:text-[5rem]">
                        {{ __('welcome.hero_h1_pre') }}<em class="font-normal italic text-chapter-produce">{{ __('welcome.hero_h1_brand') }}</em>{{ __('welcome.hero_h1_mid') }}<span>{{ $firstName }}</span>.
                    </h1>
                    </x-reveal>

                    <x-reveal variant="fade-up" delay="200">
                    <p class="mt-8 max-w-xl text-base leading-relaxed text-brand-ink/60">
                        {{ __('welcome.hero_body') }}
                    </p>
                    </x-reveal>
                </div>

                {{-- Right: "On the slip" account summary aside --}}
                <x-reveal variant="fade" delay="150">
                <div class="self-start bg-brand-paper p-8 lg:p-10">
                    <p class="eyebrow text-[0.65rem] tracking-widest text-chapter-produce">{{ __('welcome.aside_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-3xl text-brand-ink">{{ __('welcome.aside_h2') }}</h2>

                    <div class="mt-8 h-px bg-brand-ink/10"></div>

                    <dl class="mt-6 space-y-5 text-sm">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45 shrink-0">{{ __('Name') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ $user->name }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45 shrink-0">{{ __('Email') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ __('welcome.aside_email_status') }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45 shrink-0">{{ __('welcome.aside_delivery_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ __('welcome.aside_delivery_value', ['min' => $deliveryMin, 'max' => $deliveryMax]) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45 shrink-0">{{ __('welcome.aside_next_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ __('welcome.aside_next_value') }}</dd>
                        </div>
                    </dl>
                </div>
                </x-reveal>

            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         TWO WAYS — subscription vs. trial
    ═══════════════════════════════════════════ --}}
    @if (session('status') === 'subscriber-area-requires-setup')
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12 pt-6">
            <p class="border border-brand-ink/20 bg-brand-ink/[0.03] px-4 py-3 text-sm text-brand-ink" role="status">
                {{ __('Complete your subscription and payment setup to access deliveries, shop extras, and billing.') }}
            </p>
        </div>
    @endif

    <section class="border-b border-brand-ink/10 bg-brand-paper py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            {{-- Section header --}}
            <x-reveal variant="fade-up">
            <div class="mb-12 grid gap-6 md:grid-cols-[auto_1fr_1fr] md:items-end md:gap-12">
                <span class="font-mono text-xs uppercase tracking-widest text-chapter-produce">01</span>
                <div>
                    <p class="eyebrow text-brand-ink/35">{{ __('welcome.ways_eyebrow') }}</p>
                    <h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                        {{ __('welcome.ways_h2') }}
                    </h2>
                </div>
                <p class="max-w-sm text-sm leading-relaxed text-brand-ink/50 md:text-right md:justify-self-end">
                    {{ __('welcome.ways_lede') }}
                </p>
            </div>
            </x-reveal>

            {{-- Cards --}}
            <div class="grid gap-6 {{ $trialEnabled ? 'lg:grid-cols-2' : '' }}">

                {{-- Subscription card --}}
                <x-reveal variant="fade-up" delay="50">
                <div class="border border-brand-ink/10 bg-brand-cream p-8 lg:p-10" data-testid="welcome-subscription-card">
                    <p class="eyebrow text-[0.65rem] tracking-widest text-chapter-produce">{{ __('welcome.sub_eyebrow') }}</p>
                    <h3 class="mt-3 font-display text-4xl text-brand-ink">{{ __('welcome.sub_h3') }}</h3>
                    <p class="mt-4 text-sm leading-relaxed text-brand-ink/60">
                        {{ __('welcome.sub_body') }}
                    </p>

                    <div class="mt-8 h-px bg-brand-ink/10"></div>

                    <ol class="mt-6 space-y-6">
                        <li class="grid grid-cols-[auto_1fr] items-baseline gap-5 border-b border-brand-ink/10 pb-5">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">01</span>
                            <div>
                                <p class="font-display text-xl text-brand-ink">{{ __('welcome.sub_step_1_title') }}</p>
                                <p class="mt-1 text-sm text-brand-ink/60">{{ __('welcome.sub_step_1_desc') }}</p>
                            </div>
                        </li>
                        <li class="grid grid-cols-[auto_1fr] items-baseline gap-5 border-b border-brand-ink/10 pb-5">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">02</span>
                            <div>
                                <p class="font-display text-xl text-brand-ink">{{ __('welcome.sub_step_2_title') }}</p>
                                <p class="mt-1 text-sm text-brand-ink/60">{{ __('welcome.sub_step_2_desc') }}</p>
                            </div>
                        </li>
                    </ol>

                    <div class="mt-8 flex flex-wrap items-center gap-5">
                        <a
                            href="{{ route('app.onboarding.start', ['intent' => 'subscription']) }}"
                            class="rounded-sm bg-chapter-produce px-7 py-3 text-sm text-brand-cream hover:bg-chapter-produce/90 transition-colors"
                            data-testid="welcome-cta-subscribe"
                        >
                            {{ __('welcome.sub_cta') }}
                        </a>
                        <span class="text-xs text-brand-ink/45">{{ __('welcome.sub_note') }}</span>
                    </div>
                </div>
                </x-reveal>

                {{-- Trial card --}}
                @if ($trialEnabled)
                <x-reveal variant="fade-up" delay="100">
                <div class="border border-brand-ink/10 bg-brand-cream p-8 lg:p-10" data-testid="welcome-trial-card">
                    <p class="eyebrow text-[0.65rem] tracking-widest text-chapter-meat">{{ __('welcome.trial_eyebrow') }}</p>
                    <h3 id="welcome-trial-heading" class="mt-3 font-display text-4xl text-brand-ink">{{ __('welcome.trial_h3') }}</h3>
                    <p class="mt-4 text-sm leading-relaxed text-brand-ink/60">
                        {{ __('welcome.trial_body', ['amount' => $trialPrice]) }}
                    </p>

                    <div class="mt-8 h-px bg-brand-ink/10"></div>

                    <dl class="mt-6 space-y-4 text-sm">
                        <div class="flex items-baseline justify-between gap-4 border-b border-brand-ink/10 pb-4">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('welcome.trial_price_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink">{{ __('welcome.trial_price_value', ['amount' => $trialPrice]) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 border-b border-brand-ink/10 pb-4">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('welcome.trial_includes_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right max-w-[60%]">{{ __('welcome.trial_includes_value') }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 border-b border-brand-ink/10 pb-4">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('welcome.trial_delivery_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ __('welcome.trial_delivery_value') }}</dd>
                        </div>
                    </dl>

                    <div class="mt-8 flex flex-wrap items-center gap-5">
                        <a
                            href="{{ route('app.onboarding.start', ['intent' => 'trial']) }}"
                            class="rounded-sm border border-brand-ink/20 bg-transparent px-7 py-3 text-sm text-brand-ink hover:bg-brand-ink/5 transition-colors"
                            data-testid="welcome-cta-try-box"
                        >
                            {{ __('welcome.trial_cta') }}
                        </a>
                        <span class="text-xs text-brand-ink/45">{{ __('welcome.trial_note') }}</span>
                    </div>
                </div>
                </x-reveal>
                @endif

            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         GOOD TO KNOW — three-column reassurance
    ═══════════════════════════════════════════ --}}
    <section class="py-16 lg:py-20">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 sm:grid-cols-3">
                <x-reveal variant="fade-up">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('welcome.know_eyebrow') }}</p>
                    <h3 class="mt-4 font-display text-2xl text-brand-ink">{{ __('welcome.know_1_h3') }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-brand-ink/60">{{ __('welcome.know_1_body', ['min' => $deliveryMin, 'max' => $deliveryMax]) }}</p>
                </div>
                </x-reveal>
                <x-reveal variant="fade-up" delay="100">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('welcome.know_eyebrow') }}</p>
                    <h3 class="mt-4 font-display text-2xl text-brand-ink">{{ __('welcome.know_2_h3') }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-brand-ink/60">{{ __('welcome.know_2_body') }}</p>
                </div>
                </x-reveal>
                <x-reveal variant="fade-up" delay="200">
                <div>
                    <p class="eyebrow text-chapter-produce">{{ __('welcome.know_eyebrow') }}</p>
                    <h3 class="mt-4 font-display text-2xl text-brand-ink">{{ __('welcome.know_3_h3') }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-brand-ink/60">{{ __('welcome.know_3_body') }}</p>
                </div>
                </x-reveal>
            </div>
        </div>
    </section>

    {{-- Editorial footer (replaces standard app-footer) --}}
    <x-customer.editorial-footer-band />

</x-app-layout>
