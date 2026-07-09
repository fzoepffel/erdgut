<x-shop-layout :title="__('restaurant.done_title')" :narrow="true">
    <div class="shop-card p-8 text-center">
        @if ($result === 'verified')
            <p class="eyebrow text-shop-primary">{{ __('restaurant.done_verified_eyebrow') }}</p>
            <h1 class="mt-3 font-display text-3xl text-shop-foreground">{{ __('restaurant.done_verified_title') }}</h1>
            <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-shop-muted">{{ __('restaurant.done_verified_body') }}</p>
        @elseif ($result === 'rejected')
            <p class="eyebrow text-chapter-meat">{{ __('restaurant.done_rejected_eyebrow') }}</p>
            <h1 class="mt-3 font-display text-3xl text-shop-foreground">{{ __('restaurant.done_rejected_title') }}</h1>
            <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-shop-muted">{{ __('restaurant.done_rejected_body') }}</p>
        @else
            <p class="eyebrow text-shop-muted">{{ __('restaurant.done_already_eyebrow') }}</p>
            <h1 class="mt-3 font-display text-3xl text-shop-foreground">{{ __('restaurant.done_already_title') }}</h1>
            <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-shop-muted">{{ __('restaurant.done_already_body') }}</p>
        @endif
    </div>
</x-shop-layout>
