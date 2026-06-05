{{-- Standalone, self-contained layout for the preview shop and its
     catalog subpages. Deliberately isolated from the marketing / app /
     onboarding chrome: while the full project stays hidden behind the
     coming-soon page, the shop must not expose any navigation into the
     rest of the site. The only outbound links are the legally required
     privacy / terms notices and a contact email; the logo points back to
     the shop itself, never to the marketing home.

     CSP is relaxed for SumUp's hosted widget (gateway.sumup.com) on the
     pay view via :allow-sumup; everything else stays locked to 'self'. --}}
@props([
    'title' => 'Erdgut — Shop',
    'allowSumup' => false,
    'narrow' => false,
    // Which top-nav item is the current page: 'shop' | 'products' | 'producers'
    // | null. Drives active styling and hides the redundant "Hofladen" link
    // while the guest is already on the shop landing.
    'navActive' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <x-csp :sumup="$allowSumup" />

    <title>{{ $title }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-cream font-sans text-brand-ink antialiased">
    <div class="flex min-h-screen flex-col">
        @php
            // Top-nav items. The shop landing is dropped while already on it.
            $shopNav = [
                ['key' => 'products', 'route' => 'public.shop.products.index', 'label' => __('shop.nav_products')],
                ['key' => 'producers', 'route' => 'public.shop.producers.index', 'label' => __('shop.nav_producers')],
            ];
            if ($navActive !== 'shop') {
                $shopNav[] = ['key' => 'shop', 'route' => 'public.shop.index', 'label' => __('shop.nav_shop')];
            }
        @endphp
        <header
            class="border-b"
            style="background-color: #709452; border-color: rgba(33, 52, 29, 0.15);"
            x-data="{ navOpen: false }"
        >
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('public.shop.index') }}" class="flex items-center gap-3" aria-label="Erdgut">
                    <span
                        aria-hidden="true"
                        class="block h-9 w-9 shrink-0"
                        style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain; mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain;"
                    ></span>
                    <span
                        role="img"
                        aria-label="Erdgut"
                        class="block h-7 w-32 sm:w-36"
                        style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain; mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain;"
                    ></span>
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden items-center gap-8 sm:flex" aria-label="{{ __('shop.nav_label') }}">
                    @foreach ($shopNav as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            data-testid="shop-nav-{{ $item['key'] }}"
                            class="font-mono text-xs uppercase tracking-widest underline-offset-8 transition hover:underline {{ $navActive === $item['key'] ? 'text-[#21341d] underline' : 'text-[#21341d]/70 hover:text-[#21341d]' }}"
                            @if ($navActive === $item['key']) aria-current="page" @endif
                        >{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                {{-- Mobile toggle --}}
                <button
                    type="button"
                    @click="navOpen = true"
                    class="text-[#21341d] sm:hidden"
                    aria-label="{{ __('shop.nav_open') }}"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                    </svg>
                </button>
            </div>

            {{-- Mobile drawer --}}
            <div
                x-show="navOpen"
                x-cloak
                @keydown.escape.window="navOpen = false"
                class="fixed inset-0 z-50 sm:hidden"
            >
                <div class="absolute inset-0 bg-[#21341d]/40" @click="navOpen = false"></div>
                <div
                    x-show="navOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    class="absolute right-0 top-0 flex h-full w-72 max-w-[80%] flex-col bg-brand-cream p-6 shadow-xl"
                >
                    <button
                        type="button"
                        @click="navOpen = false"
                        class="self-end text-brand-ink"
                        aria-label="{{ __('shop.nav_close') }}"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <nav class="mt-8 flex flex-col gap-6" aria-label="{{ __('shop.nav_label') }}">
                        @foreach ($shopNav as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                class="font-display text-2xl text-brand-ink {{ $navActive === $item['key'] ? 'underline underline-offset-4' : '' }}"
                                @if ($navActive === $item['key']) aria-current="page" @endif
                            >{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            @if ($narrow)
                <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
                    {{ $slot }}
                </div>
            @else
                {{ $slot }}
            @endif
        </main>

        <footer class="border-t border-brand-ink/10 py-6 text-center text-xs text-brand-ink/60">
            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2" aria-label="{{ __('Site links') }}">
                <a href="{{ route('public.imprint') }}" class="underline-offset-4 hover:text-brand-ink hover:underline" target="_blank" rel="noopener">{{ __('imprint.footer_link') }}</a>
                <a href="{{ route('public.privacy') }}" class="underline-offset-4 hover:text-brand-ink hover:underline" target="_blank" rel="noopener">{{ __('privacy.footer_link') }}</a>
                <a href="{{ route('public.shop.terms') }}" class="underline-offset-4 hover:text-brand-ink hover:underline" target="_blank" rel="noopener">{{ __('shop_terms.footer_link') }}</a>
                <a href="mailto:kontakt@erd-gut.de" class="underline-offset-4 hover:text-brand-ink hover:underline">kontakt@erd-gut.de</a>
            </nav>
        </footer>
    </div>
</body>
</html>
