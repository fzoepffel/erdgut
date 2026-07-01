<x-shop-layout :title="__('restaurant.submitted_title')" :narrow="true">
    <div class="shop-card p-8 text-center">
        <p class="eyebrow text-shop-primary">{{ __('restaurant.submitted_eyebrow') }}</p>
        <h1 class="mt-3 font-display text-3xl text-shop-foreground">{{ __('restaurant.submitted_title') }}</h1>
        <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-shop-muted">{{ __('restaurant.submitted_body') }}</p>
        <a href="{{ route('public.shop.index') }}" class="mt-8 inline-flex h-[3rem] items-center justify-center border border-shop-border px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
            {{ __('restaurant.back_to_shop') }}
        </a>
    </div>
</x-shop-layout>
