<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.heading') }}</a>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.order_ref', ['id' => $order->id]) }}</h1>
            </div>
            @php $status = $order->status->value; @endphp
            <span class="flex items-center gap-2">
                <span class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                @if ($order->isRequest())
                    <span class="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-amber-700">{{ __('restaurant.kind_request') }}</span>
                @endif
                <span @class([
                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
                    'bg-amber-100 text-amber-800' => $status === 'submitted',
                    'bg-green-100 text-green-800' => $status === 'confirmed' || $status === 'fulfilled',
                    'bg-gray-100 text-gray-600' => $status === 'cancelled' || $status === 'declined',
                ])>{{ __('restaurant.order_status_'.$status) }}</span>
            </span>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div role="status" class="rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            @if ($order->source_standing_order_id !== null && optional($order->standingOrder)->isPending())
                <div class="rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">{{ __('restaurant.admin_recurring_note') }}</div>
            @endif

            @if ($order->source_standing_order_id !== null)
                <a href="{{ route('app.admin.standing-orders.index') }}#standing-{{ $order->source_standing_order_id }}"
                   data-testid="admin-standing-manage-link"
                   class="inline-flex items-center text-sm font-medium text-emerald-700 hover:underline">
                    {{ __('restaurant.admin_standing_manage_link') }} &rarr;
                </a>
            @endif

            @php
                $pendingLines = $order->items->filter(fn ($i) => $i->isPending());
                $canCancel = ! $order->isInvoiced() && in_array($order->status, [\App\Enums\RestaurantOrderStatus::Submitted, \App\Enums\RestaurantOrderStatus::Confirmed], true);
                $isFirm = in_array($status, ['confirmed', 'fulfilled'], true);
            @endphp

            @if ($order->isSubmitted())
                {{-- ── Pending request: the review panel IS the page's focus ── --}}
                <div class="space-y-4 rounded-lg border border-amber-300 bg-amber-50/60 p-5 shadow-sm" data-testid="restaurant-request-review">
                    <p class="text-xs font-medium uppercase tracking-wider text-amber-800">{{ __('restaurant.admin_pending_lines_title') }}</p>
                    @if ($pendingLines->isNotEmpty())
                        <ul class="divide-y divide-amber-200/60">
                            @foreach ($pendingLines as $item)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                    <span class="text-sm text-gray-800">{{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}</span>
                                    <span class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('app.admin.restaurant-orders.accept-line', [$order, $item]) }}">
                                            @csrf
                                            <button type="submit" data-testid="restaurant-line-accept-{{ $item->id }}" class="rounded bg-emerald-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_line_accept') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('app.admin.restaurant-orders.decline-line', [$order, $item]) }}">
                                            @csrf
                                            <button type="submit" data-testid="restaurant-line-decline-{{ $item->id }}" class="rounded border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">{{ __('restaurant.admin_line_decline') }}</button>
                                        </form>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="flex flex-wrap items-center gap-3 border-t border-amber-200/60 pt-3">
                        <form method="POST" action="{{ route('app.admin.restaurant-orders.confirm', $order) }}">
                            @csrf
                            <button type="submit" data-testid="restaurant-order-confirm" class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_orders_confirm') }}</button>
                        </form>
                        <form method="POST" action="{{ route('app.admin.restaurant-orders.decline', $order) }}"
                              onsubmit="return confirm(@js(__('restaurant.admin_orders_decline_confirm')))">
                            @csrf
                            <button type="submit" data-testid="restaurant-order-decline" class="rounded border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">{{ __('restaurant.admin_orders_decline') }}</button>
                        </form>
                        @if ($canCancel)
                            <form method="POST" action="{{ route('app.admin.restaurant-orders.cancel', $order) }}" class="ml-auto"
                                  onsubmit="return confirm(@js(__('restaurant.admin_orders_cancel_confirm')))">
                                @csrf
                                <button type="submit" data-testid="restaurant-order-cancel" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">{{ __('restaurant.admin_orders_cancel') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @elseif ($isFirm)
                {{-- ── Firm order: plain actions bar, like the shop-order detail ── --}}
                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm" data-testid="restaurant-order-actions">
                    @if ($status === 'confirmed')
                        <form method="POST" action="{{ route('app.admin.restaurant-orders.fulfil', $order) }}">
                            @csrf
                            <button type="submit" data-testid="restaurant-order-fulfil" class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_orders_fulfil') }}</button>
                        </form>
                    @endif
                    @if ($order->isInvoiced())
                        <a href="{{ route('app.admin.restaurant-orders.invoice-pdf', $order) }}" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('restaurant.invoice_download') }}</a>
                        @unless ($order->isInvoicePaid())
                            <form method="POST" action="{{ route('app.admin.restaurant-orders.invoice-paid', $order) }}">
                                @csrf
                                <button type="submit" data-testid="restaurant-order-mark-paid" class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_invoice_mark_paid') }}</button>
                            </form>
                        @endunless
                    @endif
                    @if ($canCancel)
                        <form method="POST" action="{{ route('app.admin.restaurant-orders.cancel', $order) }}" class="ml-auto"
                              onsubmit="return confirm(@js(__('restaurant.admin_orders_cancel_confirm')))">
                            @csrf
                            <button type="submit" data-testid="restaurant-order-cancel" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">{{ __('restaurant.admin_orders_cancel') }}</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- ── Customer + fulfillment ─────────────────────────────────── --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('restaurant.field_business_name') }}</p>
                    <p class="mt-1 text-sm text-gray-800">{{ $order->user?->restaurantProfile?->business_name ?? $order->user?->name ?? '—' }}</p>
                    @if ($order->user?->restaurantProfile?->contact_name)
                        <p class="text-sm text-gray-500">{{ $order->user->restaurantProfile->contact_name }}</p>
                    @endif
                    <p class="text-sm text-gray-500">{{ $order->user?->email }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('restaurant.cart_delivery_address') }}</p>
                    <p class="mt-1 text-sm text-gray-800">{{ $order->delivery_street }}, {{ $order->delivery_postal_code }} {{ $order->delivery_city }}</p>
                    <p class="text-sm text-gray-500">{{ __('restaurant.catalog_date_label') }}: {{ $order->expected_delivery_date->format('d.m.Y') }}</p>
                    @if ($order->delivery_notes)<p class="mt-1 text-sm text-gray-500">{{ $order->delivery_notes }}</p>@endif
                </div>
            </div>

            {{-- ── Items (always read-only here; editing lives below) ───────── --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm" data-testid="restaurant-order-items">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('restaurant.admin_orders_item') }}</th>
                            <th class="px-4 py-3">{{ __('restaurant.admin_status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('restaurant.net_suffix') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($order->items as $item)
                            <tr @class(['opacity-60' => $item->isDeclined()])>
                                <td class="px-4 py-3 text-gray-800 {{ $item->isDeclined() ? 'line-through' : '' }}">{{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}</td>
                                <td class="px-4 py-3">
                                    @if ($item->isDeclined())
                                        <span class="text-gray-500">{{ __('restaurant.line_declined') }}</span>
                                    @elseif ($item->isPending())
                                        <span class="text-amber-700">{{ __('restaurant.line_pending') }}</span>
                                    @elseif ($item->isReserved())
                                        <span class="text-emerald-700">{{ __('restaurant.in_stock') }}</span>
                                    @else
                                        <span class="text-amber-700">{{ __('restaurant.on_request') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-700">
                                    @if ($item->isDeclined())
                                        <span class="text-gray-400">—</span>
                                    @elseif ($item->isCustom() && ! $order->isInvoiced() && $item->lineNetEur() <= 0)
                                        <span class="text-gray-500">{{ __('restaurant.price_on_request') }}</span>
                                    @else
                                        <span class="font-mono">@eur($item->lineNetEur())</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 font-semibold">
                            <td class="px-4 py-3" colspan="2">{{ __('restaurant.order_net_total') }}</td>
                            <td class="px-4 py-3 text-right font-mono">@eur($order->items_total_net_eur)</td>
                        </tr>
                        @if ($order->isInvoiced())
                            <tr class="text-gray-600">
                                <td class="px-4 py-2" colspan="2">{{ __('restaurant.order_gross_total') }}</td>
                                <td class="px-4 py-2 text-right font-mono">@eur($order->items_total_gross_eur)</td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>

            @if ($order->isInvoiced())
                {{-- ── Invoice: frozen, with settlement state ─────────────────── --}}
                <div class="rounded-lg border p-5 text-sm {{ $order->isInvoicePaid() ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : ($order->isInvoiceOverdue() ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-amber-200 bg-amber-50 text-amber-900') }}">
                    <p class="font-semibold">{{ __('restaurant.admin_invoice_issued', ['number' => $order->invoice_number]) }}
                        @if ($order->isInvoicePaid())
                            <span class="ml-2 rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-medium text-white">{{ __('restaurant.invoice_paid_badge') }}</span>
                        @elseif ($order->isInvoiceOverdue())
                            <span class="ml-2 rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-medium text-white">{{ __('restaurant.invoice_overdue_badge') }}</span>
                        @else
                            <span class="ml-2 rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-medium text-white">{{ __('restaurant.invoice_open_badge') }}</span>
                        @endif
                    </p>
                    <p class="mt-1">{{ __('restaurant.admin_invoice_dated', ['date' => optional($order->invoiced_at)->format('d.m.Y'), 'due' => optional($order->invoice_due_date)->format('d.m.Y')]) }}</p>
                    @if ($order->isInvoicePaid())
                        <p class="mt-1">{{ __('restaurant.admin_invoice_paid_on', ['date' => $order->invoice_paid_at->format('d.m.Y')]) }}</p>
                    @endif
                </div>
            @endif

            @if ($order->isInvoiceable())
                {{-- ── Adjust lines & prices (collapsed — this is where a granted
                     request gets tweaked before the invoice freezes it) ──────── --}}
                <div x-data="{ adjustOpen: {{ $errors->any() ? 'true' : 'false' }} }" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <button type="button" @click="adjustOpen = !adjustOpen" :aria-expanded="adjustOpen"
                            data-testid="restaurant-order-adjust-toggle"
                            class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left">
                        <span>
                            <span class="block text-sm font-semibold text-gray-800">{{ __('restaurant.admin_adjust_lines_title') }}</span>
                            <span class="block text-xs text-gray-500">{{ __('restaurant.admin_adjust_lines_hint') }}</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform" :class="adjustOpen && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </button>

                    <div x-show="adjustOpen" x-cloak class="border-t border-gray-100">
                        <form method="POST" action="{{ route('app.admin.restaurant-orders.pricing', $order) }}">
                            @csrf
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">{{ __('restaurant.admin_orders_item') }}</th>
                                        <th class="px-4 py-3">{{ __('restaurant.admin_orders_qty') }}</th>
                                        <th class="px-4 py-3">{{ __('restaurant.admin_orders_net') }}</th>
                                        <th class="px-4 py-3">{{ __('restaurant.admin_orders_drop') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($order->items as $i => $item)
                                        <tr>
                                            <td class="px-4 py-3 text-gray-800">
                                                {{ $item->displayName() }}
                                                <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-1">
                                                    <input type="number" step="0.0001" min="0" name="items[{{ $i }}][quantity]" value="{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}" class="w-24 rounded border-gray-300 text-sm">
                                                    <span class="text-xs text-gray-500">{{ $item->isCustom() ? \App\Enums\ProductUnit::displaySymbol((string) $item->unit) : $item->product?->canonicalUnitLabel() }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][net_total_eur]" value="{{ number_format($item->lineNetEur(), 2, '.', '') }}" class="w-24 rounded border-gray-300 text-sm" placeholder="0.00">
                                                    <select name="items[{{ $i }}][vat_rate_percent]" class="rounded border-gray-300 text-sm">
                                                        <option value="7" @selected($item->vat_rate_snapshot_percent === 7)>7 %</option>
                                                        <option value="19" @selected($item->vat_rate_snapshot_percent === 19)>19 %</option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3"><input type="checkbox" name="items[{{ $i }}][remove]" value="1" class="rounded border-gray-300"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-4 py-3">
                                <button type="submit" data-testid="restaurant-order-save-pricing" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-white">{{ __('restaurant.admin_invoice_save') }}</button>
                            </div>
                        </form>
                        <div class="flex items-center justify-end border-t border-gray-100 px-4 py-3">
                            <form method="POST" action="{{ route('app.admin.restaurant-orders.invoice', $order) }}"
                                  onsubmit="return confirm(@js(__('restaurant.admin_invoice_issue_confirm')))">
                                @csrf
                                <button type="submit" data-testid="restaurant-order-issue-invoice" class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_invoice_issue') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
