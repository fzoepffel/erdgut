<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:; media-src 'self'">

        <title>{{ $title ?? config('app.name', 'Erdgut') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/logo/erdgut-onion-filled.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|fraunces:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="bg-brand-cream font-sans text-brand-ink antialiased">
        <div class="flex min-h-screen flex-col">
            <header x-data="{ open: false }" class="sticky top-0 z-50 border-b border-brand-ink/10 bg-brand-cream/95 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('public.marketing-home') }}" class="flex items-center">
                        <x-application-logo variant="filled" class="h-11 w-auto" />
                    </a>

                    <nav class="hidden items-center gap-10 text-sm font-medium md:flex">
                        <a href="{{ route('public.marketing-home') }}" class="text-brand-ink/80 transition hover:text-brand-ink {{ request()->routeIs('public.marketing-home') ? 'text-brand-ink' : '' }}">{{ __('Home') }}</a>
                        <a href="{{ route('public.about') }}" class="text-brand-ink/80 transition hover:text-brand-ink {{ request()->routeIs('public.about') ? 'text-brand-ink' : '' }}">{{ __('About') }}</a>
                        <a href="{{ route('public.producers') }}" class="text-brand-ink/80 transition hover:text-brand-ink {{ request()->routeIs('public.producers') ? 'text-brand-ink' : '' }}">{{ __('Producers') }}</a>
                        <a href="{{ route('public.contact') }}" class="text-brand-ink/80 transition hover:text-brand-ink {{ request()->routeIs('public.contact') ? 'text-brand-ink' : '' }}">{{ __('Contact') }}</a>
                    </nav>

                    <div class="hidden items-center gap-3 md:flex">
                        <x-language-selector />
                        @auth
                            <x-ghost-link :href="route('app.dashboard')">
                                {{ __('Dashboard') }}
                            </x-ghost-link>
                        @else
                            <a href="{{ route('auth.login') }}" class="text-sm font-medium text-brand-ink/80 transition hover:text-brand-ink">
                                {{ __('Log in') }}
                            </a>
                            <x-solid-link :href="route('auth.register')">
                                {{ __('Sign up') }}
                            </x-solid-link>
                        @endauth
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center p-2 text-brand-ink md:hidden"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-label="Toggle navigation"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                            <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div x-show="open" x-transition class="border-t border-brand-ink/10 px-4 py-6 md:hidden" style="display: none;">
                    <div class="space-y-3 text-base font-medium">
                        <a href="{{ route('public.marketing-home') }}" class="block text-brand-ink">{{ __('Home') }}</a>
                        <a href="{{ route('public.about') }}" class="block text-brand-ink/80">{{ __('About') }}</a>
                        <a href="{{ route('public.producers') }}" class="block text-brand-ink/80">{{ __('Producers') }}</a>
                        <a href="{{ route('public.contact') }}" class="block text-brand-ink/80">{{ __('Contact') }}</a>
                    </div>
                    <div class="mt-5 border-t border-brand-ink/10 pt-5">
                        <div class="mb-4">
                            <x-language-selector align="left" />
                        </div>
                        <div class="space-y-3">
                        @auth
                            <x-ghost-link :href="route('app.dashboard')" class="w-full justify-center">
                                {{ __('Dashboard') }}
                            </x-ghost-link>
                        @else
                            <a href="{{ route('auth.login') }}" class="block text-sm font-medium text-brand-ink/80">{{ __('Log in') }}</a>
                            <x-solid-link :href="route('auth.register')" class="w-full justify-center">
                                {{ __('Sign up') }}
                            </x-solid-link>
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
