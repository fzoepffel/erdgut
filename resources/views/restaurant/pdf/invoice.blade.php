{{--
    Rechnung (B2B) — German tax document, always rendered in German.
    Layout mirrors the manually issued Erdgut invoices (big RECHNUNG title,
    logo + meta block, Von/Empfänger columns, NET items table with an
    optional delivery line, bank-transfer block, highlighted grand total).
    Inline CSS only; dompdf does not support external stylesheets or Tailwind.

    Variables:
      $order         RestaurantOrder (items eager-loaded; FROZEN once invoiced)
      $profile       RestaurantProfile|null (buyer billing details)
      $vatByRate     Collection keyed by int rate → float vat amount (incl. delivery fee)
      $producerNames array "productId|variantId" → farm names (unused in this layout)
      $seller        array { name, street, postal_city, phone, email, vat_id, tax_number, iban, bank, register }
--}}
@php
    use App\Enums\ProductUnit;

    $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    $num = function (float $v): string {
        $s = number_format($v, 3, ',', '.');
        $s = rtrim(rtrim($s, '0'), ',');

        return $s === '' ? '0' : $s;
    };

    // Net unit price per DISPLAY unit (35,00 €/kg style) + quantity in
    // display units, derived from the net line total so the row always
    // multiplies out exactly.
    $lineView = function ($item) use ($num) {
        $qty = (float) $item->quantity;
        $product = $item->product;

        // The document is always German; unit words must not follow the
        // admin's session locale (displaySymbol translates piece/bunch).
        $germanSymbol = fn (string $u) => match ($u) {
            'piece' => 'Stück',
            'bunch' => 'Bund',
            default => ProductUnit::displaySymbol($u),
        };

        if ($product?->unit !== null) {
            $display = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
            $factor = max(1, $product->unit->canonicalUnitsPerDisplay($display));
            $displayQty = $qty / $factor;
            $unitLabel = $germanSymbol($display);
        } else {
            // Custom line: the admin entered the unit. Scale g/ml up to kg/L
            // so the unit price reads like a shelf price, not a per-gram one.
            $customUnit = (string) ($item->unit ?? '');
            $customFactor = in_array($customUnit, ['g', 'ml'], true) ? 1000 : 1;
            $displayQty = $qty / $customFactor;
            $unitLabel = match ($customUnit) {
                'g' => 'kg',
                'ml' => 'L',
                '' => '',
                default => $germanSymbol($customUnit),
            };
        }

        return [
            'unit_net' => $displayQty > 0 ? $item->lineNetEur() / $displayQty : 0.0,
            'qty' => trim($num($displayQty).' '.$unitLabel),
        ];
    };

    $logoPath = public_path('images/logo/erdgut-invoice.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;

    $deliveryAddress = trim($order->delivery_street.', '.$order->delivery_postal_code.' '.$order->delivery_city, ', ');
    $dueDays = (int) config('shop.restaurant_invoice_due_days', 7);
    $fmt = fn ($d) => $d !== null ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';

    // Per-invoice display overrides from the WYSIWYG editor; blank/unset
    // falls back to the real default (config / restaurant profile).
    $ov = fn (string $key, ?string $default = null) => $order->invoiceOverride($key) ?? $default;

    // Draft preview: no number assigned yet — render with the number the
    // invoice WOULD get and today's dates, clearly marked as Entwurf.
    $isPreview = $order->invoice_number === null;
    $number = $order->invoice_number ?? ($previewNumber ?? '');
    $invoiceDate = $order->invoiced_at ?? now();
    $dueDate = $order->invoice_due_date
        ?? ($order->expected_delivery_date !== null ? \Illuminate\Support\Carbon::parse($order->expected_delivery_date)->addDays($dueDays) : now()->addDays($dueDays));
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rechnung {{ $order->invoice_number }} – Erdgut</title>
<style>
    /* No universal reset: a margin/padding reset that matches the fixed
       .page-footer div makes dompdf drop the footer entirely. */
    body, h1, h2, p, table, th, td { margin: 0; padding: 0; }
    @page { margin: 90px 48px 60px; }
    body { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; font-size: 10px; color: #1f1f1f; line-height: 1.5; }
    .clearfix::after { content: ''; display: table; clear: both; }

    h1.doc-title { font-size: 26px; font-weight: bold; letter-spacing: -0.5px; margin: 0 0 26px; }
    h1.doc-title .nr { font-weight: bold; }

    .id-block { margin-bottom: 26px; }
    .id-logo { float: left; width: 110px; }
    .id-logo img { width: 96px; height: auto; }
    .id-meta { float: left; padding-top: 14px; font-size: 10px; color: #333; }
    .id-meta div { margin-bottom: 3px; }
    .id-meta strong { color: #111; }

    .rule { border-top: 1px solid #e3e3e0; margin: 18px 0; }

    .parties { margin-bottom: 6px; }
    .party { float: left; width: 50%; }
    .party h2, .block h2 { font-size: 12px; font-weight: bold; margin-bottom: 6px; }
    .party div { font-size: 10px; color: #333; margin-bottom: 2px; }

    h2.items-heading { font-size: 12px; font-weight: bold; margin: 4px 0 10px; }
    table.items { width: 100%; border-collapse: collapse; border: 1px solid #e3e3e0; }
    table.items th { font-size: 8px; font-weight: normal; color: #8a8a86; border-bottom: 1px solid #e3e3e0; border-right: 1px solid #eeeeeb; padding: 3px 6px; text-align: left; background: #fbfbfa; }
    table.items td { font-size: 9px; padding: 3px 6px; border-bottom: 1px solid #f0f0ed; border-right: 1px solid #f5f5f2; vertical-align: top; }
    table.items th:last-child, table.items td:last-child { border-right: none; }
    table.items tr:last-child td { border-bottom: none; }

    .bottom { margin-top: 26px; }
    .pay-block { float: left; width: 46%; }
    .pay-block h2 { font-size: 11px; font-weight: bold; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 1px solid #e3e3e0; }
    .pay-block .method { font-weight: bold; margin-bottom: 3px; }
    .pay-block div { font-size: 9.5px; color: #333; margin-bottom: 2px; }

    .totals-block { float: right; width: 46%; }
    .totals-block .line { font-size: 10px; margin-bottom: 4px; }
    .totals-block .line strong { color: #111; }
    .grand-total { margin-top: 8px; background: #edf2ea; padding: 8px 12px; font-size: 22px; font-weight: bold; color: #111; }

    .foot-cols { margin-top: 30px; border-top: 1px solid #e3e3e0; padding-top: 14px; }
    .foot-col { float: left; width: 50%; }
    .foot-col h2 { font-size: 12px; font-weight: bold; margin-bottom: 5px; }
    .foot-col div, .foot-col p { font-size: 9.5px; color: #333; }

    /* Fixed page footer. dompdf quirks: floats collapse inside fixed
       elements and a CSS percentage width on the inner table resolves to
       zero, so the layout uses a table with the width ATTRIBUTE instead. */
    .page-footer { position: fixed; bottom: -40px; left: 0; right: 0; }
    .page-footer td { font-size: 8.5px; color: #b3b3b0; padding: 0; border: none; }
    .page-footer .pagenum:after { content: counter(page); }
</style>
</head>
<body>

<div class="page-footer">
    <table width="100%"><tr>
        <td>RECHNUNG: {{ $number }}</td>
        <td style="text-align:right;" class="pagenum"></td>
    </tr></table>
</div>

<h1 class="doc-title">RECHNUNG: <span class="nr">{{ $number }}</span></h1>

<div class="id-block clearfix">
    @if ($logoData !== null)
        <div class="id-logo"><img src="{{ $logoData }}" alt="Erdgut"></div>
    @endif
    <div class="id-meta">
        <div>Rechnung No: <strong>{{ $number }}</strong>@if ($isPreview) <span style="color:#b3b3b0;">(Entwurf)</span>@endif</div>
        <div>Rechnungsdatum: <strong>{{ $fmt($invoiceDate) }}</strong></div>
        <div>Fälligkeitsdatum: <strong>{{ $fmt($dueDate) }}</strong></div>
        <div>Lieferdatum: <strong>{{ $fmt($order->expected_delivery_date) }}</strong></div>
    </div>
</div>

<div class="rule"></div>

<div class="parties clearfix">
    <div class="party">
        <h2>Von</h2>
        @php
            $sellerName = $ov('seller_name', $seller['name']);
            $sellerRegister = $ov('seller_register', $seller['register']);
            $sellerVatId = $ov('seller_vat_id', $seller['vat_id'] ? 'Umsatzsteuer-ID: '.$seller['vat_id'] : null);
            $sellerAddress = $ov('seller_address', trim($seller['street'].', '.$seller['postal_city'], ', '));
            $sellerEmail = $ov('seller_email', $seller['email']);
        @endphp
        <div>{{ $sellerName }}</div>
        @if ($sellerRegister)<div>{{ $sellerRegister }}</div>@endif
        @if ($sellerVatId)<div>{{ $sellerVatId }}</div>@endif
        @if ($sellerAddress)<div>{{ $sellerAddress }}</div>@endif
        @if ($sellerEmail)<div>{{ $sellerEmail }}</div>@endif
    </div>
    <div class="party">
        <h2>Empfänger</h2>
        {{-- Billing address (§ 14 UStG); the delivery address appears on the
             Lieferung line and via the Lieferdatum. --}}
        @php
            $billingAddress = $profile !== null
                ? trim(($profile->billing_street ?? '').', '.trim(($profile->billing_postal_code ?? '').' '.($profile->billing_city ?? '')), ', ')
                : '';
            $recipientName = $ov('recipient_name', $profile?->business_name ?? $order->user?->name);
            $recipientExtra = $ov('recipient_extra', $profile?->contact_name ? 'z. Hd. '.$profile->contact_name : null);
            $recipientAddress = $ov('recipient_address', $billingAddress !== '' ? $billingAddress : $deliveryAddress);
            $recipientPhone = $ov('recipient_phone', $profile?->phone ?? $order->user?->phone);
            $recipientVatId = $ov('recipient_vat_id', $profile?->ust_id_nr ? 'USt-IdNr.: '.$profile->ust_id_nr : null);
        @endphp
        <div>{{ $recipientName }}</div>
        @if ($recipientExtra)<div>{{ $recipientExtra }}</div>@endif
        <div>{{ $recipientAddress }}</div>
        @if ($recipientPhone)<div>{{ $recipientPhone }}</div>@endif
        @if ($recipientVatId)<div>{{ $recipientVatId }}</div>@endif
    </div>
</div>

<div class="rule"></div>

<h2 class="items-heading">Produkte &amp; Preise</h2>
<table class="items">
    <thead>
        <tr>
            <th style="width:44%">Bezeichnung</th>
            <th style="width:20%">Preis (ohne MwSt.)</th>
            <th style="width:16%">Menge</th>
            <th style="width:20%">Gesamt (ohne MwSt.)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items->reject(fn ($i) => $i->isDeclined()) as $item)
            @php $view = $lineView($item); @endphp
            <tr>
                <td>{{ $item->invoiceDisplayName() }}</td>
                <td>{{ $eur($view['unit_net']) }}</td>
                <td>{{ $view['qty'] }}</td>
                <td>{{ $eur($item->lineNetEur()) }}</td>
            </tr>
        @endforeach
        @if ($order->deliveryFeeNetEur() > 0)
            <tr>
                <td>{{ $ov('delivery_line_label', 'Lieferung '.$deliveryAddress) }}</td>
                <td>{{ $eur($order->deliveryFeeNetEur()) }}</td>
                <td>1</td>
                <td>{{ $eur($order->deliveryFeeNetEur()) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="bottom clearfix">
    <div class="pay-block">
        <h2>Zahlungsmethoden</h2>
        @php
            $bankName = $ov('bank_name', $seller['bank']);
            $bankHolder = $ov('bank_holder', $seller['name']);
            $bankIban = $ov('bank_iban', $seller['iban']);
            $bankReference = $ov('bank_reference', $number);
        @endphp
        <div class="method">Banküberweisung</div>
        @if ($bankName)<div>Bankname: {{ $bankName }}</div>@endif
        <div>Kontoinhaber: {{ $bankHolder }}</div>
        @if ($bankIban)
            <div>IBAN:</div>
            <div>{{ $bankIban }}</div>
        @endif
        <div>Verwendungszweck: {{ $bankReference }}</div>
    </div>
    <div class="totals-block">
        <div class="line">Gesamtbetrag Netto: <strong>{{ $eur($order->items_total_net_eur) }}</strong></div>
        @foreach ($vatByRate as $rate => $amount)
            <div class="line">Umsatzsteuer ({{ $rate }}%): <strong>{{ $eur($amount) }}</strong></div>
        @endforeach
        <div class="line" style="margin-top:6px;"><strong>Rechnungsbetrag:</strong></div>
        <div class="grand-total">{{ $eur($order->items_total_gross_eur) }}</div>
    </div>
</div>

@php
    $questionsDefault = trim(($seller['email'] ? 'Email '.$seller['email'] : '')
        .($seller['phone'] ? "\noder anrufen unter ".$seller['phone'] : ''));
    $termsDefault = 'Der Rechnungsbetrag ist innerhalb von '.$dueDays.' Tagen nach Lieferung zahlbar. Leistungsdatum entspricht dem Lieferdatum.';
@endphp
<div class="foot-cols clearfix">
    <div class="foot-col">
        <h2>Fragen?</h2>
        <p>{!! nl2br(e($ov('questions_text', $questionsDefault))) !!}</p>
    </div>
    <div class="foot-col">
        <h2>Zahlungsbedingungen</h2>
        <p>{!! nl2br(e($ov('terms_text', $termsDefault))) !!}</p>
        @if ($seller['tax_number'])<p style="margin-top:4px; color:#8a8a86;">Steuernr.: {{ $seller['tax_number'] }}</p>@endif
    </div>
</div>

</body>
</html>
