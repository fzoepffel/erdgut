<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:">

        <title>{{ __('Erdgut — Bald geht\'s los') }}</title>
        <meta name="description" content="{{ __('Frisches Gemüse aus Berliner Bauernhand. Bald kannst du dich selbst davon überzeugen.') }}">

        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo/erdgut-favicon-192.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" style="background-color: #709452;">
        <div
            data-testid="coming-soon"
            class="min-h-screen w-full"
        >
            <div class="mx-auto grid min-h-screen w-full max-w-[1600px] grid-cols-1 md:grid-cols-[minmax(0,_1fr)_minmax(0,_2fr)]">
                {{-- Left column: catalogue-style cabbage illustration stripe
                     (image is pre-cropped to the cabbage texture only — no
                     baked-in text to worry about) --}}
                <div class="relative hidden min-h-[320px] overflow-hidden md:block">
                    <img
                        src="{{ asset('images/chapters/cabbage.png') }}"
                        alt=""
                        aria-hidden="true"
                        class="absolute inset-0 h-full w-full select-none object-cover"
                    />
                </div>
                <div class="relative block h-40 overflow-hidden sm:h-48 md:hidden">
                    <img
                        src="{{ asset('images/chapters/cabbage.png') }}"
                        alt=""
                        aria-hidden="true"
                        class="absolute inset-0 h-full w-full select-none object-cover object-center"
                    />
                </div>

                {{-- Right column: wordmark + content --}}
                <div class="flex flex-col px-6 py-10 sm:px-10 sm:py-14 md:px-14 md:py-16 lg:px-20">
                    <div class="flex items-start justify-between gap-4">
                        {{--
                            Erdgut wordmark rendered via CSS mask on the brand's official
                            wordmark PNG (pre-cropped to the tight glyph bounds, aspect
                            ~5:1), filled in dark chapter-produce green so it reads
                            against the olive-green page background.
                        --}}
                        <div
                            role="img"
                            aria-label="Erdgut"
                            class="h-16 w-80 sm:h-20 sm:w-96 md:h-28 md:w-[34rem] lg:h-32 lg:w-[40rem]"
                            style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain; mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain;"
                        ></div>
                        <div class="pt-2">
                            <x-language-selector variant="compact" />
                        </div>
                    </div>

                    <div class="mt-10 sm:mt-14 md:mt-16">
                        <p class="eyebrow" style="color: #21341d;">{{ __('Bald geht\'s los') }}</p>
                        <p
                            class="mt-4 font-display text-2xl font-semibold leading-snug sm:text-3xl md:text-4xl"
                            style="color: #21341d;"
                        >
                            {{ __('Frisches Gemüse ist unterwegs.') }}
                        </p>
                        <p
                            class="mt-5 max-w-xl font-editorial text-base italic leading-relaxed sm:text-lg"
                            style="color: rgba(33, 52, 29, 0.85);"
                        >
                            {{ __('Noch sind wir auf dem Feld und ernten das Beste der Saison. In Kürze kannst du dich hier selbst davon überzeugen.') }}
                        </p>
                    </div>

                    <div class="mt-10 max-w-xl border-t pt-8 sm:mt-14" style="border-color: rgba(33, 52, 29, 0.25);">
                        <p class="eyebrow" style="color: #21341d;">{{ __('Schreib uns gern') }}</p>
                        <p class="mt-4 text-base leading-relaxed sm:text-base" style="color: rgba(33, 52, 29, 0.9);">
                            {{ __('Du bist Produzent:in und möchtest deine Erzeugnisse über uns anbieten?') }}
                        </p>
                        <p class="mt-3 text-base leading-relaxed sm:text-base" style="color: rgba(33, 52, 29, 0.9);">
                            {{ __('Oder möchtest du als Kund:in dabei sein, sobald es losgeht?') }}
                        </p>
                        <p class="mt-6">
                            <a
                                href="mailto:kontakt@erd-gut.de"
                                class="font-display text-xl font-semibold underline decoration-2 underline-offset-4 transition hover:opacity-80 sm:text-2xl"
                                style="color: #21341d;"
                            >
                                kontakt@erd-gut.de
                            </a>
                        </p>
                    </div>

                    <div class="mt-auto flex flex-col items-start gap-3 pt-12 sm:pt-16">
                        <div class="flex items-center gap-2 text-lg" style="color: #c53a2b;">
                            <span>&#x2731;</span><span>&#x2731;</span><span>&#x2731;</span>
                        </div>
                        <p class="font-editorial text-sm italic" style="color: rgba(33, 52, 29, 0.65);">
                            {{ __('In the rhythm of the seasons.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
