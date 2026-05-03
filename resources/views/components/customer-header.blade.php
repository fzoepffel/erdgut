{{-- W2-01: unified customer-facing header. The olive-green bar with the
     Erdgut mark + wordmark masked in chapter-produce dark green is the
     brand chrome — every customer surface (marketing, onboarding, auth,
     authenticated app) renders this same component. The right-side
     content adapts to the surface via the `$variant` prop:

       - `marketing` (default): public nav (Home / About / Producers /
         Contact) + Log in / Sign up CTA, or "Dashboard" CTA when
         authenticated. Used by `layouts/marketing.blade.php`.
       - `wizard`: just the logo + language selector. The onboarding
         wizard's progress indicator + summary rail carry navigation
         context, so the header stays minimal. Used by
         `layouts/onboarding.blade.php`.
       - `auth`: just the logo + language selector. Login / register /
         verify-email pages don't need extra nav. Used by
         `layouts/guest.blade.php`.
       - `app`: NOT served by this component — `layouts/navigation.blade.php`
         is its own surface (with user dropdown + subscriber nav) but
         shares the visual treatment by reusing the same colors + logo
         masks. --}}
@props([
    'variant' => 'marketing',
])

@php
    // Chapter-produce palette, hex-cast so we can use them inline (Tailwind
    // arbitrary values would also work but inline keeps the parity with the
    // mask CSS that needs the same hex).
    $bg = '#709452';        // chapter-produce light
    $ink = '#21341d';       // chapter-produce dark (logo + nav text)
    $cream = '#f1e7d6';     // brand-cream (button text on dark)
    $inkSubtle = 'rgba(33, 52, 29, 0.75)';
    $homeRoute = match (true) {
        auth()->check() => route('app.dashboard'),
        default => route('public.marketing-home'),
    };
@endphp

<header
    @if ($variant === 'marketing')
        x-data="{ open: false }"
    @endif
    class="sticky top-0 z-50 border-b"
    style="background-color: {{ $bg }}; border-color: rgba(33, 52, 29, 0.15);"
    data-testid="customer-header-{{ $variant }}"
>
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        {{-- Logo: same chapter-produce-ink mask treatment everywhere. --}}
        <a href="{{ $homeRoute }}" class="flex items-center gap-3">
            <span
                aria-hidden="true"
                class="block h-10 w-10 shrink-0 sm:h-11 sm:w-11"
                style="background-color: {{ $ink }}; -webkit-mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain; mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain;"
            ></span>
            <span
                role="img"
                aria-label="Erdgut"
                class="block h-8 w-40 sm:h-9 sm:w-44"
                style="background-color: {{ $ink }}; -webkit-mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain; mask: url('{{ asset('images/logo/erdgut-wordmark-tight.png') }}') no-repeat left center / contain;"
            ></span>
        </a>

        @if ($variant === 'marketing')
            <nav class="hidden items-center gap-10 text-sm font-medium md:flex">
                <a href="{{ route('public.marketing-home') }}" @if (request()->routeIs('public.marketing-home')) aria-current="page" @endif class="transition {{ request()->routeIs('public.marketing-home') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.marketing-home') ? $ink : $inkSubtle }};">{{ __('Home') }}</a>
                <a href="{{ route('public.about') }}" @if (request()->routeIs('public.about')) aria-current="page" @endif class="transition {{ request()->routeIs('public.about') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.about') ? $ink : $inkSubtle }};">{{ __('About') }}</a>
                <a href="{{ route('public.producers') }}" @if (request()->routeIs('public.producers')) aria-current="page" @endif class="transition {{ request()->routeIs('public.producers') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.producers') ? $ink : $inkSubtle }};">{{ __('Producers') }}</a>
                <a href="{{ route('public.contact') }}" @if (request()->routeIs('public.contact')) aria-current="page" @endif class="transition {{ request()->routeIs('public.contact') ? 'font-semibold' : '' }}" style="color: {{ request()->routeIs('public.contact') ? $ink : $inkSubtle }};">{{ __('Contact') }}</a>
            </nav>
        @endif

        <div class="hidden items-center gap-3 md:flex" style="color: {{ $ink }};">
            <x-language-selector />
            @if ($variant === 'marketing')
                @auth
                    <a href="{{ route('app.dashboard') }}" class="inline-flex items-center justify-center border px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:bg-[{{ $ink }}] hover:text-[{{ $bg }}]" style="border-color: {{ $ink }}; color: {{ $ink }};">
                        {{ __('Dashboard') }}
                    </a>
                @else
                    <a href="{{ route('auth.login') }}" class="text-sm font-medium transition" style="color: {{ $inkSubtle }};">
                        {{ __('Log in') }}
                    </a>
                    <a href="{{ route('auth.register') }}" class="inline-flex items-center justify-center px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:opacity-90" style="background-color: {{ $ink }}; color: {{ $cream }};">
                        {{ __('Sign up') }}
                    </a>
                @endauth
            @elseif ($variant === 'wizard')
                {{-- Wizard: a quiet log-out link is the only useful action.
                     The progress indicator below carries the navigation. --}}
                @auth
                    <form method="POST" action="{{ route('auth.logout') }}" class="inline">@csrf
                        <button type="submit" class="text-sm font-medium transition" style="color: {{ $inkSubtle }};">{{ __('Log out') }}</button>
                    </form>
                @endauth
            @endif
        </div>

        @if ($variant === 'marketing')
            <button
                type="button"
                class="inline-flex items-center justify-center p-2 md:hidden"
                style="color: {{ $ink }};"
                @click="open = !open"
                :aria-expanded="open"
                aria-label="{{ __('Toggle navigation') }}"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>

    @if ($variant === 'marketing')
        <div x-show="open" x-transition class="border-t px-4 py-6 md:hidden" style="display: none; border-color: rgba(33, 52, 29, 0.15);">
            <div class="space-y-3 text-base font-medium">
                <a href="{{ route('public.marketing-home') }}" @if (request()->routeIs('public.marketing-home')) aria-current="page" @endif class="block" style="color: {{ request()->routeIs('public.marketing-home') ? $ink : $inkSubtle }};">{{ __('Home') }}</a>
                <a href="{{ route('public.about') }}" @if (request()->routeIs('public.about')) aria-current="page" @endif class="block" style="color: {{ request()->routeIs('public.about') ? $ink : $inkSubtle }};">{{ __('About') }}</a>
                <a href="{{ route('public.producers') }}" @if (request()->routeIs('public.producers')) aria-current="page" @endif class="block" style="color: {{ request()->routeIs('public.producers') ? $ink : $inkSubtle }};">{{ __('Producers') }}</a>
                <a href="{{ route('public.contact') }}" @if (request()->routeIs('public.contact')) aria-current="page" @endif class="block" style="color: {{ request()->routeIs('public.contact') ? $ink : $inkSubtle }};">{{ __('Contact') }}</a>
            </div>
            <div class="mt-5 border-t pt-5" style="border-color: rgba(33, 52, 29, 0.15);">
                <div class="mb-4">
                    <x-language-selector align="left" />
                </div>
                <div class="space-y-3">
                    @auth
                        <a href="{{ route('app.dashboard') }}" class="inline-flex w-full items-center justify-center border px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:bg-[{{ $ink }}] hover:text-[{{ $bg }}]" style="border-color: {{ $ink }}; color: {{ $ink }};">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('auth.login') }}" class="block text-sm font-medium" style="color: {{ $inkSubtle }};">{{ __('Log in') }}</a>
                        <a href="{{ route('auth.register') }}" class="inline-flex w-full items-center justify-center px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:opacity-90" style="background-color: {{ $ink }}; color: {{ $cream }};">
                            {{ __('Sign up') }}
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    @endif
</header>
