<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:; media-src 'self'">

        <title>{{ $title ?? config('app.name', 'Erdgut') }}</title>
        <meta name="description" content="{{ $description ?? __('Frisches Gemüse aus Berliner Bauernhand.') }}">

        {{-- Open Graph / social share (override via @push('head')) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Erdgut">
        <meta property="og:title" content="{{ $title ?? config('app.name', 'Erdgut') }}">
        <meta property="og:description" content="{{ $description ?? __('Frisches Gemüse aus Berliner Bauernhand.') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/og/erdgut-og-default.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:locale" content="{{ app()->getLocale() === 'de' ? 'de_DE' : (app()->getLocale() === 'fr' ? 'fr_FR' : 'en_US') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ asset('images/og/erdgut-og-default.png') }}">

        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/erdgut-favicon-16.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo/erdgut-favicon-192.png') }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/logo/erdgut-favicon-512.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="bg-brand-cream font-sans text-brand-ink antialiased">
        <x-skip-link />
        <div class="flex min-h-screen flex-col">
            <x-customer-header variant="marketing" />

            <main id="main" class="flex-1" tabindex="-1">
                @yield('content')
            </main>

            <x-marketing-footer />
        </div>

        @stack('scripts')
    </body>
</html>
