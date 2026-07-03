<x-shop-layout :title="__('restaurant.invoices_title')" :narrow="true" restaurant-nav="account">
    <p class="eyebrow text-shop-primary">{{ __('restaurant.orders_eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl text-shop-foreground">{{ __('restaurant.invoices_title') }}</h1>

    @if ($invoices->isEmpty())
        <div class="shop-card mt-8 p-8 text-center text-shop-muted">{{ __('restaurant.invoices_empty') }}</div>
    @else
        <div class="mt-8 divide-y divide-shop-border border border-shop-border">
            @foreach ($invoices as $order)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" data-testid="restaurant-invoice-row-{{ $order->id }}">
                    <div class="min-w-0">
                        <p class="text-sm text-shop-foreground">{{ $order->invoice_number }}</p>
                        <p class="text-xs text-shop-muted">{{ optional($order->invoiced_at)->format('d.m.Y') }} · {{ __('restaurant.order_ref', ['id' => $order->id]) }} · {{ __('restaurant.invoice_due', ['date' => optional($order->invoice_due_date)->format('d.m.Y')]) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[11px] font-medium',
                            'bg-emerald-100 text-emerald-800' => $order->isInvoicePaid(),
                            'bg-rose-100 text-rose-800' => ! $order->isInvoicePaid() && $order->isInvoiceOverdue(),
                            'bg-amber-100 text-amber-800' => ! $order->isInvoicePaid() && ! $order->isInvoiceOverdue(),
                        ])>
                            {{ $order->isInvoicePaid() ? __('restaurant.invoice_paid_badge') : ($order->isInvoiceOverdue() ? __('restaurant.invoice_overdue_badge') : __('restaurant.invoice_open_badge')) }}
                        </span>
                        <span class="font-mono text-sm text-shop-foreground">@eur($order->items_total_gross_eur)</span>
                        <a href="{{ route('app.restaurant.orders.invoice-pdf', $order) }}"
                           class="border border-shop-border px-3 py-1.5 font-mono text-[0.65rem] uppercase tracking-widest text-shop-foreground transition hover:border-shop-primary hover:text-shop-primary">
                            PDF
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $invoices->links() }}</div>
    @endif
</x-shop-layout>
