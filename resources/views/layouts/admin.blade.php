<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- No SumUp hosts: admin pages do not load payment widgets. --}}
        <x-csp />

        <title>{{ config('app.name', 'Laravel') }} — {{ __('Admin') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <x-skip-link />
        <div
            class="min-h-screen border-t-4 border-amber-500 bg-gray-100"
            x-data="{ adminSidebarOpen: false }"
        >
            <div class="flex min-h-screen">
                @include('layouts.admin-sidebar')

                <div class="flex min-h-screen min-w-0 flex-1 flex-col">
                    <header class="flex h-14 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-500 lg:hidden"
                            @click="adminSidebarOpen = true"
                        >
                            <span class="sr-only">{{ __('admin.shell.open_menu') }}</span>
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-amber-900">{{ __('Administration') }}</span>
                        <div class="ml-auto flex items-center gap-3">
                            <button
                                type="button"
                                x-on:click="$dispatch('open-admin-search-palette')"
                                class="hidden items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 sm:inline-flex"
                                aria-label="{{ __('admin.search.trigger_aria') }}"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"/></svg>
                                <span>{{ __('admin.search.trigger_label') }}</span>
                                <span class="ml-1 rounded border border-gray-300 bg-gray-50 px-1 font-mono text-[10px] text-gray-500">⌘K</span>
                            </button>
                            <x-language-selector align="right" />
                            @auth
                                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                    <button type="button" class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100" @click="open = !open">
                                        <span>{{ auth()->user()->name }}</span>
                                        <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-1 w-44 rounded-md border border-gray-200 bg-white py-1 shadow-lg" role="menu">
                                        <form method="POST" action="{{ route('auth.logout') }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                                {{ __('Log out') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endauth
                        </div>
                    </header>

                    @isset($header)
                        <header class="border-b border-gray-200 bg-white shadow-sm">
                            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    <main id="main" class="flex-1" tabindex="-1">
                        {{ $slot }}
                    </main>
                </div>
            </div>

            <x-admin.search-palette />
        </div>
    </body>
</html>
