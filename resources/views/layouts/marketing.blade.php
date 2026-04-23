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
        <div class="flex min-h-screen flex-col">
            {{-- Olive-green masthead styled to match the coming-soon page:
                 soft chapter-produce background (#709452) with the onion mark
                 and wordmark both masked in the darker chapter-produce green
                 (#21341d). Same CSS-mask technique coming-soon uses, so we
                 don't need a dedicated white/alt colour asset. --}}
            <header x-data="{ open: false }" class="sticky top-0 z-50 border-b" style="background-color: #709452; border-color: rgba(33, 52, 29, 0.15);">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('public.marketing-home') }}" class="flex items-center gap-3">
                        <span
                            aria-hidden="true"
                            class="block h-10 w-10 shrink-0 sm:h-11 sm:w-11"
                            style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain; mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain;"
                        ></span>
                        <span
                            role="img"
                            aria-label="Erdgut"
                            class="block h-8 w-40 sm:h-9 sm:w-44"
                            style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain; mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain;"
                        ></span>
                    </a>

                    <nav class="hidden items-center gap-10 text-sm font-medium md:flex">
                        <a href="{{ route('public.marketing-home') }}" class="transition hover:opacity-100 {{ request()->routeIs('public.marketing-home') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.marketing-home') ? '#21341d' : 'rgba(33, 52, 29, 0.75)' }};">{{ __('Home') }}</a>
                        <a href="{{ route('public.about') }}" class="transition {{ request()->routeIs('public.about') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.about') ? '#21341d' : 'rgba(33, 52, 29, 0.75)' }};">{{ __('About') }}</a>
                        <a href="{{ route('public.producers') }}" class="transition {{ request()->routeIs('public.producers') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.producers') ? '#21341d' : 'rgba(33, 52, 29, 0.75)' }};">{{ __('Producers') }}</a>
                        <a href="{{ route('public.contact') }}" class="transition {{ request()->routeIs('public.contact') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.contact') ? '#21341d' : 'rgba(33, 52, 29, 0.75)' }};">{{ __('Contact') }}</a>
                    </nav>

                    <div class="hidden items-center gap-3 md:flex" style="color: #21341d;">
                        <x-language-selector />
                        @auth
                            <a href="{{ route('app.dashboard') }}" class="inline-flex items-center justify-center border px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:bg-[#21341d] hover:text-[#709452]" style="border-color: #21341d; color: #21341d;">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('auth.login') }}" class="text-sm font-medium transition" style="color: rgba(33, 52, 29, 0.8);">
                                {{ __('Log in') }}
                            </a>
                            <a href="{{ route('auth.register') }}" class="inline-flex items-center justify-center px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:opacity-90" style="background-color: #21341d; color: #f1e7d6;">
                                {{ __('Sign up') }}
                            </a>
                        @endauth
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center p-2 md:hidden"
                        style="color: #21341d;"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-label="{{ __('Toggle navigation') }}"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div x-show="open" x-transition class="border-t px-4 py-6 md:hidden" style="display: none; border-color: rgba(33, 52, 29, 0.15);">
                    <div class="space-y-3 text-base font-medium">
                        <a href="{{ route('public.marketing-home') }}" class="block" style="color: #21341d;">{{ __('Home') }}</a>
                        <a href="{{ route('public.about') }}" class="block" style="color: rgba(33, 52, 29, 0.8);">{{ __('About') }}</a>
                        <a href="{{ route('public.producers') }}" class="block" style="color: rgba(33, 52, 29, 0.8);">{{ __('Producers') }}</a>
                        <a href="{{ route('public.contact') }}" class="block" style="color: rgba(33, 52, 29, 0.8);">{{ __('Contact') }}</a>
                    </div>
                    <div class="mt-5 border-t pt-5" style="border-color: rgba(33, 52, 29, 0.15);">
                        <div class="mb-4">
                            <x-language-selector align="left" />
                        </div>
                        <div class="space-y-3">
                        @auth
                            <a href="{{ route('app.dashboard') }}" class="inline-flex w-full items-center justify-center border px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:bg-[#21341d] hover:text-[#709452]" style="border-color: #21341d; color: #21341d;">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('auth.login') }}" class="block text-sm font-medium" style="color: rgba(33, 52, 29, 0.8);">{{ __('Log in') }}</a>
                            <a href="{{ route('auth.register') }}" class="inline-flex w-full items-center justify-center px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:opacity-90" style="background-color: #21341d; color: #f1e7d6;">
                                {{ __('Sign up') }}
                            </a>
                        @endauth
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1">
                @yield('content')
            </main>

            <x-marketing-footer />
        </div>

        @stack('scripts')
    </body>
</html>
