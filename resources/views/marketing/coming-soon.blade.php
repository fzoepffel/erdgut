<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:">

        <title>{{ __('Erdgut — Bald geht\'s los') }}</title>
        <meta name="description" content="{{ __('Frisches Gemüse aus Berliner Bauernhand. Bald kannst du dich selbst davon überzeugen.') }}">

        <link rel="icon" type="image/png" href="{{ asset('images/logo/erdgut-mark.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-brand-cream font-sans text-brand-ink antialiased">
        <div class="absolute right-4 top-4 sm:right-6 sm:top-6">
            <x-language-selector variant="compact" />
        </div>
        <div
            data-testid="coming-soon"
            class="flex min-h-screen flex-col items-center justify-center px-6 py-16 text-center"
        >
            <x-application-logo
                variant="filled"
                class="h-32 w-auto sm:h-40 md:h-48"
            />

            <h1 class="mt-10 font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl md:text-6xl">
                {{ __('Frisches Gemüse ist unterwegs.') }}
            </h1>

            <p class="mt-5 max-w-xl text-base text-brand-ink/70 sm:text-lg">
                {{ __('Noch sind wir auf dem Feld und ernten das Beste der Saison. In Kürze kannst du dich hier selbst davon überzeugen.') }}
            </p>

            <div class="mt-14 max-w-xl space-y-3 text-sm text-brand-ink/70 sm:text-base">
                <p>{{ __('Du bist Produzent:in und möchtest deine Erzeugnisse über uns anbieten?') }}</p>
                <p>{{ __('Oder möchtest du als Kund:in dabei sein, sobald es losgeht?') }}</p>
                <p class="pt-2">
                    {{ __('Schreib uns gern:') }}
                    <a
                        href="mailto:kontakt@erd-gut.de"
                        class="font-semibold text-brand-ink underline-offset-4 transition hover:underline"
                    >
                        kontakt@erd-gut.de
                    </a>
                </p>
            </div>

            <p class="mt-16 text-xs uppercase tracking-eyebrow text-brand-ink/50">
                Erdgut
            </p>
        </div>
    </body>
</html>
