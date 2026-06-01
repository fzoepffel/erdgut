{{-- Standalone layout for the spargel trial-shop. Decoupled from the
     onboarding / app / marketing layouts on purpose: the trial shop is
     a single self-contained checkout flow that should not pick up
     wizard chrome, navigation, or auth assumptions.

     CSP relaxed for SumUp's hosted widget (gateway.sumup.com) on the
     pay view; form + return-url stay locked to 'self'. --}}
@props([
    'title' => 'Erdgut — Spargel',
    'allowSumup' => false,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-csp :sumup="$allowSumup" />

    <title>{{ $title }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/erdgut-favicon-180.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-cream font-sans text-brand-ink antialiased">
    <header
        class="border-b"
        style="background-color: #709452; border-color: rgba(33, 52, 29, 0.15);"
    >
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('public.home') }}" class="flex items-center gap-3">
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
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
        {{ $slot }}
    </main>

    <footer class="border-t border-brand-ink/10 py-6 text-center text-xs text-brand-ink/60">
        <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2" aria-label="{{ __('Site links') }}">
            <a href="{{ route('public.imprint') }}" class="underline-offset-4 hover:text-brand-ink hover:underline">{{ __('imprint.footer_link') }}</a>
            <a href="{{ route('public.privacy') }}" class="underline-offset-4 hover:text-brand-ink hover:underline">{{ __('privacy.footer_link') }}</a>
            <a href="mailto:kontakt@erd-gut.de" class="underline-offset-4 hover:text-brand-ink hover:underline">kontakt@erd-gut.de</a>
        </nav>
    </footer>
</body>
</html>
