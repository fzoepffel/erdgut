<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://gateway.sumup.com; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; frame-src https://gateway.sumup.com; connect-src 'self' https://gateway.sumup.com https://api.sumup.com https://js.sumup.com; img-src 'self' data: https:">
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
        <div class="min-h-screen flex flex-col" data-testid="onboarding-layout">
            <x-customer-header variant="wizard" />

            <main id="main" class="flex-1" tabindex="-1">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 py-10 grid gap-8 lg:grid-cols-[1fr_320px]">
                    <div>
                        @isset($step)
                            <x-onboarding.progress :step="$step" :intent="$intent ?? 'undecided'" />
                        @endisset

                        <div class="mt-6 border border-brand-ink/15 bg-white p-6 sm:p-8">
                            @yield('content')
                        </div>
                    </div>

                    <aside class="lg:pl-4">
                        @isset($draft)
                            <x-onboarding.summary :draft="$draft" :user="auth()->user()" />
                        @endisset
                    </aside>
                </div>
            </main>
        </div>
    </body>
</html>
