{{-- Slim, STICKY app header for the logged-in restaurant (B2B) area — it
     replaces the public marketing header entirely (shop-layout skips
     x-customer-header when `restaurantNav` is set): logo → B2B dashboard,
     the tab rail (wraps on narrow screens, no hidden scrolling), a compact
     language selector and a quiet logout. No marketing links, no "Zum
     Hofladen" CTA — a restaurant preparing an order stays in the app. --}}
@props(['active' => null])

@php
    $items = [
        ['key' => 'dashboard', 'route' => 'app.restaurant.dashboard', 'label' => __('restaurant.nav_overview')],
        ['key' => 'shop', 'route' => 'app.restaurant.catalog', 'label' => __('restaurant.nav_shop')],
        ['key' => 'requests', 'route' => 'app.restaurant.requests.index', 'label' => __('restaurant.nav_requests')],
        ['key' => 'orders', 'route' => 'app.restaurant.orders.index', 'label' => __('restaurant.my_requests')],
        ['key' => 'standing', 'route' => 'app.restaurant.standing-orders.index', 'label' => __('restaurant.nav_standing')],
        ['key' => 'account', 'route' => 'app.restaurant.account.edit', 'label' => __('restaurant.nav_account')],
    ];
@endphp

<nav class="sticky top-0 z-40 border-b border-shop-border bg-shop-paper/95 backdrop-blur" data-testid="restaurant-nav" aria-label="{{ __('restaurant.nav_label') }}">
    <div class="mx-auto flex max-w-[1100px] flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 sm:px-6 lg:px-12">
        <a href="{{ route('app.restaurant.dashboard') }}" class="mr-1 flex shrink-0 items-center" aria-label="Erdgut">
            <span class="inline-block h-7 w-7"
                  style="background-color: #21341d; -webkit-mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain; mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain;"></span>
        </a>
        <div class="-mx-1 flex flex-1 flex-wrap items-center gap-1.5 px-1 py-0.5">
            @foreach ($items as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif
                   data-testid="restaurant-nav-{{ $item['key'] }}"
                   class="whitespace-nowrap rounded-full px-3 py-1.5 text-sm font-medium transition sm:px-4 {{ $isActive ? 'bg-shop-foreground text-shop-background' : 'text-shop-foreground hover:bg-shop-foreground/10' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
        <div class="ml-auto flex shrink-0 items-center gap-1">
            <x-language-selector />
            <form method="POST" action="{{ route('auth.logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-shop-muted transition hover:bg-shop-foreground/10 hover:text-shop-foreground" aria-label="{{ __('restaurant.logout') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                    <span class="hidden lg:inline">{{ __('restaurant.logout') }}</span>
                </button>
            </form>
        </div>
    </div>
</nav>
