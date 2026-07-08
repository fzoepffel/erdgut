<x-shop-layout :title="__('restaurant.order_ref', ['id' => $order->id])" :narrow="true" :restaurant-nav="$order->isRequest() ? 'requests' : 'orders'">
    @if (session('status') === 'restaurant-order-submitted')
        <div class="mb-6 border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">{{ __('restaurant.order_confirmed_flash') }}</div>
    @elseif (session('status') === 'restaurant-request-submitted')
        <div class="mb-6 border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ __('restaurant.request_submitted_flash') }}</div>
    @elseif (session('status') === 'restaurant-order-cancelled')
        <div class="mb-6 border border-shop-border bg-shop-card p-4 text-sm text-shop-foreground">{{ __('restaurant.order_cancelled_flash') }}</div>
    @elseif (session('status') === 'restaurant-changes-locked')
        <div class="mb-6 border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">{{ __('restaurant.changes_locked') }}</div>
    @elseif (session('status') === 'restaurant-order-adjusted')
        <div class="mb-6 border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">{{ __('restaurant.order_adjusted_flash') }}</div>
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

    @if ($locked && in_array($order->status, [\App\Enums\RestaurantOrderStatus::Submitted, \App\Enums\RestaurantOrderStatus::Confirmed], true) && ! $order->isInvoiced())
        {{-- Past the ordering cutoff for this delivery day: no self-service changes. --}}
        <p class="mt-8 text-sm text-shop-muted" data-testid="restaurant-order-locked-note">{{ __('restaurant.changes_locked') }}</p>
    @endif

    @if (! $locked && ! $order->isInvoiced() && $order->status === \App\Enums\RestaurantOrderStatus::Confirmed && ! $order->isRequest())
        {{-- ── Owner edit: "this week 2 kg less" (until the cutoff) ────── --}}
        <div x-data="{ adjustOpen: {{ $errors->any() ? 'true' : 'false' }} }" class="shop-card mt-8">
            <button type="button" @click="adjustOpen = !adjustOpen" :aria-expanded="adjustOpen"
                    data-testid="restaurant-order-adjust-toggle"
                    class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left">
                <span>
                    <span class="block text-sm font-semibold text-shop-foreground">{{ __('restaurant.adjust_title') }}</span>
                    <span class="block text-xs text-shop-muted">{{ __('restaurant.adjust_hint') }}</span>
                </span>
                <svg class="h-4 w-4 shrink-0 text-shop-muted transition-transform" :class="adjustOpen && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
            </button>
            <form x-show="adjustOpen" x-cloak method="POST" action="{{ route('app.restaurant.orders.update-lines', $order) }}" class="border-t border-shop-border/60 px-5 py-4">
                @csrf
                @error('lines')<p class="mb-3 text-xs text-chapter-meat">{{ $message }}</p>@enderror
                <div class="space-y-3">
                    @foreach ($order->items as $i => $item)
                        @continue($item->isDeclined())
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $item->id }}">
                            <span class="min-w-0 flex-1 truncate text-sm text-shop-foreground">{{ $item->displayName() }}</span>
                            <span class="flex items-center gap-2">
                                <input type="number" step="{{ $item->product?->effectiveQuantityStep() ?? 1 }}" min="0"
                                       name="lines[{{ $i }}][quantity]"
                                       value="{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}"
                                       data-testid="restaurant-adjust-qty-{{ $item->id }}"
                                       class="w-28 border border-shop-border bg-shop-background px-2 py-1.5 text-right text-sm text-shop-foreground focus:border-shop-primary focus:outline-none">
                                <span class="w-10 text-xs text-shop-muted">{{ $item->isCustom() ? \App\Enums\ProductUnit::displaySymbol((string) $item->unit) : $item->product?->canonicalUnitLabel() }}</span>
                                <label class="flex items-center gap-1 text-xs text-shop-muted">
                                    <input type="checkbox" name="lines[{{ $i }}][remove]" value="1" class="border-shop-border">
                                    {{ __('restaurant.adjust_remove') }}
                                </label>
                            </span>
                        </div>
                    @endforeach
                </div>
                <button type="submit" data-testid="restaurant-order-adjust-save"
                        class="mt-4 inline-flex items-center border border-shop-primary bg-shop-primary px-5 py-2 font-mono text-[0.7rem] uppercase tracking-widest text-shop-primary-foreground transition hover:bg-shop-primary-hover">
                    {{ __('restaurant.adjust_save') }}
                </button>
            </form>
        </div>
    @endif

    @if (! $locked && ! $order->isInvoiced() && in_array($order->status, [\App\Enums\RestaurantOrderStatus::Submitted, \App\Enums\RestaurantOrderStatus::Confirmed], true))
        @if ($cutoffLabel)
            <p class="mt-8 text-xs text-shop-muted" data-testid="restaurant-order-cutoff-note">{{ __('restaurant.changes_until', ['cutoff' => $cutoffLabel]) }}</p>
        @endif
        @php
            // A future auto-generated weekly delivery is "skipped", not
            // cancelled — the recurrence itself continues (skipping leaves a
            // tombstone so this week never regenerates).
            $isRecurringWeek = $order->source_standing_order_id !== null
                && $order->expected_delivery_date->isFuture();
            $cancelKey = $isRecurringWeek ? 'skip_week' : ($order->isRequest() ? 'request_cancel' : 'order_cancel');
        @endphp
        <form method="POST" action="{{ route('app.restaurant.orders.cancel', $order) }}" class="mt-3"
              onsubmit="return confirm(@js(__('restaurant.'.$cancelKey.'_confirm')))">
            @csrf
            <button type="submit" class="inline-flex h-[3rem] items-center justify-center border border-chapter-meat px-6 font-mono text-[0.7rem] uppercase tracking-widest text-chapter-meat transition hover:bg-chapter-meat hover:text-white">
                {{ __('restaurant.'.$cancelKey) }}
            </button>
        </form>
        @if ($isRecurringWeek)
            <p class="mt-2 text-xs text-shop-muted">{{ __('restaurant.skip_week_note') }}</p>
        @endif
    @endif

    @if ($order->isRequest())
        <a href="{{ route('app.restaurant.requests.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.my_requests_list_title') }}</a>
    @else
        <a href="{{ route('app.restaurant.orders.index') }}" class="mt-6 inline-block font-mono text-[0.7rem] uppercase tracking-widest text-shop-muted underline-offset-4 hover:text-shop-primary hover:underline">{{ __('restaurant.my_requests') }}</a>
    @endif
</x-shop-layout>
