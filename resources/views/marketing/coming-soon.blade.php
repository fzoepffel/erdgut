<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:">

        <title>{{ __('Erdgut — Bald geht\'s los') }}</title>
        <meta name="description" content="{{ __('Frisches Gemüse aus Berliner Bauernhand. Bald kannst du dich selbst davon überzeugen.') }}">

        <link rel="icon" type="image/png" href="{{ asset('images/logo/erdgut-onion-filled.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-brand-cream font-sans text-brand-ink antialiased">
        <div
            data-testid="coming-soon"
            class="flex min-h-screen flex-col items-center justify-center px-6 py-16 text-center"
        >
            <x-application-logo
                variant="filled"
                :withWordmark="false"
                class="h-40 w-auto sm:h-56 md:h-72"
            />

            <h1 class="mt-10 font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl md:text-6xl">
                {{ __('Frisches Gemüse ist unterwegs.') }}
            </h1>

            <p class="mt-5 max-w-xl text-base text-brand-ink/70 sm:text-lg">
                {{ __('Noch sind wir auf dem Feld und ernten das Beste der Saison. In Kürze kannst du dich hier selbst davon überzeugen.') }}
            </p>

            <div class="mt-12 flex flex-col items-center gap-3 text-sm text-brand-ink/70 sm:flex-row sm:gap-8">
                <a
                    href="https://www.instagram.com/erdgut.berlin/"
                    rel="noopener noreferrer"
                    target="_blank"
                    class="font-medium underline-offset-4 transition hover:text-brand-ink hover:underline"
                >
                    {{ __('Folge uns auf Instagram') }}
                </a>
                <a
                    href="mailto:hallo@erdgut.de"
                    class="font-medium underline-offset-4 transition hover:text-brand-ink hover:underline"
                >
                    hallo@erdgut.de
                </a>
            </div>

            <p class="mt-16 text-xs uppercase tracking-eyebrow text-brand-ink/50">
                {{ __('Erdgut Kollektiv · Berlin') }}
            </p>
        </div>
    </body>
</html>
