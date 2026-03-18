<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Erdgut') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col">
            <header x-data="{ open: false }" class="border-b border-gray-100 bg-white/95 backdrop-blur">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('public.home') }}" class="text-lg font-bold tracking-tight text-emerald-700">
                        Erdgut
                    </a>

                    <nav class="hidden items-center gap-6 text-sm font-medium md:flex">
                        <a href="{{ route('public.home') }}" class="text-gray-700 transition hover:text-emerald-700">Home</a>
                        <a href="{{ route('public.about') }}" class="text-gray-500 transition hover:text-emerald-700">About</a>
                        <a href="{{ route('public.producers') }}" class="text-gray-500 transition hover:text-emerald-700">Producers</a>
                        <a href="{{ route('public.contact') }}" class="text-gray-500 transition hover:text-emerald-700">Contact</a>
                    </nav>

                    <div class="hidden items-center gap-3 md:flex">
                        @auth
                            <a href="{{ route('app.dashboard') }}" class="rounded-md border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('auth.login') }}" class="text-sm font-semibold text-gray-700 transition hover:text-emerald-700">
                                Log in
                            </a>
                            <a href="{{ route('auth.register') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">
                                Sign up
                            </a>
                        @endauth
                    </div>

                    <button
                        type="button"
                        class="rounded-md border border-gray-200 p-2 text-gray-600 md:hidden"
                        @click="open = !open"
                        :aria-expanded="open"
                        aria-label="Toggle navigation"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                <div x-show="open" x-transition class="border-t border-gray-100 px-4 py-4 md:hidden">
                    <div class="space-y-3 text-sm font-medium">
                        <a href="{{ route('public.home') }}" class="block text-gray-700">Home</a>
                        <a href="{{ route('public.about') }}" class="block text-gray-500">About</a>
                        <a href="{{ route('public.producers') }}" class="block text-gray-500">Producers</a>
                        <a href="{{ route('public.contact') }}" class="block text-gray-500">Contact</a>
                    </div>
                    <div class="mt-4 space-y-3 border-t border-gray-100 pt-4">
                        @auth
                            <a href="{{ route('app.dashboard') }}" class="block rounded-md border border-emerald-700 px-4 py-2 text-center text-sm font-semibold text-emerald-700">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('auth.login') }}" class="block text-sm font-semibold text-gray-700">Log in</a>
                            <a href="{{ route('auth.register') }}" class="block rounded-md bg-emerald-700 px-4 py-2 text-center text-sm font-semibold text-white">
                                Sign up
                            </a>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="flex-1">
                @yield('content')
            </main>

            <footer class="border-t border-gray-100 bg-gray-50">
                <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <p>&copy; {{ date('Y') }} Erdgut. Farm-to-consumer produce in Berlin.</p>
                    <p>Transparent sourcing. Fair pricing. Weekly delivery.</p>
                </div>
            </footer>
        </div>
    </body>
</html>
