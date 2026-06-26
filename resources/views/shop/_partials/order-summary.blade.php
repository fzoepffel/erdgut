{{-- Server-rendered basket summary shown on the checkout's step 2 + 3.
     The pricing here is authoritative (driven by ShopOrderService::quote
     on the controller) — no client-side estimate is needed since the
     stepper UI lives only on /shop. Each line shows product · variant
     snapshot · quantity · line total. Totals at the bottom mirror the
     /shop/{order}/pay summary so the customer recognises the layout.

     Props:
       lines (array)   - the quote array from ShopOrderService::quote()
       editHref (string|null) - "Bearbeiten" anchor next to the basket header. --}}
@props([
    'lines' => [],
    'editHref' => null,
])
@php
    $items = (array) ($lines['lines'] ?? []);
    $itemsTotal = (float) ($lines['items_total_eur'] ?? 0);
    $deliveryFee = (float) ($lines['delivery_fee_eur'] ?? 0);
    $discount = (float) ($lines['discount_eur'] ?? 0);
    $total = (float) ($lines['total_amount_eur'] ?? 0);
    $couponCode = $lines['coupon_code'] ?? null;
@endphp
<section
    class="border border-shop-border bg-shop-paper p-5"
    data-testid="shop-checkout-summary"
>
    <header class="flex items-center justify-between gap-3">
        <p class="eyebrow text-shop-primary">{{ __('shop.checkout.summary_heading') }}</p>
        @if ($editHref)
            <a
                href="{{ $editHref }}"
                class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary underline-offset-4 hover:underline"
                data-testid="shop-checkout-summary-edit"
            >{{ __('shop.checkout.summary_edit') }}</a>
        @endif
    </header>
    <dl class="mt-4 divide-y divide-shop-border/40 text-sm text-shop-foreground">
        @forelse ($items as $item)
            @php $product = $item['product'] ?? null; @endphp
            <div class="flex items-start justify-between gap-4 py-3">
                <dt>
                    <span class="text-shop-foreground">
                        {{ $product?->name ?? __('shop.admin.deleted_product') }}@if (! empty($item['variant_name_snapshot']))<span class="text-shop-muted"> · {{ $item['variant_name_snapshot'] }}</span>@endif
                    </span>
                    <span class="mt-1 block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">
                        {{ $product?->displayQuantity((float) ($item['quantity'] ?? 0)) }}
                    </span>
                </dt>
                <dd class="tabular-nums text-shop-foreground">@eur($item['line_total_eur'] ?? 0)</dd>
            </div>
        @empty
            <p class="py-3 text-sm text-shop-muted">{{ __('shop.checkout.summary_empty') }}</p>
        @endforelse
    </dl>
    <div class="mt-4 space-y-2 border-t border-shop-border pt-4 text-sm tabular-nums">
        <div class="flex items-center justify-between gap-4">
            <span class="text-shop-muted">{{ __('shop.checkout.summary_items_total') }}</span>
            <span class="text-shop-foreground">@eur($itemsTotal)</span>
        </div>
        @if ($deliveryFee > 0)
            <div class="flex items-center justify-between gap-4">
                <span class="text-shop-muted">{{ __('shop.summary_line_delivery') }}</span>
                <span class="text-shop-foreground">@eur($deliveryFee)</span>
            </div>
        @endif
        @if ($discount > 0)
            <div class="flex items-center justify-between gap-4">
                <span class="text-shop-muted">{{ __('coupon.discount_line') }} {{ $couponCode }}</span>
                <span class="text-shop-foreground">− @eur($discount)</span>
            </div>
        @endif
        <div class="mt-2 flex items-center justify-between gap-4 border-t border-shop-border pt-3 font-mono text-xs uppercase tracking-widest">
            <span class="text-shop-muted">{{ __('shop.summary_line_total') }}</span>
            <span class="text-shop-foreground" data-testid="shop-checkout-summary-total">@eur($total)</span>
        </div>
    </div>
</section>
