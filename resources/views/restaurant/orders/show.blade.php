<x-shop-layout :title="__('restaurant.order_ref', ['id' => $order->id])" :narrow="true">
    @if (session('status') === 'restaurant-order-submitted')
        <div class="mb-6 border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">{{ __('restaurant.order_submitted_flash') }}</div>
    @elseif (session('status') === 'restaurant-order-cancelled')
        <div class="mb-6 border border-shop-border bg-shop-card p-4 text-sm text-shop-foreground">{{ __('restaurant.order_cancelled_flash') }}</div>
    @endif

    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="eyebrow text-shop-primary">{{ __('restaurant.order_ref', ['id' => $order->id]) }}</p>
            <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ $order->expected_delivery_date->translatedFormat('d.m.Y') }}</h1>
        </div>
        <span class="rounded-full bg-shop-card px-3 py-1 text-xs font-medium text-shop-foreground">{{ __('restaurant.order_status_'.$order->status->value) }}</span>
    </div>

    <div class="shop-card mt-8 divide-y divide-shop-border">
        @foreach ($order->items as $item)
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm text-shop-foreground">{{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}</p>
                    @if ($item->isReserved())
                        <span class="text-[11px] font-medium text-emerald-700">{{ __('restaurant.in_stock') }}</span>
                    @else
                        <span class="text-[11px] font-medium text-amber-700">{{ __('restaurant.on_request') }}</span>
                    @endif
                </div>
                <p class="shrink-0 font-mono text-sm text-shop-foreground">@eur($item->lineNetEur()) <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span></p>
            </div>
        @endforeach
        <div class="flex items-center justify-between px-5 py-3">
            <p class="text-sm font-semibold text-shop-foreground">{{ __('restaurant.order_net_total') }}</p>
            <p class="font-mono text-sm font-semibold text-shop-foreground">@eur($order->items_total_net_eur)</p>
        </div>
    </div>

    <div class="mt-6 text-sm text-shop-muted">
        <p class="font-mono text-[0.7rem] uppercase tracking-widest">{{ __('restaurant.cart_delivery_address') }}</p>
        <p class="mt-1 text-shop-foreground">{{ $order->delivery_street }}, {{ $order->delivery_postal_code }} {{ $order->delivery_city }}</p>
        @if ($order->delivery_notes)<p class="mt-1">{{ $order->delivery_notes }}</p>@endif
    </div>

    @if ($order->isSubmitted())
        <form method="POST" action="{{ route('app.restaurant.orders.cancel', $order) }}" class="mt-8"
              onsubmit="return confirm(@js(__('restaurant.order_cancel_confirm')))">
            @csrf
            <button type="submit" class="inline-flex h-[3rem] items-center justify-center border border-chapter-meat px-6 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-meat transition hover:bg-chapter-meat hover:text-white">
                {{ __('restaurant.order_cancel') }}
            </button>
        </form>
    @endif

    <a href="{{ route('app.restaurant.orders.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.my_requests') }}</a>
</x-shop-layout>
