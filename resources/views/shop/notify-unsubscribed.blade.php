<x-shop-layout :title="__('shop.reduced_offer.unsubscribed_title').' — Erdgut'" nav-active="shop" narrow>
    <div class="text-center">
        <p class="eyebrow text-brand-moss">{{ __('shop.reduced_offer.eyebrow') }}</p>
        <h1 class="mt-4 font-display text-3xl font-semibold text-brand-ink sm:text-4xl">
            {{ __('shop.reduced_offer.unsubscribed_title') }}
        </h1>
        <p class="mx-auto mt-4 max-w-lg text-base leading-relaxed text-brand-ink/70">
            {{ __('shop.reduced_offer.unsubscribed_body') }}
        </p>
        <p class="mt-8">
            <a href="{{ route('public.shop.index') }}" class="inline-flex items-center gap-2 border-b border-brand-ink pb-0.5 text-sm text-brand-ink transition hover:opacity-70">
                {{ __('shop.reduced_offer.back_to_shop') }}
                <span aria-hidden="true">&rarr;</span>
            </a>
        </p>
    </div>
</x-shop-layout>
