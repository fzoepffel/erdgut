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
                <x-language-selector />
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
