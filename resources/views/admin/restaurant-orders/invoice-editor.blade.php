<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('app.admin.restaurant-orders.show', $order) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('restaurant.invoice_editor_back') }}</a>
                <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('restaurant.invoice_editor_heading', ['number' => $previewNumber]) }}</h1>
            </div>
        </div>
    </x-slot>

    @php
        // Seamless "part of the bill" input styling: invisible until hover/focus.
        $inp = 'w-full rounded-none border-0 border-b border-dashed border-transparent bg-transparent p-0 text-[12px] leading-snug text-gray-900 placeholder:text-gray-300 hover:border-gray-300 focus:border-emerald-500 focus:ring-0';
        $inpRight = $inp.' text-right tabular-nums';

        // factor converts canonical quantity to the display unit (g→kg etc.)
        // so the live unit-price column matches the PDF's per-display price.
        $existingConfig = $items->map(function ($item) {
            if ($item->product?->unit !== null) {
                $display = $item->product->price_display_unit ?? $item->product->unit->defaultDisplayUnit();
                $factor = max(1, $item->product->unit->canonicalUnitsPerDisplay($display));
            } else {
                $factor = in_array((string) $item->unit, ['g', 'ml'], true) ? 1000 : 1;
            }

            $displayQty = $factor > 0 ? ((float) $item->quantity) / $factor : (float) $item->quantity;

            return [
                'qty' => (float) $item->quantity,
                'net' => round($item->lineNetEur(), 2),
                // Prefilled net price per display unit; editing price or
                // quantity recomputes the total (price-first UX). Untouched
                // lines submit their exact stored total, so no rounding
                // drift on a plain re-save.
                'price' => $displayQty > 0 ? round($item->lineNetEur() / $displayQty, 4) : 0.0,
                'vat' => (int) $item->vat_rate_snapshot_percent,
                'factor' => $factor,
                'touched' => false,
                'removed' => false,
            ];
        })->values()->all();

        // German price-unit suffix ("/ kg") per line for the Preis column.
        $priceUnitLabels = $items->map(function ($item) {
            if ($item->product?->unit !== null) {
                $display = $item->product->price_display_unit ?? $item->product->unit->defaultDisplayUnit();

                return match ($display) {
                    'piece' => 'Stück',
                    'bunch' => 'Bund',
                    default => \App\Enums\ProductUnit::displaySymbol($display),
                };
            }

            return match ((string) $item->unit) {
                'g' => 'kg', 'ml' => 'L', 'piece' => 'Stück', 'bunch' => 'Bund',
                default => \App\Enums\ProductUnit::displaySymbol((string) $item->unit),
            };
        })->values()->all();

        $unitOptions = [
            ['v' => 'piece', 'l' => __('units.piece')],
            ['v' => 'bunch', 'l' => __('units.bunch')],
            ['v' => 'kg', 'l' => 'kg'],
            ['v' => 'g', 'l' => 'g'],
            ['v' => 'l', 'l' => 'L'],
            ['v' => 'ml', 'l' => 'ml'],
        ];

        $dueDateValue = old('invoice_due_date', $order->invoice_due_date?->toDateString()
            ?? ($order->expected_delivery_date?->copy()->addDays($dueDays)->toDateString()));
    @endphp

    {{-- Single-quoted x-data: the config is json_encode output (double-quoted
         strings), which would terminate a double-quoted attribute early.
         JSON_HEX_APOS keeps apostrophes from closing the single quotes. --}}
    <div class="py-8"
         x-data='invoiceEditor({
             existing: {!! json_encode($existingConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) !!},
             feeNet: {!! json_encode($order->delivery_fee_net_eur !== null ? (float) $order->delivery_fee_net_eur : null, JSON_HEX_APOS) !!},
             feeVat: {{ (int) ($order->delivery_fee_vat_rate_percent ?? 7) }}
         })'>
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <p class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status" data-testid="invoice-editor-flash">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Action bar (outside the main form: the issue action and the
                 sequence alignment are separate POSTs). --}}
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-100 bg-white p-3 shadow-sm">
                <button type="submit" form="invoice-editor-form" class="btn-solid-sm" data-testid="invoice-editor-save">
                    {{ __('restaurant.invoice_editor_save') }}
                </button>
                {{-- Submits the CURRENT (possibly unsaved) sheet to the live
                     preview route, which renders and rolls back. --}}
                <button type="submit" form="invoice-editor-form"
                        formaction="{{ route('app.admin.restaurant-orders.invoice-editor.preview', $order) }}"
                        formtarget="_blank"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        data-testid="invoice-editor-preview">
                    {{ __('restaurant.invoice_editor_preview') }}
                </button>
                @if ($order->isInvoiced())
                    <a href="{{ route('app.admin.restaurant-orders.invoice-mail', $order) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                        {{ __('restaurant.invoice_mail_open') }}
                    </a>
                @endif
                @if (! $order->isInvoiced() && $order->isInvoiceable())
                    <form method="POST" action="{{ route('app.admin.restaurant-orders.invoice', $order) }}"
                          onsubmit="return confirm(@js(__('restaurant.admin_invoice_issue_confirm')))">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-emerald-800">
                            {{ __('restaurant.admin_invoice_issue') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('app.admin.restaurant-orders.invoice-sequence') }}" class="ml-auto flex items-center gap-1.5">
                        @csrf
                        <span class="text-xs text-gray-500">{{ __('restaurant.admin_invoice_next_number', ['number' => $previewNumber]) }}</span>
                        <input type="number" name="next_invoice_number" min="1" max="9999"
                               placeholder="{{ __('restaurant.admin_invoice_set_sequence_placeholder') }}"
                               class="w-20 rounded border-gray-300 text-xs" required>
                        <button type="submit" class="rounded border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">
                            {{ __('restaurant.admin_invoice_set_sequence_button') }}
                        </button>
                    </form>
                @endif
            </div>
            <p class="px-1 text-xs text-gray-400">{{ __('restaurant.invoice_editor_hint_defaults') }}</p>

            {{-- ── The sheet: looks like the invoice, edits like a form ── --}}
            <form id="invoice-editor-form" method="POST" action="{{ route('app.admin.restaurant-orders.invoice-editor.save', $order) }}">
                @csrf
                <div class="mx-auto bg-white px-8 py-10 shadow-md sm:px-12" style="font-size: 12px; line-height: 1.45;">

                    <h2 class="mb-6 text-2xl font-bold tracking-tight text-gray-900">RECHNUNG: {{ $previewNumber }}</h2>

                    <div class="mb-6 flex items-start gap-5">
                        <img src="{{ asset('images/logo/erdgut-invoice.png') }}" alt="Erdgut" class="h-16 w-16 object-contain">
                        <div class="pt-1 text-[13px] text-gray-700">
                            <div>Rechnung No: <strong>{{ $previewNumber }}</strong>
                                @unless ($order->isInvoiced())
                                    <span class="text-gray-400">({{ __('restaurant.invoice_editor_draft') }})</span>
                                @endunless
                            </div>
                            <div>Rechnungsdatum: <strong>{{ ($order->invoiced_at ?? now())->format('d/m/Y') }}</strong></div>
                            <div class="flex items-center gap-1.5">
                                <span>Fälligkeitsdatum:</span>
                                <input type="date" name="invoice_due_date" value="{{ $dueDateValue }}"
                                       class="rounded-none border-0 border-b border-dashed border-gray-300 bg-transparent p-0 text-[13px] font-bold text-gray-900 focus:border-emerald-500 focus:ring-0"
                                       data-testid="invoice-editor-due-date">
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span>Lieferdatum:</span>
                                <input type="date" name="expected_delivery_date"
                                       value="{{ old('expected_delivery_date', $order->expected_delivery_date?->toDateString()) }}"
                                       class="rounded-none border-0 border-b border-dashed border-gray-300 bg-transparent p-0 text-[13px] font-bold text-gray-900 focus:border-emerald-500 focus:ring-0"
                                       data-testid="invoice-editor-delivery-date">
                            </div>
                        </div>
                    </div>

                    <div class="my-5 border-t border-gray-200"></div>

                    <div class="mb-6 grid grid-cols-2 gap-8">
                        <div>
                            <h3 class="mb-2 text-sm font-bold text-gray-900">Von</h3>
                            <input type="text" name="overrides[seller_name]" value="{{ old('overrides.seller_name', $values['seller_name']) }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_register]" value="{{ old('overrides.seller_register', $values['seller_register']) }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_vat_id]" value="{{ old('overrides.seller_vat_id', $values['seller_vat_id']) }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_address]" value="{{ old('overrides.seller_address', $values['seller_address']) }}" class="{{ $inp }}">
                            <input type="text" name="overrides[seller_email]" value="{{ old('overrides.seller_email', $values['seller_email']) }}" class="{{ $inp }}">
                        </div>
                        <div>
                            <h3 class="mb-2 text-sm font-bold text-gray-900">Empfänger</h3>
                            <input type="text" name="overrides[recipient_name]" value="{{ old('overrides.recipient_name', $values['recipient_name']) }}" class="{{ $inp }}" data-testid="invoice-editor-recipient-name">
                            <input type="text" name="overrides[recipient_extra]" value="{{ old('overrides.recipient_extra', $values['recipient_extra']) }}" placeholder="{{ __('restaurant.invoice_editor_optional_line') }}" class="{{ $inp }}">
                            <input type="text" name="overrides[recipient_address]" value="{{ old('overrides.recipient_address', $values['recipient_address']) }}" class="{{ $inp }}">
                            <input type="text" name="overrides[recipient_phone]" value="{{ old('overrides.recipient_phone', $values['recipient_phone']) }}" placeholder="{{ __('restaurant.invoice_editor_optional_line') }}" class="{{ $inp }}">
                            <input type="text" name="overrides[recipient_vat_id]" value="{{ old('overrides.recipient_vat_id', $values['recipient_vat_id']) }}" placeholder="{{ __('restaurant.invoice_editor_optional_line') }}" class="{{ $inp }}">
                        </div>
                    </div>

                    <div class="my-5 border-t border-gray-200"></div>

                    <h3 class="mb-3 text-sm font-bold text-gray-900">Produkte &amp; Preise</h3>
                    <table class="w-full border border-gray-200 text-[12px]">
                        <thead>
                            <tr class="bg-gray-50 text-left text-[11px] text-gray-400">
                                <th class="border-b border-r border-gray-200 px-2 py-1 font-normal" style="width:42%">Bezeichnung</th>
                                <th class="border-b border-r border-gray-200 px-2 py-1 font-normal" style="width:17%">Preis (ohne MwSt.)</th>
                                <th class="border-b border-r border-gray-200 px-2 py-1 font-normal" style="width:17%">Menge</th>
                                <th class="border-b border-r border-gray-200 px-2 py-1 font-normal" style="width:20%">Gesamt (ohne MwSt.)</th>
                                <th class="border-b border-gray-200 px-1 py-1.5" style="width:4%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $i => $item)
                                <tr :class="existing[{{ $i }}].removed && 'opacity-30'">
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                        <input type="text" name="items[{{ $i }}][display_name]"
                                               value="{{ old('items.'.$i.'.display_name', $item->invoiceDisplayName()) }}"
                                               class="{{ $inp }}" data-testid="invoice-editor-line-name-{{ $i }}">
                                    </td>
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        <span class="flex items-baseline gap-1">
                                            {{-- step=any: the prefilled unit price is derived from
                                                 total/quantity and may carry 4 decimals (2,5235). --}}
                                            <input type="number" step="any" min="0" x-model.number="existing[{{ $i }}].price"
                                                   x-on:input="existing[{{ $i }}].touched = true"
                                                   class="{{ $inpRight }}" data-testid="invoice-editor-line-price-{{ $i }}">
                                            <span class="shrink-0 text-gray-500">€ / {{ $priceUnitLabels[$i] }}</span>
                                        </span>
                                    </td>
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        <span class="flex items-baseline gap-1">
                                            <input type="number" step="0.0001" min="0.0001" name="items[{{ $i }}][quantity]"
                                                   x-model.number="existing[{{ $i }}].qty"
                                                   x-on:input="existing[{{ $i }}].touched = true"
                                                   class="{{ $inpRight }}" style="max-width:70px;">
                                            <span class="text-gray-500">{{ $item->isCustom() ? \App\Enums\ProductUnit::displaySymbol((string) $item->unit) : $item->product?->canonicalUnitLabel() }}</span>
                                        </span>
                                    </td>
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        {{-- Computed: Preis × Menge. The hidden input submits the
                                             exact stored total while the line is untouched. --}}
                                        <span class="tabular-nums text-gray-900" x-text="fmt(lineNet(existing[{{ $i }}]))" data-testid="invoice-editor-line-total-{{ $i }}"></span>
                                        <input type="hidden" name="items[{{ $i }}][net_total_eur]" :value="lineNet(existing[{{ $i }}]).toFixed(2)">
                                        <select name="items[{{ $i }}][vat_rate_percent]" x-model.number="existing[{{ $i }}].vat"
                                                class="mt-0.5 w-full border-0 bg-transparent p-0 text-[10px] text-gray-400 focus:ring-0">
                                            <option value="7" @selected($item->vat_rate_snapshot_percent === 7)>zzgl. 7 % MwSt.</option>
                                            <option value="19" @selected($item->vat_rate_snapshot_percent === 19)>zzgl. 19 % MwSt.</option>
                                        </select>
                                    </td>
                                    <td class="border-b border-gray-100 px-1 py-2 text-center align-top">
                                        <label class="cursor-pointer text-gray-300 hover:text-red-500" title="{{ __('restaurant.invoice_editor_remove_line') }}">
                                            <input type="checkbox" name="items[{{ $i }}][remove]" value="1" x-model="existing[{{ $i }}].removed" class="sr-only">
                                            <span aria-hidden="true" x-text="existing[{{ $i }}].removed ? '↺' : '✕'"></span>
                                        </label>
                                    </td>
                                </tr>
                            @endforeach

                            {{-- New off-catalog lines. --}}
                            <template x-for="(line, idx) in extra" :key="idx">
                                <tr>
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        <input type="text" :name="'items[' + ({{ count($items) }} + idx) + '][name]'"
                                               x-model="line.name" placeholder="{{ __('restaurant.invoice_editor_new_line_placeholder') }}"
                                               class="{{ $inp }}" required>
                                    </td>
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        <span class="flex items-baseline gap-1">
                                            <input type="number" step="any" min="0" x-model.number="line.price"
                                                   class="{{ $inpRight }}" required>
                                            <span class="shrink-0 text-gray-500">€ / <span x-text="priceUnitLabel(line.unit)"></span></span>
                                        </span>
                                    </td>
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        <span class="flex items-baseline gap-1">
                                            <input type="number" step="0.0001" min="0.0001" :name="'items[' + ({{ count($items) }} + idx) + '][quantity]'"
                                                   x-model.number="line.qty" class="{{ $inpRight }}" style="max-width:70px;">
                                            <select :name="'items[' + ({{ count($items) }} + idx) + '][unit]'" x-model="line.unit"
                                                    class="border-0 bg-transparent p-0 text-[12px] text-gray-500 focus:ring-0">
                                                @foreach ($unitOptions as $opt)
                                                    <option value="{{ $opt['v'] }}">{{ $opt['l'] }}</option>
                                                @endforeach
                                            </select>
                                        </span>
                                    </td>
                                    <td class="border-b border-r border-gray-100 px-2 py-1 align-top">
                                        <span class="tabular-nums text-gray-900" x-text="fmt(lineNet(line))"></span>
                                        <input type="hidden" :name="'items[' + ({{ count($items) }} + idx) + '][net_total_eur]'" :value="lineNet(line).toFixed(2)">
                                        <select :name="'items[' + ({{ count($items) }} + idx) + '][vat_rate_percent]'" x-model.number="line.vat"
                                                class="mt-0.5 w-full border-0 bg-transparent p-0 text-[10px] text-gray-400 focus:ring-0">
                                            <option value="7">zzgl. 7 % MwSt.</option>
                                            <option value="19">zzgl. 19 % MwSt.</option>
                                        </select>
                                    </td>
                                    <td class="border-b border-gray-100 px-1 py-2 text-center align-top">
                                        <button type="button" class="text-gray-300 hover:text-red-500" x-on:click="extra.splice(idx, 1)" title="{{ __('restaurant.invoice_editor_remove_line') }}">✕</button>
                                    </td>
                                </tr>
                            </template>

                            {{-- Delivery fee as its own line, like on the PDF. --}}
                            <tr :class="!(feeNet > 0) && 'opacity-40'">
                                <td class="border-r border-gray-100 px-2 py-1 align-top">
                                    <input type="text" name="overrides[delivery_line_label]"
                                           value="{{ old('overrides.delivery_line_label', $values['delivery_line_label']) }}"
                                           class="{{ $inp }}">
                                </td>
                                <td class="border-r border-gray-100 px-2 py-1 align-top text-gray-700">
                                    <span x-text="feeNet > 0 ? fmt(feeNet) : ''"></span>
                                </td>
                                <td class="border-r border-gray-100 px-2 py-1 align-top text-gray-700">1</td>
                                <td class="border-r border-gray-100 px-2 py-1 align-top">
                                    <span class="flex items-baseline gap-1">
                                        <input type="number" step="0.01" min="0" max="999.99" name="delivery_fee_net_eur"
                                               x-model.number="feeNet" placeholder="0,00"
                                               class="{{ $inpRight }}" data-testid="invoice-editor-fee">
                                        <span class="text-gray-500">€</span>
                                    </span>
                                    <select name="delivery_fee_vat_rate_percent" x-model.number="feeVat"
                                            class="mt-0.5 w-full border-0 bg-transparent p-0 text-[10px] text-gray-400 focus:ring-0">
                                        <option value="7">zzgl. 7 % MwSt.</option>
                                        <option value="19">zzgl. 19 % MwSt.</option>
                                    </select>
                                </td>
                                <td class="px-1 py-2"></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" x-on:click="addLine()" class="mt-2 text-xs font-medium text-emerald-700 hover:text-emerald-900" data-testid="invoice-editor-add-line">
                        + {{ __('restaurant.invoice_editor_add_line') }}
                    </button>

                    <div class="mt-8 grid grid-cols-2 gap-8">
                        <div>
                            <h3 class="mb-2 border-b border-gray-200 pb-1.5 text-sm font-bold text-gray-900">Zahlungsmethoden</h3>
                            <div class="font-bold">Banküberweisung</div>
                            <div class="flex items-baseline gap-1"><span class="shrink-0 text-gray-700">Bankname:</span>
                                <input type="text" name="overrides[bank_name]" value="{{ old('overrides.bank_name', $values['bank_name']) }}" class="{{ $inp }}"></div>
                            <div class="flex items-baseline gap-1"><span class="shrink-0 text-gray-700">Kontoinhaber:</span>
                                <input type="text" name="overrides[bank_holder]" value="{{ old('overrides.bank_holder', $values['bank_holder']) }}" class="{{ $inp }}"></div>
                            <div class="text-gray-700">IBAN:</div>
                            <input type="text" name="overrides[bank_iban]" value="{{ old('overrides.bank_iban', $values['bank_iban']) }}" class="{{ $inp }}">
                            <div class="flex items-baseline gap-1"><span class="shrink-0 text-gray-700">Verwendungszweck:</span>
                                <input type="text" name="overrides[bank_reference]" value="{{ old('overrides.bank_reference', $values['bank_reference']) }}" class="{{ $inp }}"></div>
                        </div>
                        <div>
                            <div class="text-[13px]">Gesamtbetrag Netto: <strong x-text="fmt(netTotal)"></strong></div>
                            <template x-for="row in vatRows" :key="row.rate">
                                <div class="text-[13px]">Umsatzsteuer (<span x-text="row.rate"></span>%): <strong x-text="fmt(row.amount)"></strong></div>
                            </template>
                            <div class="mt-2 text-[13px] font-bold">Rechnungsbetrag:</div>
                            <div class="mt-1 bg-[#edf2ea] px-4 py-2 text-2xl font-bold tabular-nums" data-testid="invoice-editor-gross" x-text="fmt(grossTotal)"></div>
                        </div>
                    </div>

                    <div class="mt-8 grid grid-cols-2 gap-8 border-t border-gray-200 pt-5">
                        <div>
                            <h3 class="mb-1.5 text-sm font-bold text-gray-900">Fragen?</h3>
                            <textarea name="overrides[questions_text]" rows="2" class="{{ $inp }} resize-none">{{ old('overrides.questions_text', $values['questions_text']) }}</textarea>
                        </div>
                        <div>
                            <h3 class="mb-1.5 text-sm font-bold text-gray-900">Zahlungsbedingungen</h3>
                            <textarea name="overrides[terms_text]" rows="2" class="{{ $inp }} resize-none">{{ old('overrides.terms_text', $values['terms_text']) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-10 flex items-center justify-between text-[10px] text-gray-300">
                        <span>RECHNUNG: {{ $previewNumber }}</span>
                        <span>1</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    function invoiceEditor(config) {
        return {
            existing: config.existing || [],
            extra: [],
            feeNet: config.feeNet,
            feeVat: config.feeVat || 7,

            addLine() {
                this.extra.push({ name: "", qty: 1, unit: "piece", price: null, net: null, vat: 7, factor: 1, touched: true });
            },

            // g/ml quantities pair with a per-kg/per-L price.
            effectiveFactor(line) {
                if (line.unit === "g" || line.unit === "ml") return 1000;
                return parseFloat(line.factor) || 1;
            },

            // The net line total: Preis × Menge for edited lines; untouched
            // lines keep their exact stored total (no rounding drift).
            lineNet(line) {
                if (!line.touched && line.net !== null && line.net !== undefined) {
                    return parseFloat(line.net) || 0;
                }
                const price = parseFloat(line.price) || 0;
                const qty = (parseFloat(line.qty) || 0) / this.effectiveFactor(line);
                return Math.round(price * qty * 100) / 100;
            },

            priceUnitLabel(unit) {
                const labels = { piece: "Stück", bunch: "Bund", kg: "kg", g: "kg", l: "L", ml: "L" };
                return labels[unit] || unit || "";
            },

            liveLines() {
                return this.existing.filter((l) => !l.removed).concat(this.extra);
            },

            get netTotal() {
                const fee = parseFloat(this.feeNet);
                return this.liveLines().reduce((sum, l) => sum + this.lineNet(l), 0)
                    + (Number.isNaN(fee) ? 0 : fee);
            },

            get vatRows() {
                const byRate = {};
                this.liveLines().forEach((l) => {
                    const net = this.lineNet(l);
                    const rate = parseInt(l.vat, 10) || 0;
                    if (net <= 0) return;
                    byRate[rate] = (byRate[rate] || 0) + net * rate / 100;
                });
                const fee = parseFloat(this.feeNet);
                if (!Number.isNaN(fee) && fee > 0) {
                    const rate = parseInt(this.feeVat, 10) || 0;
                    byRate[rate] = (byRate[rate] || 0) + fee * rate / 100;
                }
                return Object.keys(byRate)
                    .map((r) => parseInt(r, 10))
                    .sort((a, b) => a - b)
                    .map((rate) => ({ rate, amount: Math.round(byRate[rate] * 100) / 100 }));
            },

            get grossTotal() {
                return this.netTotal + this.vatRows.reduce((sum, r) => sum + r.amount, 0);
            },

            fmt(value) {
                const n = parseFloat(value);
                if (Number.isNaN(n)) return "";
                return n.toFixed(2).replace(".", ",") + " €";
            },
        };
    }
    </script>
</x-admin-layout>
