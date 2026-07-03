<x-shop-layout :title="__('restaurant.my_requests')" :narrow="true" restaurant-nav="orders">
    <div class="flex items-center justify-between">
        <div>
            <p class="eyebrow text-shop-primary">{{ __('restaurant.orders_eyebrow') }}</p>
            <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.my_requests') }}</h1>
        </div>
        <a href="{{ route('app.restaurant.catalog') }}" class="font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.dashboard_cta') }}</a>
    </div>

    @if ($orders->isEmpty())
        <div class="shop-card mt-8 p-8 text-center">
            <p class="text-shop-muted">{{ __('restaurant.orders_empty') }}</p>
            <a href="{{ route('app.restaurant.catalog') }}"
               class="mt-5 inline-flex h-[3rem] items-center justify-center border border-shop-primary bg-shop-primary px-6 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                {{ __('restaurant.dashboard_cta') }}
            </a>
        </div>
    @else
        {{-- Planned weekly deliveries, one line per schedule — the individual
             future weeks are collapsed out of the list below. --}}
        @foreach ($recurringSummaries as $summary)
            <a href="{{ route('app.restaurant.standing-orders.index') }}" data-testid="recurring-summary-{{ $summary['standing']->id }}"
               class="mt-8 flex flex-wrap items-center justify-between gap-3 border-l-2 border-shop-primary bg-shop-paper px-4 py-3 text-sm text-shop-foreground transition hover:bg-shop-card">
                <span>
                    {{ __('restaurant.recurring_summary_line', [
                        'weekday' => \Illuminate\Support\Carbon::parse($summary['next'])->locale(app()->getLocale())->isoFormat('dddd'),
                        'count' => $summary['count'],
                        'until' => \Illuminate\Support\Carbon::parse($summary['until'])->translatedFormat('d.m.Y'),
                    ]) }}
                </span>
                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-shop-primary">{{ __('restaurant.edit_standing') }} &rarr;</span>
            </a>
        @endforeach

        <div class="mt-8 divide-y divide-shop-border border border-shop-border">
            @foreach ($orders as $order)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-shop-card">
                    <a href="{{ route('app.restaurant.orders.show', $order) }}" class="min-w-0 flex-1">
                        <p class="text-sm text-shop-foreground">{{ __('restaurant.order_ref', ['id' => $order->id]) }}</p>
                        @php
                            $names = $order->items->take(2)->map(fn ($i) => $i->displayName())->filter()->implode(', ');
                            $more = max(0, $order->items_count - 2);
                        @endphp
                        <p class="truncate text-xs text-shop-foreground/80">{{ $names }}@if ($more > 0) <span class="text-shop-muted">+{{ $more }}</span>@endif</p>
                        <p class="text-xs text-shop-muted">{{ $order->expected_delivery_date->translatedFormat('d.m.Y') }} · {{ $order->items_count }} {{ trans_choice('restaurant.cart_line_count', $order->items_count) }}</p>
                    </a>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <div class="text-right">
                            <span class="rounded-full bg-shop-card px-2.5 py-0.5 text-[11px] font-medium text-shop-foreground">{{ __('restaurant.order_status_'.$order->status->value) }}</span>
                            <p class="mt-1 font-mono text-sm text-shop-foreground">@eur($order->items_total_net_eur) <span class="text-xs text-shop-muted">{{ __('restaurant.net_suffix') }}</span></p>
                        </div>
                        {{-- Recurring orders are managed, not re-ordered; one-time
                             orders get the one-click repeat (fresh prices + stock). --}}
                        @if ($order->source_standing_order_id !== null)
                            <a href="{{ route('app.restaurant.standing-orders.index') }}" data-testid="restaurant-edit-standing-{{ $order->id }}"
                               class="border border-shop-border px-3 py-1.5 font-mono text-[0.65rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                                {{ __('restaurant.edit_standing') }}
                            </a>
                        @elseif (! in_array($order->status->value, ['cancelled', 'declined'], true))
                            <form method="POST" action="{{ route('app.restaurant.orders.reorder', $order) }}">
                                @csrf
                                <button type="submit" data-testid="restaurant-reorder-{{ $order->id }}"
                                        class="border border-shop-border px-3 py-1.5 font-mono text-[0.65rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                                    {{ __('restaurant.reorder') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-shop-layout>
