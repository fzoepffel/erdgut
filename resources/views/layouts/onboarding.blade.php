<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-csp sumup />
        <title>{{ config('app.name', 'Erdgut') }} — {{ $title ?? __('Get started') }}</title>
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/erdgut-favicon-16.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo/erdgut-favicon-192.png') }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/logo/erdgut-favicon-512.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://gateway.sumup.com" crossorigin>
        <link rel="dns-prefetch" href="https://api.sumup.com">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-brand-cream font-sans text-brand-ink antialiased">
        <x-skip-link />

        @php
            // ── Per-step hero + stepper meta ───────────────────────────────
            // The wizard renders one editorial shell; each step swaps the hero
            // copy + aside + step number. Konto is intentionally absent from
            // the customer-facing numbering — the four visible steps are
            // Adresse · Box · Zeitplan · Zahlung. (The account step still
            // exists for guest entry; it simply renders no stepper highlight.)
            $stepProp = $step ?? 'address';
            $intentProp = $intent ?? 'subscription';
            $isTrial = $intentProp === \App\Services\Onboarding\OnboardingDraftManager::INTENT_TRIAL;

            // The editorial hero only fronts the four redesigned subscription
            // steps. The remaining steps (Konto, Commitment, trial sub-steps)
            // keep their own in-content heading and render inside the same
            // shell without a hero.
            $showHero = in_array($stepProp, ['address', 'preferences', 'subscription_schedule', 'subscription_payment'], true);

            $heroKey = match ($stepProp) {
                'address' => 'address',
                'preferences', 'commitment' => 'box',
                'subscription_schedule', 'trial_delivery' => 'schedule',
                'subscription_payment', 'trial_payment' => 'payment',
                default => 'address',
            };

            $stepNumber = match ($heroKey) {
                'address' => 1,
                'box' => 2,
                'schedule' => 3,
                'payment' => 4,
            };

            $eyebrowChapter = [
                'address' => 'text-chapter-producers',
                'box' => 'text-chapter-produce',
                'schedule' => 'text-chapter-fish',
                'payment' => 'text-chapter-meat',
            ][$heroKey];

            $stepName = match ($heroKey) {
                'address' => __('onboarding.step.address'),
                'box' => __('onboarding.step.box'),
                'schedule' => $isTrial ? __('onboarding.step.delivery') : __('onboarding.step.schedule'),
                'payment' => __('onboarding.step.payment'),
            };

            $zoneMin = config('delivery.zones.0.postal_code_min', 10115);
            $zoneMax = config('delivery.zones.0.postal_code_max', 14199);

            // Aside: mono title, oversized serif anchor word, hairline, dl.
            $aside = match ($heroKey) {
                'address' => [
                    'title' => __('onboarding.hero.address.aside_title'),
                    'anchor' => __('onboarding.val.berlin'),
                    'rows' => [
                        [__('onboarding.lbl.area'), __('onboarding.val.berlin')],
                        [__('onboarding.lbl.zip'), $zoneMin.'–'.$zoneMax],
                        [__('onboarding.lbl.day'), __('onboarding.val.wednesday')],
                        [__('onboarding.lbl.window'), __('onboarding.val.window')],
                    ],
                ],
                'box' => [
                    'title' => __('onboarding.hero.box.aside_title'),
                    'anchor' => __('onboarding.hero.box.aside_anchor'),
                    'rows' => [
                        [__('onboarding.lbl.origin'), __('onboarding.val.regional')],
                        [__('onboarding.lbl.content'), __('onboarding.val.veg_fruit')],
                        [__('onboarding.lbl.rhythm'), __('onboarding.val.weekly_flexible')],
                    ],
                ],
                'schedule' => [
                    'title' => __('onboarding.hero.schedule.aside_title'),
                    'anchor' => __('onboarding.val.wednesday'),
                    'rows' => [
                        [__('onboarding.lbl.day'), __('onboarding.val.wednesday')],
                        [__('onboarding.lbl.window'), __('onboarding.val.window')],
                        [__('onboarding.lbl.area'), __('onboarding.val.berlin')],
                    ],
                ],
                'payment' => [
                    'title' => __('onboarding.hero.payment.aside_title'),
                    'anchor' => 'SumUp',
                    'rows' => [
                        [__('onboarding.lbl.provider'), 'SumUp'],
                        [__('onboarding.lbl.security'), __('onboarding.val.pci')],
                        [__('onboarding.lbl.billing'), __('onboarding.val.per_delivery')],
                    ],
                ],
            };
        @endphp

        <div class="min-h-screen flex flex-col" data-testid="onboarding-layout">
            <x-customer-header variant="wizard" />

            <main id="main" class="flex-1" tabindex="-1">

                {{-- ═══ HERO ═══ --}}
                @if ($showHero)
                <section class="relative border-b border-brand-ink/10">
                    <div class="mx-auto max-w-[1400px] px-6 py-14 lg:px-12 lg:py-20">
                        <span class="absolute right-6 top-6 hidden font-mono text-[0.65rem] uppercase tracking-widest {{ $eyebrowChapter }} lg:block" aria-hidden="true">
                            {{ __('onboarding.hero.step_tag', ['n' => str_pad((string) $stepNumber, 2, '0', STR_PAD_LEFT), 'name' => $stepName]) }}
                        </span>

                        <div class="grid gap-12 lg:grid-cols-[1.15fr_1fr] lg:items-start lg:gap-16">
                            <div>
                                <p class="eyebrow {{ $eyebrowChapter }}">{{ __('onboarding.hero.'.$heroKey.'.eyebrow') }}</p>
                                <h1 class="mt-6 font-display text-4xl leading-[1.05] tracking-tight text-brand-ink md:text-5xl lg:text-[4.5rem]">
                                    {{ __('onboarding.hero.'.$heroKey.'.title_pre') }}<em class="font-normal italic text-chapter-produce">{{ __('onboarding.hero.'.$heroKey.'.title_em') }}</em>{{ __('onboarding.hero.'.$heroKey.'.title_post') }}
                                </h1>
                                <p class="mt-8 max-w-xl text-base leading-relaxed text-brand-ink/60">
                                    {{ __('onboarding.hero.'.$heroKey.'.lede') }}
                                </p>
                            </div>

                            <div class="self-start bg-brand-paper p-8 lg:p-10">
                                <p class="font-mono text-[0.65rem] uppercase tracking-widest {{ $eyebrowChapter }}">{{ $aside['title'] }}</p>
                                <p class="mt-3 font-display text-4xl text-brand-ink">{{ $aside['anchor'] }}</p>
                                <div class="mt-8 h-px bg-brand-ink/10"></div>
                                <dl class="mt-6 space-y-5 text-sm">
                                    @foreach ($aside['rows'] as [$rowLabel, $rowValue])
                                        <div class="flex items-baseline justify-between gap-4">
                                            <dt class="shrink-0 font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ $rowLabel }}</dt>
                                            <dd class="text-right font-display text-lg text-brand-ink">{{ $rowValue }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                {{-- ═══ STEPPER RAIL ═══ --}}
                <section class="border-b border-brand-ink/10 bg-brand-paper">
                    <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
                        <x-onboarding.progress :step="$stepProp" :intent="$intentProp" />
                    </div>
                </section>

                {{-- ═══ FORM BODY ═══ --}}
                <section class="mx-auto max-w-[1400px] px-6 py-14 lg:px-12 lg:py-20">
                    <div class="grid gap-12 lg:grid-cols-[1.3fr_1fr] lg:gap-16">
                        <div>
                            @yield('content')
                        </div>

                        <aside class="lg:pl-4">
                            @isset($draft)
                                <x-onboarding.summary :draft="$draft" :user="auth()->user()" />
                            @endisset
                        </aside>
                    </div>
                </section>
            </main>

            <x-customer.editorial-footer-band />
        </div>
    </body>
</html>
