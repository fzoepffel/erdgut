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
        $variant === 'marketing' => route('public.marketing-home'),
        default => route('public.home'),
    };

    // Nav items per surface. The `marketing` (box) surface keeps the full
    // account-aware nav; the `shop` surface only links to the shop pages
    // (plus the shop landing + About) and carries no sign-up / sign-in.
    $navItems = match ($variant) {
        'marketing' => [
            ['route' => 'public.marketing-home', 'active' => 'public.marketing-home', 'label' => __('Home')],
            ['route' => 'public.about', 'active' => 'public.about', 'label' => __('About')],
            ['route' => 'public.producers', 'active' => 'public.producers', 'label' => __('Producers')],
            ['route' => 'public.products.index', 'active' => 'public.products.*', 'label' => __('Products')],
            ['route' => 'public.shop.index', 'active' => 'public.shop.*', 'label' => __('Shop')],
            ['route' => 'public.contact', 'active' => 'public.contact', 'label' => __('Contact')],
        ],
        // The Hofladen is the primary conversion goal; it is not a plain nav
        // link but a solid CTA button on the right (see below).
        'shop' => [
            ['route' => 'public.home', 'active' => 'public.home', 'label' => __('Home')],
            ['route' => 'public.shop-about', 'active' => 'public.shop-about', 'label' => __('About')],
            ['route' => 'public.shop.producers.index', 'active' => 'public.shop.producers.*', 'label' => __('shop.nav_producers')],
            ['route' => 'public.shop.products.index', 'active' => 'public.shop.products.*', 'label' => __('shop.nav_products')],
            ['route' => 'public.shop-contact', 'active' => 'public.shop-contact', 'label' => __('Contact')],
        ],
        default => [],
    };

    $hasNav = in_array($variant, ['marketing', 'shop'], true);
@endphp

<header
    @if ($hasNav)
        x-data="{ open: false }"
    @endif
    class="sticky top-0 z-50 border-b"
    style="background-color: {{ $bg }}; border-color: rgba(33, 52, 29, 0.15);"
    data-testid="customer-header-{{ $variant }}"
>
    {{-- Utility strip: a thin, subtly-toned band above the consumer nav holding
         the "other audience" (B2B / Gastronomie) + account access in a compact
         "Konto" menu, so they never compete with the primary "Zum Hofladen" CTA.
         Desktop only; the mobile menu carries the same links. --}}
    @if ($variant === 'shop')
        <div class="hidden border-b md:block" style="background-color: rgba(33, 52, 29, 0.07); border-color: rgba(33, 52, 29, 0.10);">
            <div class="mx-auto flex max-w-7xl items-center justify-end gap-4 px-4 py-1 text-[11px] sm:px-6 lg:px-8" style="color: {{ $inkSubtle }};">
                <x-language-selector />
                <div class="relative" x-data="{ konto: false }" @keydown.escape="konto = false">
                    <button type="button" @click="konto = !konto" :aria-expanded="konto" data-testid="header-account-menu"
                            class="inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 font-medium transition hover:opacity-80" style="color: {{ $inkSubtle }};">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.68 0-5.22-.584-7.5-1.632Z" /></svg>
                        {{ __('restaurant.account_menu') }}
                        <svg class="h-3 w-3 transition" :class="konto && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="konto" x-cloak x-transition.origin.top @click.outside="konto = false"
                         class="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-xl border border-brand-ink/10 bg-brand-cream shadow-xl">
                        <a href="{{ route('restaurant.landing') }}" class="flex items-start gap-3 px-4 py-3 transition hover:bg-brand-ink/5" data-testid="header-restaurant-link">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-chapter-produce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 21h19.5M6.75 21V6.75A1.5 1.5 0 0 1 8.25 5.25h7.5a1.5 1.5 0 0 1 1.5 1.5V21M9.75 8.25h.75m-.75 3h.75m3-3h.75m-.75 3h.75m-4.5 9.75v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>
                            <span>
                                <span class="block text-sm font-semibold text-brand-ink">{{ __('restaurant.nav_public') }}</span>
                                <span class="block text-xs text-brand-ink/60">{{ __('restaurant.account_menu_restaurant_sub') }}</span>
                            </span>
                        </a>
                        <div class="border-t border-brand-ink/10"></div>
                        <a href="{{ route('auth.login') }}" class="flex items-start gap-3 px-4 py-3 transition hover:bg-brand-ink/5">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-chapter-produce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                            <span>
                                <span class="block text-sm font-semibold text-brand-ink">{{ __('Log in') }}</span>
                                <span class="block text-xs text-brand-ink/60">{{ __('restaurant.account_menu_login_sub') }}</span>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

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

        @if ($hasNav)
            <nav class="hidden items-center gap-10 text-sm font-medium md:flex">
                @foreach ($navItems as $item)
                    @php $isActive = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif class="transition {{ $isActive ? 'font-semibold' : '' }}" style="color: {{ $isActive ? $ink : $inkSubtle }};">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        @endif

        @if ($variant !== 'wizard')
            <div class="hidden items-center gap-3 md:flex" style="color: {{ $ink }};">
                {{-- Shop keeps only the primary CTA here; language + B2B/login
                     sit in the utility strip above. --}}
                @if ($variant !== 'shop')
                    <x-language-selector />
                @endif
                @if ($variant === 'shop')
                    <a href="{{ route('public.shop.index') }}" @if (request()->routeIs('public.shop.index')) aria-current="page" @endif class="inline-flex items-center justify-center gap-2 px-5 py-2 text-xs font-semibold uppercase tracking-eyebrow transition hover:opacity-90" style="background-color: {{ $ink }}; color: {{ $cream }};" data-testid="shop-nav-cta">
                        {{ __('Visit the shop') }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                @endif
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
                @endif
            </div>
        @endif
        {{-- B1/B9 (UX notes): wizard chrome is intentionally nav-free
             on every width. The logout link used to live here, but a
             single misclick during onboarding logged the customer out,
             and the verified-middleware then blocked them from resuming
             (see B3). The language selector also lived on desktop only,
             which broke the focused-shell intent — both viewports now
             render just the logo. The draft survives a closed tab; the
             user can resume via /onboard, and language can be switched
             from the public marketing pages before entering onboarding. --}}

        @if ($hasNav)
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

    @if ($hasNav)
        <div x-show="open" x-transition class="border-t px-4 py-6 md:hidden" style="display: none; border-color: rgba(33, 52, 29, 0.15);">
            <div class="space-y-3 text-base font-medium">
                @foreach ($navItems as $item)
                    @php $isActive = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif class="block" style="color: {{ $isActive ? $ink : $inkSubtle }};">{{ $item['label'] }}</a>
                @endforeach
            </div>
            <div class="mt-5 border-t pt-5" style="border-color: rgba(33, 52, 29, 0.15);">
                <div class="mb-4">
                    <x-language-selector align="left" />
                </div>
                @if ($variant === 'shop')
                    <a href="{{ route('restaurant.landing') }}" class="mb-3 block text-sm font-medium" style="color: {{ $inkSubtle }};">{{ __('restaurant.nav_public') }}</a>
                    <a href="{{ route('auth.login') }}" class="mb-3 block text-sm font-medium" style="color: {{ $inkSubtle }};">{{ __('Log in') }}</a>
                    <a href="{{ route('public.shop.index') }}" class="inline-flex w-full items-center justify-center gap-2 px-5 py-2.5 text-xs font-semibold uppercase tracking-eyebrow transition hover:opacity-90" style="background-color: {{ $ink }}; color: {{ $cream }};" data-testid="shop-nav-cta-mobile">
                        {{ __('Visit the shop') }}
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                @endif
                @if ($variant === 'marketing')
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
                @endif
            </div>
        </div>
    @endif
</header>
