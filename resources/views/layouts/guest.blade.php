<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-csp />

        <title>{{ config('app.name', 'Erdgut') }} — {{ __('Account') }}</title>

        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/erdgut-favicon-16.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
        <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo/erdgut-favicon-192.png') }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/logo/erdgut-favicon-512.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-brand-cream font-sans text-brand-ink antialiased">
        <x-skip-link />
        <div class="flex min-h-screen flex-col" data-testid="guest-layout">
            <x-customer-header variant="auth" />
            <main id="main" tabindex="-1" class="flex flex-1 flex-col items-center justify-center px-4 py-14 sm:px-6 sm:py-20">
                <div class="mb-10 text-center">
                    <p class="max-w-sm text-sm leading-relaxed text-brand-ink/70" data-testid="guest-layout-tagline">{{ __('Sign in or create an account to manage your box.') }}</p>
                </div>

                <div class="w-full max-w-md border border-brand-ink/15 bg-brand-cream/60 px-8 py-10 sm:px-10">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-brand-ink/10 py-6 text-center text-xs text-brand-ink/60">
                <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2" aria-label="{{ __('Site links') }}">
                    <a href="{{ route('public.home') }}" class="font-medium text-brand-ink underline-offset-4 hover:underline">{{ __('Home') }}</a>
                    <a href="{{ route('public.about') }}" class="text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">{{ __('About') }}</a>
                    <a href="{{ route('public.contact') }}" class="text-brand-ink/70 underline-offset-4 hover:text-brand-ink hover:underline">{{ __('Contact') }}</a>
                </nav>
            </footer>
        </div>
    </body>
</html>
