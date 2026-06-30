<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('app.admin.restaurant-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('restaurant.admin_orders_title') }}</a>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.order_ref', ['id' => $order->id]) }}</h1>
            </div>
            @php $status = $order->status->value; @endphp
            <span @class([
                'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
                'bg-amber-100 text-amber-800' => $status === 'submitted',
                'bg-green-100 text-green-800' => $status === 'confirmed' || $status === 'fulfilled',
                'bg-gray-100 text-gray-600' => $status === 'cancelled' || $status === 'declined',
            ])>{{ __('restaurant.order_status_'.$status) }}</span>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div role="status" class="border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            @if ($order->source_standing_order_id !== null && optional($order->standingOrder)->isPending())
                <div class="border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">{{ __('restaurant.admin_recurring_note') }}</div>
            @endif

            {{-- Actions --}}
            @if ($order->isSubmitted() || $order->status === \App\Enums\RestaurantOrderStatus::Confirmed)
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    @if ($order->isSubmitted())
                        <div class="grid gap-4 sm:grid-cols-2">
                            <form method="POST" action="{{ route('app.admin.restaurant-orders.confirm', $order) }}" class="space-y-2">
                                @csrf
                                <textarea name="note" rows="2" placeholder="{{ __('restaurant.admin_orders_note') }}" class="block w-full rounded border-gray-300 text-sm"></textarea>
                                <button type="submit" data-testid="restaurant-order-confirm" class="w-full rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_orders_confirm') }}</button>
                            </form>
                            <form method="POST" action="{{ route('app.admin.restaurant-orders.decline', $order) }}" class="space-y-2"
                                  onsubmit="return confirm(@js(__('restaurant.admin_orders_decline_confirm')))">
                                @csrf
                                <textarea name="note" rows="2" placeholder="{{ __('restaurant.admin_orders_note') }}" class="block w-full rounded border-gray-300 text-sm"></textarea>
                                <button type="submit" data-testid="restaurant-order-decline" class="w-full rounded border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">{{ __('restaurant.admin_orders_decline') }}</button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('app.admin.restaurant-orders.fulfil', $order) }}">
                            @csrf
                            <button type="submit" data-testid="restaurant-order-fulfil" class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_orders_fulfil') }}</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Meta --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('restaurant.field_business_name') }}</p>
                    <p class="mt-1 text-sm text-gray-800">{{ $order->user?->name ?? '—' }}</p>
                    <p class="text-sm text-gray-500">{{ $order->user?->email }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('restaurant.cart_delivery_address') }}</p>
                    <p class="mt-1 text-sm text-gray-800">{{ $order->delivery_street }}, {{ $order->delivery_postal_code }} {{ $order->delivery_city }}</p>
                    <p class="text-sm text-gray-500">{{ __('restaurant.catalog_date_label') }}: {{ $order->expected_delivery_date->format('d.m.Y') }}</p>
                    @if ($order->delivery_notes)<p class="mt-1 text-sm text-gray-500">{{ $order->delivery_notes }}</p>@endif
                </div>
            </div>

            @if ($order->isInvoiced())
                {{-- Invoiced: frozen, with a download link --}}
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
                    <p class="font-semibold">{{ __('restaurant.admin_invoice_issued', ['number' => $order->invoice_number]) }}</p>
                    <p class="mt-1">{{ __('restaurant.admin_invoice_dated', ['date' => optional($order->invoiced_at)->format('d.m.Y'), 'due' => optional($order->invoice_due_date)->format('d.m.Y')]) }}</p>
                    <a href="{{ route('app.admin.restaurant-orders.invoice-pdf', $order) }}" class="mt-2 inline-block font-medium text-emerald-800 underline">{{ __('restaurant.invoice_download') }}</a>
                </div>
            @endif

            {{-- Items / pricing --}}
            @if ($order->isInvoiceable())
                <form method="POST" action="{{ route('app.admin.restaurant-orders.pricing', $order) }}"
                      class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    @csrf
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('restaurant.admin_orders_item') }}</th>
                                <th class="px-4 py-3">{{ __('restaurant.admin_orders_qty') }}</th>
                                <th class="px-4 py-3">{{ __('restaurant.admin_orders_net') }}</th>
                                <th class="px-4 py-3">{{ __('restaurant.admin_status') }}</th>
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
                                    <td class="px-4 py-3">
                                        @if ($item->isReserved())<span class="text-emerald-700">{{ __('restaurant.in_stock') }}</span>@else<span class="text-amber-700">{{ __('restaurant.on_request') }}</span>@endif
                                    </td>
                                    <td class="px-4 py-3"><input type="checkbox" name="items[{{ $i }}][remove]" value="1" class="rounded border-gray-300"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 font-semibold">
                                <td class="px-4 py-3" colspan="2">{{ __('restaurant.order_net_total') }}</td>
                                <td class="px-4 py-3 font-mono">@eur($order->items_total_net_eur)</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 bg-gray-50 px-4 py-3">
                        <button type="submit" data-testid="restaurant-order-save-pricing" class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-white">{{ __('restaurant.admin_invoice_save') }}</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('app.admin.restaurant-orders.invoice', $order) }}"
                      onsubmit="return confirm(@js(__('restaurant.admin_invoice_issue_confirm')))">
                    @csrf
                    <button type="submit" data-testid="restaurant-order-issue-invoice" class="rounded bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">{{ __('restaurant.admin_invoice_issue') }}</button>
                </form>
            @else
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
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
                                <tr>
                                    <td class="px-4 py-3 text-gray-800">{{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}</td>
                                    <td class="px-4 py-3">@if ($item->isReserved())<span class="text-emerald-700">{{ __('restaurant.in_stock') }}</span>@else<span class="text-amber-700">{{ __('restaurant.on_request') }}</span>@endif</td>
                                    <td class="px-4 py-3 text-right text-gray-700">@if ($item->isCustom() && ! $order->isInvoiced())<span class="text-gray-500">{{ __('restaurant.price_on_request') }}</span>@else<span class="font-mono">@eur($item->lineNetEur())</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-200 font-semibold">
                                <td class="px-4 py-3" colspan="2">{{ __('restaurant.order_net_total') }}</td>
                                <td class="px-4 py-3 text-right font-mono">@eur($order->items_total_net_eur)</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
