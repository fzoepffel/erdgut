{{-- Standalone, self-contained layout for the shop and its catalog
     subpages. The shop is now the published public front, so its header
     shares the same nav as the shop-marketing surface (Startseite ·
     Über uns · Höfe · Produkte · Hofladen · Kontakt) and the logo points
     back to the landing page. It still carries NO account chrome
     (sign-up / sign-in stay box-only for now) and never links into the
     parked box pages or the app/onboarding shells.

     CSP is relaxed for SumUp's hosted widget (gateway.sumup.com) on the
     pay view via :allow-sumup; everything else stays locked to 'self'. --}}
@props([
    'title' => 'Erdgut — Shop',
    'allowSumup' => false,
    'narrow' => false,
    // Which top-nav item is the current page: 'shop' | 'products' | 'producers'
    // | null. Drives active styling. The landing / About / Kontakt items
    // live on the shop-marketing surface and are never active here.
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
        {{-- Shared shop-surface header (identical to the shop landing /
             About / Kontakt pages). Active state is derived from the route,
             so the legacy `navActive` prop is no longer needed. --}}
        <x-customer-header variant="shop" />

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
