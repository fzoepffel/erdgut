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
        <a href="{{ route('app.restaurant.catalog') }}"
           class="mt-6 inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
            {{ __('restaurant.dashboard_cta') }}
        </a>
    </div>
</x-shop-layout>
