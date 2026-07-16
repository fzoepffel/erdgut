<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('app.admin.shop-orders.show', $order) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('shop.admin.back_to_order') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('shop.admin.receipt_editor_heading', ['id' => $order->id]) }}</h1>
        </div>
    </x-slot>

    @php
        // Seamless "part of the sheet" input styling: invisible until hover/focus.
        $inp = 'w-full rounded-none border-0 border-b border-dashed border-transparent bg-transparent p-0 text-[13px] leading-relaxed text-gray-900 placeholder:text-gray-300 hover:border-gray-300 focus:border-emerald-500 focus:ring-0';
        $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
        $items = $order->items;
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status" data-testid="receipt-editor-flash">{{ session('status') }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
                <button type="submit" form="receipt-editor-form" class="btn-solid-sm" data-testid="receipt-editor-save">
                    {{ __('shop.admin.receipt_editor_save') }}
                </button>
                <a href="{{ \App\Services\Shop\ShopOrderService::receiptUrl($order) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    {{ __('shop.admin.receipt_editor_pdf') }}
                </a>
                <a href="{{ route('app.admin.shop-orders.shop-invoice-preview', $order) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                   data-testid="receipt-editor-invoice-preview">
                    {{ __('shop.admin.invoice_preview') }}
                </a>
                @if ($order->isEditableByAdmin())
                    <a href="{{ route('app.admin.shop-orders.edit', $order) }}"
                       class="ml-auto inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                        {{ __('shop.admin.receipt_editor_amounts_link') }}
                    </a>
                @endif
            </div>
            <p class="px-1 text-xs text-gray-400">{{ __('shop.admin.receipt_editor_hint') }}</p>

            {{-- ── The sheet: looks like the Kaufbeleg, edits like a form ── --}}
            <form id="receipt-editor-form" method="POST" action="{{ route('app.admin.shop-orders.receipt-editor.save', $order) }}">
                @csrf
                <div class="mx-auto bg-white px-10 py-12 shadow-md sm:px-14" style="font-size: 13px; line-height: 1.5;">

                    <h2 class="mb-8 text-3xl font-bold tracking-tight text-gray-900">BELEG: #{{ $order->id }}</h2>

                    <div class="mb-8 flex items-start gap-6">
                        <img src="{{ asset('images/logo/erdgut-invoice.png') }}" alt="Erdgut" class="h-20 w-20 object-contain">
                        <div class="pt-1 text-[13px] text-gray-700">
                            <div>Bestellnummer: <strong>#{{ $order->id }}</strong></div>
                            <div>Belegdatum: <strong>{{ $order->created_at->format('d/m/Y') }}</strong></div>
                            @if ($order->expected_delivery_date)
                                <div>{{ $order->fulfillment_mode === 'pickup' ? 'Abholdatum' : 'Lieferdatum' }}: <strong>{{ $order->expected_delivery_date->format('d/m/Y') }}</strong></div>
                            @endif
                        </div>
                    </div>

                    <div class="my-5 border-t border-gray-200"></div>

                    <div class="mb-6 grid grid-cols-2 gap-8">
                        <div>
                            <h3 class="mb-2 text-sm font-bold text-gray-900">Von</h3>
                            <input type="text" name="overrides[seller_name]" value="{{ old('overrides.seller_name', $values['seller_name']) }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_register]" value="{{ old('overrides.seller_register', $values['seller_register']) }}" placeholder="{{ __('shop.admin.receipt_editor_optional') }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_vat_id]" value="{{ old('overrides.seller_vat_id', $values['seller_vat_id']) }}" placeholder="{{ __('shop.admin.receipt_editor_optional') }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_address]" value="{{ old('overrides.seller_address', $values['seller_address']) }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_email]" value="{{ old('overrides.seller_email', $values['seller_email']) }}" class="{{ $inp }}">
                        </div>
                        <div>
                            <h3 class="mb-2 text-sm font-bold text-gray-900">Empfänger</h3>
                            <input type="text" name="overrides[recipient_name]" value="{{ old('overrides.recipient_name', $values['recipient_name']) }}" class="{{ $inp }}" data-testid="receipt-editor-recipient-name">
                            <input type="text" name="overrides[recipient_email]" value="{{ old('overrides.recipient_email', $values['recipient_email']) }}" placeholder="{{ __('shop.admin.receipt_editor_optional') }}" class="{{ $inp }}">
                            <input type="text" name="overrides[recipient_phone]" value="{{ old('overrides.recipient_phone', $values['recipient_phone']) }}" placeholder="{{ __('shop.admin.receipt_editor_optional') }}" class="{{ $inp }}">
                            <input type="text" name="overrides[recipient_fulfillment]" value="{{ old('overrides.recipient_fulfillment', $values['recipient_fulfillment']) }}" class="{{ $inp }}">
                        </div>
                    </div>

                    <div class="my-5 border-t border-gray-200"></div>

                    <h3 class="mb-3 text-sm font-bold text-gray-900">Produkte &amp; Preise</h3>
                    <table class="w-full border border-gray-200 text-[13px]">
                        <thead>
                            <tr class="bg-gray-50 text-left text-[11px] text-gray-400">
                                <th class="border-b border-r border-gray-200 px-2.5 py-1.5 font-normal" style="width:46%">Bezeichnung</th>
                                <th class="border-b border-r border-gray-200 px-2.5 py-1.5 font-normal" style="width:18%">Menge</th>
                                <th class="border-b border-gray-200 px-2.5 py-1.5 font-normal" style="width:20%">Gesamt (inkl. MwSt.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $i => $item)
                                <tr>
                                    <td class="border-b border-r border-gray-100 px-2.5 py-2 align-top">
                                        <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                        <input type="text" name="items[{{ $i }}][display_name]"
                                               value="{{ old('items.'.$i.'.display_name', $item->receiptDisplayName()) }}"
                                               class="{{ $inp }}" data-testid="receipt-editor-line-name-{{ $i }}">
                                        @if ($item->product?->producer?->name)
                                            <div class="text-[10px] text-gray-400">{{ $item->product->producer->name }}</div>
                                        @endif
                                    </td>
                                    <td class="border-b border-r border-gray-100 px-2.5 py-2 align-top text-gray-700">{{ $item->displayQuantityLabel('de') }}</td>
                                    <td class="border-b border-gray-100 px-2.5 py-2 align-top text-gray-700">{{ $eur($item->line_total_eur) }}</td>
                                </tr>
                            @endforeach
                            @if ((float) $order->discount_eur > 0)
                                <tr>
                                    <td class="border-b border-r border-gray-100 px-2.5 py-2 text-gray-700">Rabatt{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</td>
                                    <td class="border-b border-r border-gray-100 px-2.5 py-2"></td>
                                    <td class="border-b border-gray-100 px-2.5 py-2 text-gray-700">&minus; {{ $eur($order->discount_eur) }}</td>
                                </tr>
                            @endif
                            @if ((float) $order->delivery_fee_eur > 0)
                                <tr>
                                    <td class="border-r border-gray-100 px-2.5 py-2">
                                        <input type="text" name="overrides[delivery_line_label]" value="{{ old('overrides.delivery_line_label', $values['delivery_line_label']) }}" class="{{ $inp }}">
                                    </td>
                                    <td class="border-r border-gray-100 px-2.5 py-2 text-gray-700">1</td>
                                    <td class="px-2.5 py-2 text-gray-700">{{ $eur($order->delivery_fee_eur) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-gray-400">{{ __('shop.admin.receipt_editor_amounts_note') }}</p>

                    <div class="mt-8 grid grid-cols-2 gap-8">
                        <div>
                            <h3 class="mb-2 border-b border-gray-200 pb-1.5 text-sm font-bold text-gray-900">Zahlung</h3>
                            <input type="text" name="overrides[payment_text]" value="{{ old('overrides.payment_text', $values['payment_text']) }}" class="{{ $inp }} font-bold">
                            @if ($order->paid_at !== null)
                                <div class="text-[13px] text-gray-700">Bezahlt am {{ $order->paid_at->format('d/m/Y') }}</div>
                            @endif
                        </div>
                        <div>
                            @foreach ($vatByRate as $rate => $amount)
                                <div class="text-[12px] text-gray-500">davon {{ $rate }} % MwSt.: {{ $eur($amount) }}</div>
                            @endforeach
                            @if ($order->paid_at !== null && abs($order->outstandingAmountEur()) >= 0.01)
                                <div class="text-[13px]">Bereits bezahlt (Online-Zahlung): <strong>{{ $eur($order->effectivePaidAmountEur()) }}</strong></div>
                                @if ($order->outstandingAmountEur() > 0)
                                    <div class="text-[13px]">Offener Betrag (Zahlung vor Ort): <strong>{{ $eur($order->outstandingAmountEur()) }}</strong></div>
                                @else
                                    <div class="text-[13px]">Zu erstatten: <strong>{{ $eur(abs($order->outstandingAmountEur())) }}</strong></div>
                                @endif
                            @endif
                            <div class="mt-2 text-[13px] font-bold">Gesamtbetrag:</div>
                            <div class="mt-1 bg-[#edf2ea] px-4 py-2 text-2xl font-bold tabular-nums">{{ $eur($order->total_amount_eur) }}</div>
                        </div>
                    </div>

                    <div class="mt-8 grid grid-cols-2 gap-8 border-t border-gray-200 pt-5">
                        <div>
                            <h3 class="mb-1.5 text-sm font-bold text-gray-900">Fragen?</h3>
                            <textarea name="overrides[questions_text]" rows="2" class="{{ $inp }} resize-none">{{ old('overrides.questions_text', $values['questions_text']) }}</textarea>
                        </div>
                        <div>
                            <h3 class="mb-1.5 text-sm font-bold text-gray-900">Hinweise</h3>
                            <textarea name="overrides[notes_text]" rows="2" class="{{ $inp }} resize-none">{{ old('overrides.notes_text', $values['notes_text']) }}</textarea>
                        </div>
                    </div>

                    {{-- Invoice-only fields: bank block + payment terms as
                         printed on the Rechnung. The Kaufbeleg ignores them. --}}
                    <div class="mt-8 border-t border-gray-200 pt-5">
                        <h3 class="mb-1 text-sm font-bold text-gray-900">{{ __('shop.admin.receipt_editor_invoice_section') }}</h3>
                        <p class="mb-3 text-xs text-gray-400">{{ __('shop.admin.receipt_editor_invoice_section_hint') }}</p>
                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <div class="text-[13px] font-bold">Banküberweisung</div>
                                <input type="text" name="overrides[bank_name]" value="{{ old('overrides.bank_name', $values['bank_name']) }}" placeholder="Bankname" class="{{ $inp }}" data-testid="receipt-editor-bank-name">
                                <input type="text" name="overrides[bank_holder]" value="{{ old('overrides.bank_holder', $values['bank_holder']) }}" placeholder="Kontoinhaber" class="{{ $inp }}">
                                <input type="text" name="overrides[bank_iban]" value="{{ old('overrides.bank_iban', $values['bank_iban']) }}" placeholder="IBAN" class="{{ $inp }}">
                                <input type="text" name="overrides[bank_reference]" value="{{ old('overrides.bank_reference', $values['bank_reference']) }}" placeholder="{{ __('shop.admin.receipt_editor_bank_reference_placeholder') }}" class="{{ $inp }}">
                            </div>
                            <div>
                                <h4 class="mb-1.5 text-[13px] font-bold text-gray-900">Zahlungsbedingungen</h4>
                                <textarea name="overrides[terms_text]" rows="3" class="{{ $inp }} resize-none" data-testid="receipt-editor-terms">{{ old('overrides.terms_text', $values['terms_text']) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-10 flex items-center justify-between text-[10px] text-gray-300">
                        <span>BELEG: #{{ $order->id }}</span>
                        <span>1</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
