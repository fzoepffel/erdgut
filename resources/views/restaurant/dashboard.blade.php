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
    </div>
</x-shop-layout>
