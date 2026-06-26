{{-- Floating basket chip — sticks to the bottom-right of the storefront
     while the customer shops, reads from the basket object on the
     parent `shopStorefront` Alpine component. Tapping it opens a small
     summary panel that lists every line and the running estimate, with
     a CTA that submits the basket and advances straight to step 2
     (checkout details). The button sits outside the checkout <form> but
     drives it via the HTML5 `form` attribute, so no extra JS is needed.

     Hidden when the cart is empty; pulses briefly when a line is added
     so the customer sees where it landed. --}}
<div
    x-show="basket.itemCount > 0"
    x-cloak
    @click.outside="chipOpen = false"
    @keydown.escape.window="chipOpen = false"
    class="fixed bottom-6 right-6 z-40 sm:bottom-8 sm:right-8"
    data-testid="shop-basket-chip"
>
    <button
        type="button"
        @click="chipOpen = !chipOpen"
        :class="chipPulse ? 'ring-2 ring-shop-primary ring-offset-2 ring-offset-shop-background' : ''"
        class="flex items-center gap-3 border border-shop-primary bg-shop-primary px-5 py-3 font-mono text-xs uppercase tracking-widest text-shop-primary-foreground shadow-lg transition hover:bg-shop-primary-hover"
        :aria-expanded="chipOpen ? 'true' : 'false'"
        aria-controls="shop-basket-chip-panel"
    >
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l3-7H6.4M7 13l-2-7M7 13l-.5 2.5h12M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z" />
        </svg>
        <span>{{ __('shop.basket_chip_label') }}</span>
        <span class="tabular-nums" x-text="basket.itemCount"></span>
        <span class="opacity-60">·</span>
        <span class="tabular-nums" x-text="itemsTotalLabel()"></span>
    </button>

    <div
        x-show="chipOpen"
        x-cloak
        x-transition.opacity
        id="shop-basket-chip-panel"
        role="dialog"
        aria-label="{{ __('shop.basket_chip_label') }}"
        class="absolute bottom-full right-0 mb-3 w-[min(20rem,calc(100vw-3rem))] border border-shop-border bg-shop-background p-4 shadow-xl"
    >
        <p class="eyebrow text-shop-primary">{{ __('shop.basket_chip_heading') }}</p>
        <ul class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
            <template x-for="line in cartLines" :key="line.id">
                <li class="flex items-start justify-between gap-3 border-b border-shop-border/40 pb-2 last:border-b-0 last:pb-0">
                    <span class="text-sm leading-snug text-shop-foreground">
                        <span x-text="line.name"></span>
                        <span class="block font-mono text-[0.7rem] text-shop-muted" x-text="line.qty"></span>
                    </span>
                    <span class="shrink-0 font-mono text-xs tabular-nums text-shop-foreground" x-text="line.lineTotalLabel"></span>
                </li>
            </template>
        </ul>
        <div class="mt-4 flex items-center justify-between border-t border-shop-border pt-3 font-mono text-xs uppercase tracking-widest">
            <span class="text-shop-muted">{{ __('shop.confirm_order_total') }}</span>
            <span class="text-shop-foreground" x-text="totalLabel()"></span>
        </div>
        <button
            type="submit"
            form="shop-checkout-form"
            @click="chipOpen = false"
            class="touch-manipulation mt-4 inline-flex h-[3rem] w-full items-center justify-center border border-shop-primary bg-shop-primary px-5 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover"
        >{{ __('shop.basket_chip_checkout_cta') }}</button>
    </div>
</div>
