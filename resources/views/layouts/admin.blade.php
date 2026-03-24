<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- No SumUp hosts: admin pages do not load payment widgets. --}}
        <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data: https:;">

        <title>{{ config('app.name', 'Laravel') }} — {{ __('Admin') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div
            class="min-h-screen border-t-4 border-amber-500 bg-gray-100"
            x-data="{ adminSidebarOpen: false }"
        >
            <div class="flex min-h-screen">
                @include('layouts.admin-sidebar')

                <div class="flex min-h-screen min-w-0 flex-1 flex-col">
                    <header class="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4 lg:hidden">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            @click="adminSidebarOpen = true"
                        >
                            <span class="sr-only">{{ __('admin.shell.open_menu') }}</span>
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-amber-900">{{ __('Administration') }}</span>
                    </header>

                    @isset($header)
                        <header class="border-b border-gray-200 bg-white shadow-sm">
                            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main class="flex-1">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
