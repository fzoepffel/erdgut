@props(['order'])

@php
    $base = 'inline-flex items-center px-2.5 py-1 text-[11px] font-semibold uppercase tracking-eyebrow';
@endphp

@if ($order->isAwaitingInPersonPayment())
    <span class="{{ $base }} border border-blue-300 bg-blue-50 text-blue-700" data-testid="shop-order-status-pending-in-person">
        {{ __('shop.admin.status_pending_in_person') }}
    </span>
    <div class="mt-1 text-[11px] font-medium normal-case tracking-normal text-gray-500">{{ __('shop.admin.pending_in_person_note') }}</div>
@elseif ($order->isAwaitingOnlinePayment())
    <span class="{{ $base }} border border-dashed border-amber-400 bg-amber-50 text-amber-700" data-testid="shop-order-status-pending-online">
        {{ __('shop.admin.status_pending_online') }}
    </span>
    <div class="mt-1 text-[11px] font-semibold normal-case tracking-normal text-amber-700">{{ __('shop.admin.pending_online_note') }}</div>
@else
    <x-status-badge :value="$order->status" type="shop_order" />
@endif

{{-- Open difference after an admin edit of a paid order: positive = collect
     the remainder in person, negative = refund the overpayment in person. --}}
@if ($order->hasOpenAmount())
    @php
        $openAmountLabel = number_format(abs($order->outstandingAmountEur()), 2, ',', '.').' €';
    @endphp
    @if ($order->outstandingAmountEur() > 0)
        <span class="{{ $base }} mt-1 border border-amber-300 bg-amber-50 text-amber-800" data-testid="shop-order-open-amount">
            {{ __('shop.admin.badge_open_amount', ['amount' => $openAmountLabel]) }}
        </span>
    @else
        <span class="{{ $base }} mt-1 border border-violet-300 bg-violet-50 text-violet-800" data-testid="shop-order-refund-due">
            {{ __('shop.admin.badge_refund_due', ['amount' => $openAmountLabel]) }}
        </span>
    @endif
@elseif ($order->outstanding_settled_at !== null && (float) $order->outstanding_settled_amount_eur < 0)
    {{-- The overpayment was refunded in person; keep that visible. --}}
    <span class="{{ $base }} mt-1 border border-emerald-300 bg-emerald-50 text-emerald-800" data-testid="shop-order-settled-refunded">
        {{ __('shop.admin.badge_settled_refunded') }}
    </span>
@endif
