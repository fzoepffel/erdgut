<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:">

        <title>{{ config('app.name', 'Erdgut') }} — {{ __('Account') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gradient-to-b from-emerald-50 to-gray-50 font-sans text-gray-900 antialiased">
        <div class="flex min-h-screen flex-col" data-testid="guest-layout">
            <main class="flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-6 sm:py-12">
                <div class="mb-8 text-center">
                    <a
                        href="{{ route('public.home') }}"
                        class="inline-flex flex-col items-center gap-2 rounded-lg outline-none ring-emerald-600 focus-visible:ring-2 focus-visible:ring-offset-2"
                        data-testid="guest-layout-home"
                    >
                        <x-application-logo class="h-12 w-12 fill-current text-emerald-700 sm:h-14 sm:w-14" />
                        <span class="text-lg font-bold tracking-tight text-emerald-800">{{ config('app.name', 'Erdgut') }}</span>
                    </a>
                    <p class="mt-2 text-sm text-gray-600">{{ __('Sign in or create an account to manage your box.') }}</p>
                </div>

                <div class="w-full max-w-md overflow-hidden rounded-xl border border-gray-200 bg-white px-6 py-8 shadow-sm sm:px-8">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-emerald-100/80 bg-white/80 py-6 text-center text-sm text-gray-600">
                <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2" aria-label="{{ __('Site links') }}">
                    <a href="{{ route('public.home') }}" class="font-medium text-emerald-800 underline-offset-4 hover:underline">{{ __('Home') }}</a>
                    <a href="{{ route('public.about') }}" class="text-gray-600 underline-offset-4 hover:text-emerald-800 hover:underline">{{ __('About') }}</a>
                    <a href="{{ route('public.contact') }}" class="text-gray-600 underline-offset-4 hover:text-emerald-800 hover:underline">{{ __('Contact') }}</a>
                </nav>
            </footer>
        </div>
    </body>
</html>
