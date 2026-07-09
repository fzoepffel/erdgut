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
    // When set (to 'shop' | 'orders' | 'standing' | any truthy key), renders the
    // persistent restaurant (B2B) nav below the header, full-width.
    'restaurantNav' => null,
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
        @if ($restaurantNav)
            {{-- Logged-in B2B area: the slim sticky app header REPLACES the
                 public marketing header (no Startseite/Höfe/Zum-Hofladen
                 noise while preparing an order). --}}
            <x-restaurant-nav :active="$restaurantNav" />
        @else
            {{-- Shared shop-surface header (identical to the shop landing /
                 About / Kontakt pages). --}}
            <x-customer-header variant="shop" />
        @endif

        <main id="main" class="flex-1">
            @if ($narrow)
                <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
                    {{ $slot }}
                </div>
            @else
                {{ $slot }}
            @endif
        </main>

        <x-marketing-footer />
    </div>
</body>
</html>
