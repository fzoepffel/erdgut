{{--
    Kaufbeleg / Kleinbetragsrechnung (§ 33 UStDV)
    Always rendered in German — this is a German tax document.
    Same visual language as the B2B Rechnung (restaurant/pdf/invoice.blade.php),
    adapted for private customers: GROSS prices, order number instead of a
    sequential invoice number, payment block instead of bank details.
    Inline CSS only; dompdf does not support external stylesheets or Tailwind.

    Variables:
      $order         ShopOrder  (items.product, items.product.producer eager-loaded)
      $vatByRate     Collection keyed by int rate → float vat amount
      $producerNames array "productId|variantId" → farm names
      $company       array { name, street, postal_city, phone, email, vat_id, register? }
--}}
@php
    use App\Enums\ProductUnit;

    $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    $num = function (float $v): string {
        $s = number_format($v, 3, ',', '.');
        $s = rtrim(rtrim($s, '0'), ',');

        return $s === '' ? '0' : $s;
    };

    // Gross unit price per DISPLAY unit + quantity in display units, derived
    // from the stored gross line total so each row multiplies out exactly.
    $lineView = function ($item) use ($num) {
        $qty = (float) $item->quantity;
        $product = $item->product;

        // The document is always German; unit words must not follow the
        // downloader's session locale (displaySymbol translates piece/bunch).
        $germanSymbol = fn (string $u) => match ($u) {
            'piece' => 'Stück',
            'bunch' => 'Bund',
            default => ProductUnit::displaySymbol($u),
        };

        if ($product?->unit !== null) {
            $display = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
            $factor = max(1, $product->unit->canonicalUnitsPerDisplay($display));
            $displayQty = $qty / $factor;
            $unitLabel = $product->countUnitWord((int) round($displayQty), 'de') ?? $germanSymbol($display);
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
            'unit_gross' => $displayQty > 0 ? (float) $item->line_total_eur / $displayQty : 0.0,
            'qty' => trim($num($displayQty).' '.$unitLabel),
        ];
    };

    $logoPath = public_path('images/logo/erdgut-invoice.png');
    $logoData = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;

    if ($order->fulfillment_mode === 'delivery') {
        $fulfillmentLabel = 'Lieferung';
        $fulfillmentAddress = trim($order->delivery_street.', '.$order->delivery_postal_code.' '.$order->delivery_city, ', ');
    } else {
        $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location);
        $fulfillmentLabel = 'Abholung';
        $fulfillmentAddress = $pickup !== null
            ? trim(($pickup['street'] ?? '').', '.trim(($pickup['postal_code'] ?? '').' '.($pickup['city'] ?? '')), ', ')
            : (string) $order->pickup_location;
    }

    // Per-receipt display overrides from the WYSIWYG editor; blank/unset
    // falls back to the real default.
    $ov = fn (string $key, ?string $default = null) => $order->receiptOverride($key) ?? ($default !== null && trim($default) !== '' ? $default : null);

    $statusLabel = match ($order->status->value ?? '') {
        'paid' => 'Bezahlt',
        'pending_payment' => 'Ausstehend',
        'fulfilled' => 'Abgeschlossen',
        'failed' => 'Fehlgeschlagen',
        default => ucfirst($order->status->value ?? ''),
    };

    $fmt = fn ($d) => $d !== null ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Kaufbeleg – Erdgut Bestellung #{{ $order->id }}</title>
<style>
    /* No universal reset: a margin/padding reset that matches the fixed
       .page-footer div makes dompdf drop the footer entirely. */
    body, h1, h2, p, table, th, td { margin: 0; padding: 0; }
    @page { margin: 90px 48px 60px; }
    body { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; font-size: 10px; color: #1f1f1f; line-height: 1.5; }
    .clearfix::after { content: ''; display: table; clear: both; }

    h1.doc-title { font-size: 26px; font-weight: bold; letter-spacing: -0.5px; margin: 0 0 26px; }

    .id-block { margin-bottom: 26px; }
    .id-logo { float: left; width: 110px; }
    .id-logo img { width: 96px; height: auto; }
    .id-meta { float: left; padding-top: 14px; font-size: 10px; color: #333; }
    .id-meta div { margin-bottom: 3px; }
    .id-meta strong { color: #111; }

    .rule { border-top: 1px solid #e3e3e0; margin: 18px 0; }

    .parties { margin-bottom: 6px; }
    .party { float: left; width: 50%; }
    .party h2 { font-size: 12px; font-weight: bold; margin-bottom: 6px; }
    .party div { font-size: 10px; color: #333; margin-bottom: 2px; }

    h2.items-heading { font-size: 12px; font-weight: bold; margin: 4px 0 10px; }
    table.items { width: 100%; border-collapse: collapse; border: 1px solid #e3e3e0; }
    table.items th { font-size: 8.5px; font-weight: normal; color: #8a8a86; border-bottom: 1px solid #e3e3e0; border-right: 1px solid #eeeeeb; padding: 5px 8px; text-align: left; background: #fbfbfa; }
    table.items td { font-size: 9.5px; padding: 6px 8px; border-bottom: 1px solid #f0f0ed; border-right: 1px solid #f5f5f2; vertical-align: top; }
    table.items th:last-child, table.items td:last-child { border-right: none; }
    table.items tr:last-child td { border-bottom: none; }

    .bottom { margin-top: 26px; }
    .pay-block { float: left; width: 46%; }
    .pay-block h2 { font-size: 11px; font-weight: bold; margin-bottom: 6px; padding-bottom: 5px; border-bottom: 1px solid #e3e3e0; }
    .pay-block div { font-size: 9.5px; color: #333; margin-bottom: 2px; }
    .pay-block .method { font-weight: bold; margin-bottom: 3px; }

    .totals-block { float: right; width: 46%; }
    .totals-block .line { font-size: 10px; margin-bottom: 4px; }
    .totals-block .line strong { color: #111; }
    .totals-block .muted { color: #8a8a86; font-size: 9px; }
    .grand-total { margin-top: 8px; background: #edf2ea; padding: 8px 12px; font-size: 22px; font-weight: bold; color: #111; }

    .foot-cols { margin-top: 30px; border-top: 1px solid #e3e3e0; padding-top: 14px; }
    .foot-col { float: left; width: 50%; }
    .foot-col h2 { font-size: 12px; font-weight: bold; margin-bottom: 5px; }
    .foot-col div, .foot-col p { font-size: 9.5px; color: #333; }
    .legal-note { margin-top: 14px; font-size: 8px; color: #aaa; }

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
        <td>BELEG: #{{ $order->id }}</td>
        <td style="text-align:right;" class="pagenum"></td>
    </tr></table>
</div>

<h1 class="doc-title">BELEG: #{{ $order->id }}</h1>

<div class="id-block clearfix">
    @if ($logoData !== null)
        <div class="id-logo"><img src="{{ $logoData }}" alt="Erdgut"></div>
    @endif
    <div class="id-meta">
        <div>Bestellnummer: <strong>#{{ $order->id }}</strong></div>
        <div>Belegdatum: <strong>{{ $fmt($order->created_at) }}</strong></div>
        @if ($order->expected_delivery_date)
            <div>{{ $fulfillmentLabel === 'Abholung' ? 'Abholdatum' : 'Lieferdatum' }}: <strong>{{ $fmt($order->expected_delivery_date) }}</strong></div>
        @endif
        <div>Status: <strong>{{ $statusLabel }}</strong></div>
    </div>
</div>

<div class="rule"></div>

<div class="parties clearfix">
    <div class="party">
        <h2>Von</h2>
        @php
            $sellerName = $ov('seller_name', $company['name']);
            $sellerRegister = $ov('seller_register', $company['register'] ?? '');
            $sellerVatId = $ov('seller_vat_id', $company['vat_id'] ? 'Umsatzsteuer-ID: '.$company['vat_id'] : null);
            $sellerAddress = $ov('seller_address', trim($company['street'].', '.$company['postal_city'], ', '));
            $sellerEmail = $ov('seller_email', $company['email']);
        @endphp
        <div>{{ $sellerName }}</div>
        @if ($sellerRegister)<div>{{ $sellerRegister }}</div>@endif
        @if ($sellerVatId)<div>{{ $sellerVatId }}</div>@endif
        @if ($sellerAddress)<div>{{ $sellerAddress }}</div>@endif
        @if ($sellerEmail)<div>{{ $sellerEmail }}</div>@endif
    </div>
    <div class="party">
        <h2>Empfänger</h2>
        @php
            $recipientName = $ov('recipient_name', $order->name);
            $recipientEmail = $ov('recipient_email', $order->email);
            $recipientPhone = $ov('recipient_phone', $order->phone);
            $recipientFulfillment = $ov('recipient_fulfillment', $fulfillmentLabel.': '.$fulfillmentAddress);
        @endphp
        <div>{{ $recipientName }}</div>
        @if ($recipientEmail)<div>{{ $recipientEmail }}</div>@endif
        @if ($recipientPhone)<div>{{ $recipientPhone }}</div>@endif
        <div>{{ $recipientFulfillment }}</div>
    </div>
</div>

<div class="rule"></div>

<h2 class="items-heading">Produkte &amp; Preise</h2>
<table class="items">
    <thead>
        <tr>
            <th style="width:44%">Bezeichnung</th>
            <th style="width:20%">Preis (inkl. MwSt.)</th>
            <th style="width:16%">Menge</th>
            <th style="width:20%">Gesamt (inkl. MwSt.)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            @php
                $view = $lineView($item);
                $farm = ($producerNames ?? [])[$item->product_id.'|'.(int) ($item->variant_id ?? 0)]
                    ?? $item->product?->producer?->name;
            @endphp
            <tr>
                <td>
                    {{ $item->receiptDisplayName() }}
                    @if ($farm)<div style="font-size:8px; color:#9a9a96;">{{ $farm }}</div>@endif
                </td>
                <td>{{ $eur($view['unit_gross']) }}</td>
                <td>{{ $view['qty'] }}</td>
                <td>{{ $eur($item->line_total_eur) }}</td>
            </tr>
        @endforeach
        @if ((float) $order->discount_eur > 0)
            <tr>
                <td>Rabatt{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</td>
                <td></td>
                <td></td>
                <td>&minus; {{ $eur($order->discount_eur) }}</td>
            </tr>
        @endif
        @if ((float) $order->delivery_fee_eur > 0)
            <tr>
                <td>{{ $ov('delivery_line_label', 'Lieferung '.$fulfillmentAddress) }}</td>
                <td>{{ $eur($order->delivery_fee_eur) }}</td>
                <td>1</td>
                <td>{{ $eur($order->delivery_fee_eur) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="bottom clearfix">
    <div class="pay-block">
        <h2>Zahlung</h2>
        @php
            $paymentDefault = match (true) {
                $order->payment_method === 'in_person' => 'Barzahlung / Kartenzahlung vor Ort',
                $order->paid_at !== null && $order->outstandingAmountEur() >= 0.01 => 'Online-Zahlung, Restbetrag vor Ort',
                $order->paid_at !== null && $order->outstandingAmountEur() <= -0.01 => 'Online-Zahlung, Erstattung vor Ort',
                default => 'Online-Zahlung',
            };
        @endphp
        <div class="method">{{ $ov('payment_text', $paymentDefault) }}</div>
        @if ($order->paid_at !== null)
            <div>Bezahlt am {{ $fmt($order->paid_at) }}</div>
        @endif
    </div>
    <div class="totals-block">
        @foreach ($vatByRate as $rate => $amount)
            <div class="line muted">davon {{ $rate }} % MwSt.: {{ $eur($amount) }}</div>
        @endforeach
        {{-- Admin edit changed the total after an online payment: show what
             was already collected and what is still open (or to refund). --}}
        @if ($order->paid_at !== null && abs($order->outstandingAmountEur()) >= 0.01)
            <div class="line">Bereits bezahlt (Online-Zahlung): <strong>{{ $eur($order->effectivePaidAmountEur()) }}</strong></div>
            @if ($order->outstandingAmountEur() > 0)
                <div class="line">Offener Betrag (Zahlung vor Ort): <strong>{{ $eur($order->outstandingAmountEur()) }}</strong></div>
            @else
                <div class="line">Zu erstatten: <strong>{{ $eur(abs($order->outstandingAmountEur())) }}</strong></div>
            @endif
        @endif
        <div class="line" style="margin-top:6px;"><strong>Gesamtbetrag:</strong></div>
        <div class="grand-total">{{ $eur($order->total_amount_eur) }}</div>
    </div>
</div>

<div class="foot-cols clearfix">
    <div class="foot-col">
        <h2>Fragen?</h2>
        @php
            $questionsDefault = trim(($company['email'] ? 'Email '.$company['email'] : '')
                .($company['phone'] ? "\noder anrufen unter ".$company['phone'] : ''));
            $notesDefault = 'Alle Preise sind Bruttopreise und enthalten die gesetzliche Mehrwertsteuer.'
                .($order->total_amount_eur <= 250 ? ' Dieser Beleg gilt als Kleinbetragsrechnung gem. § 33 UStDV.' : '');
        @endphp
        <p>{!! nl2br(e($ov('questions_text', $questionsDefault))) !!}</p>
    </div>
    <div class="foot-col">
        <h2>Hinweise</h2>
        <p>{!! nl2br(e($ov('notes_text', $notesDefault))) !!}</p>
    </div>
</div>

</body>
</html>
