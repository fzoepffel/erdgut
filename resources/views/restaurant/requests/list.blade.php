<x-shop-layout :title="__('restaurant.my_requests_list_title')" :narrow="true" restaurant-nav="requests">
    <p class="eyebrow text-amber-700">{{ __('restaurant.requests_eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.my_requests_list_title') }}</h1>

    @if ($requests->isEmpty())
        <div class="shop-card mt-8 p-8 text-center text-shop-muted">{{ __('restaurant.requests_list_empty') }}</div>
    @else
        <div class="mt-8 divide-y divide-shop-border border border-shop-border">
            @foreach ($requests as $req)
                <a href="{{ route('app.restaurant.orders.show', $req) }}" class="flex items-center justify-between gap-4 px-4 py-3 hover:bg-shop-card" data-testid="request-list-row-{{ $req->id }}">
                    <div>
                        <p class="text-sm text-shop-foreground">{{ __('restaurant.request_ref', ['id' => $req->id]) }}</p>
                        <p class="text-xs text-shop-muted">{{ $req->expected_delivery_date->translatedFormat('d.m.Y') }} · {{ $req->items_count }} {{ trans_choice('restaurant.cart_line_count', $req->items_count) }}</p>
                    </div>
                    <span class="rounded-full bg-shop-card px-2.5 py-0.5 text-[11px] font-medium text-shop-foreground">{{ __('restaurant.order_status_'.$req->status->value) }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $requests->links() }}</div>
    @endif

    <a href="{{ route('app.restaurant.requests.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">
        &larr; {{ __('restaurant.requests_title') }}
    </a>
</x-shop-layout>
