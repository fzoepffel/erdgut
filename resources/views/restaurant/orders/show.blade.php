<x-shop-layout :title="__('restaurant.order_ref', ['id' => $order->id])" :narrow="true" restaurant-nav="orders">
    @if (session('status') === 'restaurant-order-submitted')
        <div class="mb-6 border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">
            {{ __('restaurant.order_confirmed_flash') }}
            @if (is_array(session('reorder_adjusted')) && session('reorder_adjusted') !== [])
                <span class="block pt-1 text-amber-800">{{ __('restaurant.reorder_adjusted_flash', ['names' => implode(', ', session('reorder_adjusted'))]) }}</span>
            @endif
        </div>
    @elseif (session('status') === 'restaurant-request-submitted')
        <div class="mb-6 border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ __('restaurant.request_submitted_flash') }}</div>
    @elseif (session('status') === 'restaurant-reorder-empty')
        <div class="mb-6 border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ __('restaurant.reorder_empty_flash') }}</div>
    @elseif (session('status') === 'restaurant-order-cancelled')
        <div class="mb-6 border border-shop-border bg-shop-card p-4 text-sm text-shop-foreground">{{ __('restaurant.order_cancelled_flash') }}</div>
    @endif

    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="eyebrow {{ $order->isRequest() ? 'text-amber-700' : 'text-shop-primary' }}">{{ __($order->isRequest() ? 'restaurant.request_ref' : 'restaurant.order_ref', ['id' => $order->id]) }}</p>
            <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ $order->expected_delivery_date->translatedFormat('d.m.Y') }}</h1>
        </div>
        <span class="rounded-full bg-shop-card px-3 py-1 text-xs font-medium text-shop-foreground">{{ __('restaurant.order_status_'.$order->status->value) }}</span>
    </div>

    <div class="shop-card mt-8 divide-y divide-shop-border">
        @foreach ($order->items as $item)
            <div class="flex items-center justify-between gap-3 px-5 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm text-shop-foreground">{{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}</p>
                    @if ($item->isDeclined())
                        <span class="text-[11px] font-medium text-shop-muted line-through">{{ __('restaurant.line_declined') }}</span>
                    @elseif ($item->isPending())
                        <span class="text-[11px] font-medium text-amber-700">{{ __('restaurant.line_pending') }}</span>
                    @elseif ($item->isReserved())
                        <span class="text-[11px] font-medium text-emerald-700">{{ __('restaurant.in_stock') }}</span>
                    @else
                        <span class="text-[11px] font-medium text-amber-700">{{ __('restaurant.on_request') }}</span>
                    @endif
                    @if ($item->isWeekly())
                        <span class="ml-2 text-[11px] font-medium text-shop-primary">{{ __('restaurant.recurrence_weekly') }}@if ($item->recurrence_until) · {{ __('restaurant.recurrence_until') }} {{ $item->recurrence_until->format('d.m.Y') }}@endif</span>
                    @endif
                </div>
                @if ($item->isCustom())
                    <p class="shrink-0 text-sm text-shop-muted">{{ __('restaurant.price_on_request') }}</p>
                @else
                    <p class="shrink-0 font-mono text-sm text-shop-foreground">@eur($item->lineNetEur()) <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span></p>
                @endif
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

    @if ($order->isInvoiced())
        <div class="shop-card mt-6 p-5">
            <p class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted">{{ __('restaurant.invoice') }}</p>
            <p class="mt-1 text-sm text-shop-foreground">{{ $order->invoice_number }} · {{ __('restaurant.invoice_due', ['date' => optional($order->invoice_due_date)->format('d.m.Y')]) }}</p>
            @if ($order->isInvoicePaid())
                <p class="mt-1 text-sm text-emerald-700">{{ __('restaurant.invoice_paid_note', ['date' => $order->invoice_paid_at->format('d.m.Y')]) }}</p>
            @endif
            <a href="{{ route('app.restaurant.orders.invoice-pdf', $order) }}"
               class="mt-3 inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                {{ __('restaurant.invoice_download') }}
            </a>
        </div>
    @endif

    @if ($order->source_standing_order_id !== null)
        <div class="mt-6 border-l-2 border-shop-primary bg-shop-paper px-4 py-3 text-sm text-shop-foreground">
            {{ __('restaurant.order_recurring_note') }}
            <a href="{{ route('app.restaurant.standing-orders.index') }}" class="text-shop-primary underline underline-offset-2">{{ __('restaurant.standing_title') }}</a>
        </div>
    @endif

    @if (! $order->isInvoiced() && in_array($order->status, [\App\Enums\RestaurantOrderStatus::Submitted, \App\Enums\RestaurantOrderStatus::Confirmed], true))
        <form method="POST" action="{{ route('app.restaurant.orders.cancel', $order) }}" class="mt-8"
              onsubmit="return confirm(@js(__($order->isRequest() ? 'restaurant.request_cancel_confirm' : 'restaurant.order_cancel_confirm')))">
            @csrf
            <button type="submit" class="inline-flex h-[3rem] items-center justify-center border border-chapter-meat px-6 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-meat transition hover:bg-chapter-meat hover:text-white">
                {{ __($order->isRequest() ? 'restaurant.request_cancel' : 'restaurant.order_cancel') }}
            </button>
        </form>
    @endif

    @if ($order->isRequest())
        <a href="{{ route('app.restaurant.requests.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.my_requests_list_title') }}</a>
    @else
        <a href="{{ route('app.restaurant.orders.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.my_requests') }}</a>
    @endif
</x-shop-layout>
