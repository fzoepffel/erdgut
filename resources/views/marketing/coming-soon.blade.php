<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:">

        <title>{{ __('Erdgut — Bald geht\'s los') }}</title>
        <meta name="description" content="{{ __('Frisches Gemüse aus Berliner Bauernhand. Bald kannst du dich selbst davon überzeugen.') }}">

        {{-- Open Graph / social share --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Erdgut">
        <meta property="og:title" content="{{ __('Erdgut — Bald geht\'s los') }}">
        <meta property="og:description" content="{{ __('Frisches Gemüse aus Berliner Bauernhand. Bald kannst du dich selbst davon überzeugen.') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/og/erdgut-og-default.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ __('Erdgut — Frisches Gemüse aus Berliner Bauernhand') }}">
        <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale() === 'de' ? 'de_DE' : (app()->getLocale() === 'fr' ? 'fr_FR' : 'en_US')) }}">

        {{-- Twitter card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ __('Erdgut — Bald geht\'s los') }}">
        <meta name="twitter:description" content="{{ __('Frisches Gemüse aus Berliner Bauernhand. Bald kannst du dich selbst davon überzeugen.') }}">
        <meta name="twitter:image" content="{{ asset('images/og/erdgut-og-default.png') }}">
        <meta name="twitter:image:alt" content="{{ __('Erdgut — Frisches Gemüse aus Berliner Bauernhand') }}">

        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/erdgut-favicon-16.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo/erdgut-favicon-192.png') }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/logo/erdgut-favicon-512.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        {{-- Work Sans: used as the body/UI typeface on this page.
             Inter (the main app's sans) is purpose-built for software UIs;
             Work Sans has more craft warmth — a better companion to
             Instrument Serif for this editorial-brand surface. --}}
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=work-sans:400,600&display=swap">

        <style>
            .font-work { font-family: 'Work Sans', sans-serif; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-work antialiased" style="background-color: #709452;">
        <div
            data-testid="coming-soon"
            class="min-h-screen w-full"
        >
            <div class="mx-auto grid min-h-screen w-full max-w-[1600px] grid-cols-1 md:grid-cols-[160px_minmax(0,_1fr)] lg:grid-cols-[200px_minmax(0,_1fr)]">
                {{-- Left column: catalogue-style cabbage illustration stripe --}}
                <div class="relative hidden min-h-[320px] overflow-hidden md:block">
                    <img
                        src="{{ asset('images/chapters/cabbage.png') }}"
                        alt=""
                        aria-hidden="true"
                        class="absolute inset-0 h-full w-full select-none object-cover object-left"
                    />
                </div>

                {{-- Right column: wordmark + content --}}
                <div class="flex flex-col px-6 py-10 sm:px-10 sm:py-14 md:px-14 md:py-16 lg:px-20">

                    {{-- Wordmark + language selector --}}
                    <div class="flex items-start justify-between gap-4">
                        <div
                            role="img"
                            aria-label="Erdgut"
                            class="h-10 w-52 sm:h-12 sm:w-60 md:h-16 md:w-80 lg:h-20 lg:w-96"
                            style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain; mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain;"
                        ></div>
                        <div class="pt-2">
                            <x-language-selector variant="compact" />
                        </div>
                    </div>

                    {{-- ── Hero ──────────────────────────────────────────────────── --}}
                    <div class="mt-10 sm:mt-14 md:mt-16">
                        {{-- 1. Hero kicker --}}
                        <p class="font-work mb-4 inline-block text-[11px] font-semibold uppercase tracking-[0.2em]"
                           style="color: rgba(61, 79, 42, 0.7);">
                            {{ __('Bald geht\'s los') }}
                        </p>

                        {{-- 2. Hero headline — "unterwegs." is italic --}}
                        <h1 class="font-display mb-8 text-5xl font-normal leading-[0.9] text-balance md:text-7xl"
                            style="color: #1A1F12;">
                            {{ __('Frisches Gemüse ist') }} <span class="italic">{{ __('unterwegs.') }}</span>
                        </h1>

                        {{-- 3. Hero paragraph --}}
                        <p class="font-work max-w-xl text-lg leading-relaxed md:text-xl"
                           style="color: rgba(26, 31, 18, 0.8);">
                            {{ __('Noch sind wir auf dem Feld und ernten das Beste der Saison. In Kürze kannst du dich hier selbst davon überzeugen und in ganz Berlin beliefert werden.') }}
                        </p>
                    </div>

                    {{-- ── Feature: Spargel / season highlight ──────────────────── --}}
                    <div class="mt-10 max-w-xl border-t pt-10 sm:mt-14 sm:pt-12"
                         style="border-color: rgba(26, 31, 18, 0.15);">

                        {{-- 4. Feature kicker --}}
                        <p class="font-work mb-6 inline-block text-[11px] font-semibold uppercase tracking-[0.2em]"
                           style="color: rgba(61, 79, 42, 0.7);">
                            {{ __('spargel.coming_soon_eyebrow_kicker') }}
                        </p>

                        {{-- 5. Feature headline — fully italic --}}
                        <p class="font-display mb-6 text-3xl font-normal md:text-4xl"
                           style="color: #1A1F12;">
                            {{ __('spargel.coming_soon_eyebrow') }}
                        </p>

                        {{-- 6. CTA — btn-solid matches logged-in dashboard --}}
                        <a
                            href="{{ route('public.spargel.show') }}"
                            class="btn-solid"
                            data-testid="coming-soon-spargel-cta"
                        >
                            {{ __('spargel.coming_soon_cta') }}
                        </a>
                    </div>

                    {{-- ── Contact ───────────────────────────────────────────────── --}}
                    <div class="mt-10 max-w-xl border-t pt-10 sm:mt-14 sm:pt-12"
                         style="border-color: rgba(26, 31, 18, 0.15);">

                        {{-- 8. Contact kicker --}}
                        <p class="font-work mb-6 inline-block text-[11px] font-semibold uppercase tracking-[0.2em]"
                           style="color: rgba(61, 79, 42, 0.7);">
                            {{ __('footer.contact_heading') }}
                        </p>

                        {{-- 9. Contact headline — not italic --}}
                        <h2 class="font-display mb-12 text-3xl font-normal md:text-4xl"
                            style="color: #1A1F12;">
                            {{ __('Schreib uns gern.') }}
                        </h2>

                        <div class="space-y-8">
                            {{-- 10 + 11. Sub-label + paragraph: Erzeuger --}}
                            <div>
                                <p class="font-work mb-2 text-[11px] font-semibold uppercase tracking-widest"
                                   style="color: rgba(26, 31, 18, 0.5);">
                                    {{ __('Für Erzeuger') }}
                                </p>
                                <p class="font-work text-base leading-relaxed"
                                   style="color: #1A1F12;">
                                    {{ __('Du bist Produzent:in und möchtest deine Erzeugnisse über uns anbieten?') }}
                                </p>
                            </div>

                            {{-- 10 + 11. Sub-label + paragraph: Genießer --}}
                            <div>
                                <p class="font-work mb-2 text-[11px] font-semibold uppercase tracking-widest"
                                   style="color: rgba(26, 31, 18, 0.5);">
                                    {{ __('Für Genießer') }}
                                </p>
                                <p class="font-work text-base leading-relaxed"
                                   style="color: #1A1F12;">
                                    {{ __('Oder möchtest du als Kund:in dabei sein, sobald es losgeht?') }}
                                </p>
                            </div>
                        </div>

                        {{-- 12. Contact email link --}}
                        <div class="mt-10">
                            <a
                                href="mailto:kontakt@erd-gut.de"
                                class="font-display inline-block pb-2 text-3xl font-normal transition-colors md:text-5xl"
                                style="color: #1A1F12; border-bottom: 1px solid rgba(26, 31, 18, 0.4);"
                                onmouseover="this.style.borderBottomColor='#E85D3A'"
                                onmouseout="this.style.borderBottomColor='rgba(26, 31, 18, 0.4)'"
                            >
                                kontakt@erd-gut.de
                            </a>
                        </div>
                    </div>

                    {{-- Footer: triple star + tagline --}}
                    <div class="mt-auto flex flex-col items-start gap-3 pt-12 sm:pt-16">
                        <div class="flex items-center gap-2 text-lg" style="color: #c53a2b;">
                            <span>&#x2731;</span><span>&#x2731;</span><span>&#x2731;</span>
                        </div>
                        <p class="font-display text-sm italic" style="color: rgba(26, 31, 18, 0.65);">
                            {{ __('In the rhythm of the seasons.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
