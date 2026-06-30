<x-shop-layout :title="__('restaurant.pending_title')" :narrow="true">
    <div class="shop-card p-8 text-center">
        <p class="eyebrow text-shop-primary">{{ __('restaurant.pending_eyebrow') }}</p>
        <h1 class="mt-3 font-display text-3xl text-shop-foreground">{{ __('restaurant.pending_title') }}</h1>
        <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-shop-muted">{{ __('restaurant.pending_body') }}</p>

        <form method="POST" action="{{ route('auth.logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="inline-flex h-[3rem] items-center justify-center border border-shop-border px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                {{ __('restaurant.logout') }}
            </button>
        </form>
    </div>
</x-shop-layout>
