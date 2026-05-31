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
