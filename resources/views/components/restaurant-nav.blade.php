{{-- Persistent nav for the logged-in restaurant (B2B) area. Rendered by
     shop-layout when the `restaurantNav` prop is set to the active key
     ('shop' | 'orders' | 'standing' | 'dashboard'). A single horizontal
     pill rail — scrollable on mobile (never wraps), with a quiet logout
     pinned right on wider screens. --}}
@props(['active' => null])

@php
    $items = [
        ['key' => 'dashboard', 'route' => 'app.restaurant.dashboard', 'label' => __('restaurant.nav_overview')],
        ['key' => 'shop', 'route' => 'app.restaurant.catalog', 'label' => __('restaurant.nav_shop')],
        ['key' => 'requests', 'route' => 'app.restaurant.requests.index', 'label' => __('restaurant.nav_requests')],
        ['key' => 'orders', 'route' => 'app.restaurant.orders.index', 'label' => __('restaurant.my_requests')],
        ['key' => 'standing', 'route' => 'app.restaurant.standing-orders.index', 'label' => __('restaurant.standing_title')],
    ];
@endphp

<nav class="border-b border-shop-border bg-shop-paper" data-testid="restaurant-nav" aria-label="{{ __('restaurant.nav_label') }}">
    <div class="mx-auto flex max-w-[1100px] items-center gap-3 px-4 py-2.5 sm:px-6 lg:px-12">
        <div class="scrollbar-none -mx-1 flex flex-1 items-center gap-1.5 overflow-x-auto px-1 py-0.5" style="scrollbar-width: none; -webkit-overflow-scrolling: touch;">
            @foreach ($items as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif
                   data-testid="restaurant-nav-{{ $item['key'] }}"
                   class="shrink-0 whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-medium transition {{ $isActive ? 'bg-shop-foreground text-shop-background' : 'text-shop-foreground hover:bg-shop-foreground/10' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
        <form method="POST" action="{{ route('auth.logout') }}" class="shrink-0">
            @csrf
            <button type="submit" class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-shop-muted transition hover:bg-shop-foreground/10 hover:text-shop-foreground" aria-label="{{ __('restaurant.logout') }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" /></svg>
                <span class="hidden sm:inline">{{ __('restaurant.logout') }}</span>
            </button>
        </form>
    </div>
</nav>
