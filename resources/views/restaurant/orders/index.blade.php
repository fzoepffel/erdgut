<x-shop-layout :title="__('restaurant.my_requests')" :narrow="true" restaurant-nav="orders">
    <div class="flex items-center justify-between">
        <div>
            <p class="eyebrow text-shop-primary">{{ __('restaurant.catalog_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.my_requests') }}</h1>
        </div>
        <a href="{{ route('app.restaurant.catalog') }}" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.dashboard_cta') }}</a>
    </div>

    @if ($orders->isEmpty())
        <div class="shop-card mt-8 p-8 text-center text-shop-muted">{{ __('restaurant.orders_empty') }}</div>
    @else
        <div class="mt-8 divide-y divide-shop-border border border-shop-border">
            @foreach ($orders as $order)
                <a href="{{ route('app.restaurant.orders.show', $order) }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-shop-card">
                    <div>
                        <p class="text-sm text-shop-foreground">{{ __('restaurant.order_ref', ['id' => $order->id]) }}</p>
                        <p class="text-xs text-shop-muted">{{ $order->expected_delivery_date->translatedFormat('d.m.Y') }} · {{ $order->items_count }} {{ trans_choice('restaurant.cart_line_count', $order->items_count) }}</p>
                    </div>
                    <div class="text-right">
                        <span class="rounded-full bg-shop-card px-2.5 py-0.5 text-[11px] font-medium text-shop-foreground">{{ __('restaurant.order_status_'.$order->status->value) }}</span>
                        <p class="mt-1 font-mono text-sm text-shop-foreground">@eur($order->items_total_net_eur) <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span></p>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-shop-layout>
