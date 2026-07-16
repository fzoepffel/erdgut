<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_list') }}</a>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.detail_heading', ['id' => $order->id]) }}</h1>
            </div>
            <div class="text-right">
                <x-shop-order-status :order="$order" />
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status" data-testid="shop-admin-flash">{{ session('status') }}</p>
            @endif

            @if (session('error'))
                <p class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ session('error') }}</p>
            @endif

            @if ($order->restaurant_order_id !== null)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-testid="shop-admin-source-restaurant-banner">
                    <span>{{ __('shop.admin.source_request_note', ['id' => $order->restaurant_order_id]) }}</span>
                    <a href="{{ route('app.admin.restaurant-orders.show', $order->restaurant_order_id) }}"
                       class="whitespace-nowrap font-semibold underline hover:text-amber-800"
                       data-testid="shop-admin-source-restaurant-link">
                        {{ __('shop.admin.source_request_link') }} &rarr;
                    </a>
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                @if ($order->isEditableByAdmin())
                    <a href="{{ route('app.admin.shop-orders.edit', $order) }}"
                       class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                       data-testid="shop-admin-edit">
                        {{ __('shop.admin.action_edit') }}
                    </a>
                @endif
                @if ($order->hasOpenAmount())
                    @php $openAmount = number_format(abs($order->outstandingAmountEur()), 2, ',', '.').' €'; @endphp
                    <form method="POST" action="{{ route('app.admin.shop-orders.settle-outstanding', $order) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md border border-amber-400 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-100" data-testid="shop-admin-settle-outstanding">
                            {{ $order->outstandingAmountEur() > 0
                                ? __('shop.admin.action_settle_collected', ['amount' => $openAmount])
                                : __('shop.admin.action_settle_refunded', ['amount' => $openAmount]) }}
                        </button>
                    </form>
                @endif
                @if ($order->status === \App\Enums\ShopOrderStatus::PendingPayment)
                    <form method="POST" action="{{ route('app.admin.shop-orders.mark-paid', $order) }}">
                        @csrf
                        <button type="submit" class="btn-solid-sm" data-testid="shop-admin-mark-paid">
                            {{ __('shop.admin.action_mark_paid') }}
                        </button>
                    </form>
                @endif
                @if (! in_array($order->status, [\App\Enums\ShopOrderStatus::Fulfilled, \App\Enums\ShopOrderStatus::Cancelled, \App\Enums\ShopOrderStatus::Refunded], true))
                    <form method="POST" action="{{ route('app.admin.shop-orders.mark-completed', $order) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md bg-gray-800 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-gray-700" data-testid="shop-admin-mark-completed">
                            {{ __('shop.admin.action_mark_completed') }}
                        </button>
                    </form>
                @endif
                @if ($order->status === \App\Enums\ShopOrderStatus::Paid)
                    <form
                        method="POST"
                        action="{{ route('app.admin.shop-orders.refund', $order) }}"
                        onsubmit="return confirm({{ Js::from(__('shop.admin.confirm_refund', ['id' => $order->id, 'amount' => \App\Support\Money::format($order->effectivePaidAmountEur() ?? $order->total_amount_eur)])) }})"
                        class="flex items-center gap-2"
                    >
                        @csrf
                        <input type="text" name="refund_reason" placeholder="{{ __('shop.admin.refund_reason_placeholder') }}" class="rounded-md border-gray-300 text-sm" />
                        <button type="submit" class="inline-flex items-center rounded-md border border-chapter-meat bg-chapter-meat/10 px-3 py-1.5 text-sm font-semibold text-chapter-meat transition hover:bg-chapter-meat hover:text-white" data-testid="shop-admin-refund">
                            {{ __('shop.admin.action_refund') }}
                        </button>
                    </form>
                @endif

                {{-- WYSIWYG editors: mirrors get the B2B invoice editor,
                     regular orders get the receipt AND the shop invoice
                     editor (same UX as B2B). --}}
                @if ($order->restaurant_order_id !== null)
                    <a
                        href="{{ route('app.admin.shop-orders.invoice-editor', $order->restaurant_order_id) }}"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        data-testid="shop-admin-receipt-editor"
                    >
                        {{ __('restaurant.invoice_editor_open') }}
                    </a>
                @else
                    <a
                        href="{{ route('app.admin.shop-orders.receipt-editor', $order) }}"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        data-testid="shop-admin-receipt-editor"
                    >
                        {{ __('shop.admin.receipt_editor_open') }}
                    </a>
                    <a
                        href="{{ route('app.admin.shop-orders.shop-invoice-editor', $order) }}"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        data-testid="shop-admin-invoice-editor"
                    >
                        {{ __('shop.admin.invoice_editor_open') }}
                    </a>
                @endif

                @if ($order->restaurant_order_id !== null)
                    {{-- B2B mirror: the customer document is the Rechnung,
                         so its download + email actions live right here on
                         the order level once it is issued. --}}
                    @if ($order->sourceRestaurantOrder?->isInvoiced())
                        <a
                            href="{{ route('app.admin.restaurant-orders.invoice-pdf', $order->restaurant_order_id) }}"
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                            data-testid="shop-admin-invoice-download"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            {{ __('restaurant.invoice_download') }}
                        </a>
                        <a
                            href="{{ route('app.admin.shop-orders.invoice-mail', $order->restaurant_order_id) }}"
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                            data-testid="shop-admin-invoice-mail"
                        >
                            {{ __('restaurant.invoice_mail_open') }}
                        </a>
                    @endif
                @else
                    {{-- PDF receipt — always available, same signed URL the customer uses --}}
                    <a
                        href="{{ \App\Services\Shop\ShopOrderService::receiptUrl($order) }}"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        data-testid="shop-admin-receipt"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                        {{ __('shop.admin.action_download_receipt') }}
                    </a>

                    {{-- Rechnung: real invoice with an EB number from the
                         shared ledger, parallel to the Kaufbeleg. --}}
                    @if ($order->isInvoiced())
                        <a
                            href="{{ route('app.admin.shop-orders.invoice-pdf', $order) }}"
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                            data-testid="shop-admin-invoice-download"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                            {{ __('shop.admin.action_download_invoice') }}
                        </a>
                        <a
                            href="{{ route('app.admin.shop-orders.shop-invoice-mail', $order) }}"
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                            data-testid="shop-admin-invoice-mail"
                        >
                            {{ __('restaurant.invoice_mail_open') }}
                        </a>
                        <span class="text-xs text-gray-500" data-testid="shop-admin-invoice-meta">
                            {{ __('shop.admin.invoice_meta_line', ['number' => $order->invoice_number, 'date' => $order->invoiced_at?->format('d.m.Y')]) }}
                        </span>
                    @elseif ((float) $order->discount_eur > 0)
                        <span class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-500"
                              data-testid="shop-admin-invoice-blocked"
                              title="{{ __('shop.admin.invoice_blocked_discount') }}">
                            {{ __('shop.admin.invoice_blocked_discount') }}
                        </span>
                    @else
                        <form method="POST" action="{{ route('app.admin.shop-orders.issue-invoice', $order) }}"
                              onsubmit="return confirm({{ Js::from(__('shop.admin.confirm_issue_invoice')) }})">
                            @csrf
                            <button type="submit" class="btn-solid-sm" data-testid="shop-admin-issue-invoice">
                                {{ __('shop.admin.action_issue_invoice') }}
                            </button>
                        </form>
                        <a
                            href="{{ route('app.admin.shop-orders.shop-invoice-preview', $order) }}"
                            target="_blank"
                            class="text-sm text-gray-500 underline hover:text-gray-700"
                            data-testid="shop-admin-invoice-preview"
                        >
                            {{ __('shop.admin.invoice_preview') }}
                        </a>
                        {{-- Shared yearly ledger: aligning the sequence here
                             moves the next number for shop AND restaurant
                             invoices alike. --}}
                        <form method="POST" action="{{ route('app.admin.restaurant-orders.invoice-sequence') }}" class="flex items-center gap-1.5" data-testid="shop-admin-invoice-sequence">
                            @csrf
                            <span class="text-xs text-gray-500">{{ __('restaurant.admin_invoice_next_number', ['number' => \App\Support\InvoiceSequence::preview()]) }}</span>
                            <input type="number" name="next_invoice_number" min="1" max="9999"
                                   placeholder="{{ __('restaurant.admin_invoice_set_sequence_placeholder') }}"
                                   class="w-20 rounded border-gray-300 text-xs" required>
                            <button type="submit" class="rounded border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                {{ __('restaurant.admin_invoice_set_sequence_button') }}
                            </button>
                        </form>
                    @endif
                @endif

                <form
                    method="POST"
                    action="{{ route('app.admin.shop-orders.destroy', $order) }}"
                    onsubmit="return confirm({{ Js::from(__('shop.admin.confirm_delete', ['id' => $order->id])) }})"
                    class="ml-auto"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-chapter-meat/40 bg-chapter-meat/[0.06] px-3 py-1.5 text-sm font-semibold text-chapter-meat transition hover:border-chapter-meat hover:bg-chapter-meat hover:text-white" data-testid="shop-admin-delete">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                        </svg>
                        {{ __('shop.admin.action_delete') }}
                    </button>
                </form>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                {{-- Customer --}}
                <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.admin.section_customer') }}</h2>
                    <dl class="space-y-1 text-sm">
                        <div class="font-medium text-gray-800">{{ $order->name }}</div>
                        <div><a href="mailto:{{ $order->email }}" class="text-emerald-700 hover:underline">{{ $order->email }}</a></div>
                        @if ($order->phone)
                            <div class="text-gray-600">{{ $order->phone }}</div>
                        @endif
                    </dl>
                </div>

                {{-- Fulfillment --}}
                <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.admin.section_fulfillment') }}</h2>
                    <div class="space-y-1 text-sm text-gray-700">
                        <div class="font-medium text-gray-800">
                            {{ $order->fulfillment_mode === 'delivery' ? __('shop.fulfillment_delivery') : __('shop.fulfillment_pickup') }}
                        </div>
                        @if ($order->expected_delivery_date)
                            <div class="text-gray-600">{{ $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY') }}</div>
                        @endif
                        @if ($order->fulfillment_mode === 'delivery')
                            <div class="mt-2 text-gray-600">
                                {{ $order->delivery_street }}<br>
                                {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                            </div>
                            @if ($order->delivery_notes)
                                <div class="mt-1 italic text-gray-500">{{ $order->delivery_notes }}</div>
                            @endif
                        @else
                            @php $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location); @endphp
                            @if ($pickup)
                                <div class="mt-2 text-gray-600">
                                    <span class="font-semibold text-gray-800">{{ $pickup['name'] }}</span><br>
                                    {{ $pickup['street'] }}<br>
                                    {{ $pickup['postal_code'] }} {{ $pickup['city'] }}
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Items + totals. B2B orders get an extra net column: the
                 business price list is net, so the operative view should
                 read like it (the gross Total column stays authoritative). --}}
            @php
                $isBusiness = $order->customer_type === \App\Enums\CustomerType::Business;
                $sumCols = $isBusiness ? 4 : 3;
                $vatTotal = round($order->items->sum(fn ($i) => (float) $i->vat_amount_eur) + (float) $order->delivery_fee_vat_amount_eur, 2);
            @endphp
            <div class="overflow-x-auto rounded-lg border border-gray-100 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-600">
                            <th class="px-4 py-2">{{ __('shop.admin.col_product') }}</th>
                            <th class="px-4 py-2">{{ __('shop.admin.col_quantity') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('shop.admin.col_vat_rate') }}</th>
                            @if ($isBusiness)
                                <th class="px-4 py-2 text-right">{{ __('shop.admin.col_net_total') }}</th>
                            @endif
                            <th class="px-4 py-2 text-right">{{ __('shop.admin.col_line_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="px-4 py-2 text-gray-800">{{ $item->displayName() }}@if ($item->variant_name_snapshot) <span class="text-gray-500">· {{ $item->variant_name_snapshot }}</span>@endif
                                    @if ($item->isCustom())<span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[0.6rem] uppercase tracking-wide text-gray-500">{{ __('shop.admin.custom_line_tag') }}</span>@endif</td>
                                <td class="px-4 py-2 text-gray-600">{{ $item->displayQuantityLabel() }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ $item->vat_rate_snapshot_percent }}%</td>
                                @if ($isBusiness)
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-600" data-testid="shop-admin-line-net">@eur(round((float) $item->line_total_eur - (float) $item->vat_amount_eur, 2))</td>
                                @endif
                                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-800">@eur($item->line_total_eur)</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 text-sm">
                        <tr>
                            <td colspan="{{ $sumCols }}" class="px-4 py-2 text-right text-gray-600">{{ __('shop.summary_line_items') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-800">@eur($order->items_total_eur)</td>
                        </tr>
                        @if ((float) $order->discount_eur > 0)
                            <tr>
                                <td colspan="{{ $sumCols }}" class="px-4 py-2 text-right text-gray-600">{{ __('coupon.discount_line') }} {{ $order->coupon_code }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-emerald-700">− @eur($order->discount_eur)</td>
                            </tr>
                        @endif
                        @if ($order->fulfillment_mode === 'delivery')
                            <tr>
                                <td colspan="{{ $sumCols }}" class="px-4 py-2 text-right text-gray-600">
                                    {{ __('shop.summary_line_delivery') }}
                                    <span class="text-gray-400">({{ $order->delivery_fee_vat_rate_percent }}% {{ __('shop.admin.col_vat_rate') }}@if ($isBusiness), {{ __('shop.admin.col_net_total') }} @eur(round((float) $order->delivery_fee_eur - (float) $order->delivery_fee_vat_amount_eur, 2))@endif)</span>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-800">@eur($order->delivery_fee_eur)</td>
                            </tr>
                        @endif
                        @if ($isBusiness)
                            <tr class="text-xs text-gray-500">
                                <td colspan="{{ $sumCols }}" class="px-4 py-1 text-right">{{ __('shop.admin.label_net_total') }}</td>
                                <td class="px-4 py-1 text-right tabular-nums" data-testid="shop-admin-net-total">@eur(round((float) $order->total_amount_eur - $vatTotal, 2))</td>
                            </tr>
                            <tr class="text-xs text-gray-500">
                                <td colspan="{{ $sumCols }}" class="px-4 py-1 text-right">{{ __('shop.admin.label_vat_total') }}</td>
                                <td class="px-4 py-1 text-right tabular-nums">@eur($vatTotal)</td>
                            </tr>
                        @endif
                        <tr class="font-semibold">
                            <td colspan="{{ $sumCols }}" class="px-4 py-2 text-right text-gray-800">{{ __('shop.summary_line_total') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-900">@eur($order->total_amount_eur)</td>
                        </tr>
                        @if ($order->paid_at !== null && abs($order->outstandingAmountEur()) >= 0.01)
                            <tr>
                                <td colspan="{{ $sumCols }}" class="px-4 py-2 text-right text-gray-600">{{ __('shop.admin.label_already_paid') }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-800">@eur($order->effectivePaidAmountEur())</td>
                            </tr>
                            <tr class="font-semibold">
                                @if ($order->outstandingAmountEur() > 0)
                                    <td colspan="{{ $sumCols }}" class="px-4 py-2 text-right text-amber-800">{{ __('shop.admin.label_open_amount') }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-amber-800" data-testid="shop-admin-open-amount">@eur($order->outstandingAmountEur())</td>
                                @else
                                    <td colspan="{{ $sumCols }}" class="px-4 py-2 text-right text-violet-800">{{ __('shop.admin.label_refund_due') }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-violet-800" data-testid="shop-admin-refund-due">@eur(abs($order->outstandingAmountEur()))</td>
                                @endif
                            </tr>
                        @elseif ($order->outstanding_settled_at !== null && $order->outstanding_settled_amount_eur !== null)
                            {{-- The difference was settled in person; keep the trace visible. --}}
                            <tr>
                                <td colspan="{{ $sumCols + 1 }}" class="px-4 py-2 text-right text-xs text-emerald-700" data-testid="shop-admin-settled-note">
                                    {{ (float) $order->outstanding_settled_amount_eur >= 0
                                        ? __('shop.admin.settled_collected_note', [
                                            'amount' => number_format(abs((float) $order->outstanding_settled_amount_eur), 2, ',', '.').' €',
                                            'date' => $order->outstanding_settled_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm'),
                                        ])
                                        : __('shop.admin.settled_refunded_note', [
                                            'amount' => number_format(abs((float) $order->outstanding_settled_amount_eur), 2, ',', '.').' €',
                                            'date' => $order->outstanding_settled_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm'),
                                        ]) }}
                                </td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>

            {{-- Payment + lifecycle meta --}}
            <div class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('shop.admin.section_payment') }}</h2>
                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('shop.admin.order_source_label') }}</dt>
                        <dd class="text-gray-800">
                            @if ($order->order_source === \App\Enums\OrderSource::Admin)
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-gray-600">{{ __('shop.admin.order_source_admin') }}</span>
                            @else
                                {{ __('shop.admin.order_source_storefront') }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('shop.admin.customer_type_label') }}</dt>
                        <dd class="text-gray-800">
                            @if ($order->customer_type === \App\Enums\CustomerType::Business)
                                <span class="rounded bg-blue-50 px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide text-blue-700">{{ __('shop.admin.badge_b2b') }}</span>
                                {{ __('shop.admin.customer_type_business') }}
                            @else
                                {{ __('shop.admin.customer_type_private') }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('shop.summary_line_payment_method') }}</dt>
                        <dd class="text-gray-800">{{ $order->payment_method === 'in_person' ? __('shop.payment_in_person') : __('shop.payment_online') }}</dd>
                    </div>
                    @if ($order->paid_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ __('shop.admin.meta_paid_at') }}</dt>
                            <dd class="text-gray-800">{{ $order->paid_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm') }}</dd>
                        </div>
                    @endif
                    @if ($order->fulfilled_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ __('shop.admin.meta_fulfilled_at') }}</dt>
                            <dd class="text-gray-800">{{ $order->fulfilled_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm') }}</dd>
                        </div>
                    @endif
                    @if ((int) $order->payment_attempts > 0)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ __('shop.admin.meta_payment_attempts') }}</dt>
                            <dd class="text-chapter-meat">{{ (int) $order->payment_attempts }}</dd>
                        </div>
                    @endif
                    @if ($order->refunded_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ __('shop.admin.meta_refunded_at') }}</dt>
                            <dd class="text-gray-800">{{ $order->refunded_at->locale(app()->getLocale())->isoFormat('D. MMM YYYY, HH:mm') }}</dd>
                        </div>
                    @endif
                    @if ($order->refund_reason)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ __('shop.admin.meta_refund_reason') }}</dt>
                            <dd class="text-gray-800">{{ $order->refund_reason }}</dd>
                        </div>
                    @endif
                </dl>
                @if ((int) $order->refund_attempts > 0 && $order->status !== \App\Enums\ShopOrderStatus::Refunded)
                    <div class="mt-3 border border-chapter-meat/40 bg-chapter-meat/5 px-3 py-2 text-xs text-chapter-meat">
                        {{ trans_choice('shop.admin.refund_attempts_failed', (int) $order->refund_attempts, ['count' => (int) $order->refund_attempts]) }}
                        @if ($order->last_refund_error)
                            <div class="mt-1 italic text-brand-ink/65">{{ \Illuminate\Support\Str::limit($order->last_refund_error, 120) }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
