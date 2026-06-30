<x-shop-layout :title="__('restaurant.dashboard_title')" :narrow="true">
    <div class="flex items-center justify-between">
        <div>
            <p class="eyebrow text-shop-primary">{{ __('restaurant.dashboard_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.dashboard_title') }}</h1>
        </div>
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted transition hover:text-shop-primary">
                {{ __('restaurant.logout') }}
            </button>
        </form>
    </div>

    <div class="shop-card mt-8 p-8">
        <p class="text-base leading-relaxed text-shop-muted">{{ __('restaurant.dashboard_placeholder') }}</p>
        <div class="mt-6 flex flex-wrap items-center gap-4">
            <a href="{{ route('app.restaurant.catalog') }}"
               class="inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                {{ __('restaurant.dashboard_cta') }}
            </a>
            <a href="{{ route('app.restaurant.orders.index') }}"
               class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">
                {{ __('restaurant.my_requests') }}
            </a>
            <a href="{{ route('app.restaurant.standing-orders.index') }}"
               class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">
                {{ __('restaurant.standing_title') }}
            </a>
        </div>
    </div>
</x-shop-layout>
