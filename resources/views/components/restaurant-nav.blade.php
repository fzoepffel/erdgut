{{-- Persistent nav for the logged-in restaurant (B2B) area. Rendered by
     shop-layout when the `restaurantNav` prop is set to the active key
     ('shop' | 'orders' | 'standing'). Keeps the key destinations one tap away
     on every restaurant page instead of buried as text links on the dashboard. --}}
@props(['active' => null])

@php
    $items = [
        ['key' => 'shop', 'route' => 'app.restaurant.catalog', 'label' => __('restaurant.nav_shop')],
        ['key' => 'orders', 'route' => 'app.restaurant.orders.index', 'label' => __('restaurant.my_requests')],
        ['key' => 'standing', 'route' => 'app.restaurant.standing-orders.index', 'label' => __('restaurant.standing_title')],
    ];
@endphp

<nav class="border-b border-shop-border bg-shop-paper/60" data-testid="restaurant-nav" aria-label="{{ __('restaurant.nav_label') }}">
    <div class="mx-auto flex max-w-[1100px] flex-wrap items-center justify-between gap-x-2 gap-y-1 px-4 py-2 sm:px-6 lg:px-12">
        <div class="flex flex-wrap items-center gap-1">
            @foreach ($items as $item)
                @php $isActive = $active === $item['key']; @endphp
                <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif
                   data-testid="restaurant-nav-{{ $item['key'] }}"
                   class="px-3 py-2 font-mono text-[0.7rem] uppercase tracking-widest transition {{ $isActive ? 'bg-shop-foreground text-shop-background' : 'text-shop-foreground hover:text-shop-primary' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="px-3 py-2 font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted transition hover:text-shop-primary">
                {{ __('restaurant.logout') }}
            </button>
        </form>
    </div>
</nav>
