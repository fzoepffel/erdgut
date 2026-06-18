{{--
    Kaufbeleg / Kleinbetragsrechnung (§ 33 UStDV)
    Always rendered in German — this is a German tax document.
    Inline CSS only; dompdf does not support external stylesheets or Tailwind.

    Variables:
      $order    ShopOrder  (items.product, items.product.producer eager-loaded)
      $vatByRate Collection keyed by int rate → float vat amount
      $company  array { name, street, postal_city, phone, email, vat_id }
--}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Kaufbeleg – Erdgut Bestellung #{{ $order->id }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
        font-size: 10px;
        color: #1a1a1a;
        line-height: 1.45;
        padding: 36px 40px;
    }
    /* ── Header ─────────────────────────────────────────────── */
    .header {
        width: 100%;
        border-bottom: 1.5px solid #c1b99a;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .header-left { float: left; width: 60%; }
    .header-right { float: right; width: 38%; text-align: right; }
    .brand { font-size: 22px; font-weight: bold; letter-spacing: -0.5px; color: #709452; }
    .brand-sub { font-size: 9px; color: #888; letter-spacing: 0.08em; text-transform: uppercase; margin-top: 1px; }
    .company-addr { font-size: 9px; color: #555; margin-top: 6px; }
    .doc-title { font-size: 16px; font-weight: bold; color: #1a1a1a; }
    .doc-meta { font-size: 9px; color: #555; margin-top: 4px; }
    .clearfix::after { content: ''; display: table; clear: both; }

    /* ── Customer / fulfillment block ───────────────────────── */
    .section { margin-bottom: 16px; }
    .section-label {
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #888;
        margin-bottom: 4px;
    }
    .customer-name { font-size: 11px; font-weight: bold; }
    .customer-detail { font-size: 9px; color: #444; margin-top: 1px; }

    /* ── Items table ─────────────────────────────────────────── */
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    table.items th {
        font-size: 8px;
        font-weight: bold;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #888;
        border-bottom: 1px solid #ddd;
        padding: 4px 6px 4px 0;
        text-align: left;
    }
    table.items th.num { text-align: right; }
    table.items td {
        font-size: 9.5px;
        padding: 5px 6px 5px 0;
        border-bottom: 1px solid #efefef;
        vertical-align: top;
    }
    table.items td.num { text-align: right; }
    table.items td.rate {
        font-size: 8.5px;
        color: #888;
        text-align: right;
    }
    .producer-name { font-size: 8px; color: #999; }

    /* ── Totals ──────────────────────────────────────────────── */
    table.totals {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
    }
    table.totals td {
        font-size: 9.5px;
        padding: 3px 0;
    }
    table.totals td.label { color: #555; }
    table.totals td.amount { text-align: right; }
    .total-row td {
        font-size: 12px;
        font-weight: bold;
        padding-top: 6px;
        border-top: 1.5px solid #c1b99a;
    }
    .vat-line {
        font-size: 8.5px;
        color: #888;
        text-align: right;
        padding: 1px 0;
    }

    /* ── Footer ──────────────────────────────────────────────── */
    .footer {
        margin-top: 28px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
        font-size: 8.5px;
        color: #888;
    }
    .footer-cols { width: 100%; }
    .footer-left { float: left; width: 60%; }
    .footer-right { float: right; width: 38%; text-align: right; }
    .legal-note { margin-top: 8px; font-size: 8px; color: #aaa; }
</style>
</head>
<body>

{{-- ── Header ──────────────────────────────────────────────── --}}
<div class="header clearfix">
    <div class="header-left">
        <div class="brand">{{ $company['name'] }}</div>
        <div class="brand-sub">Hofladen &amp; Lieferservice</div>
        @if ($company['street'] || $company['postal_city'])
            <div class="company-addr">
                @if ($company['street']){{ $company['street'] }}<br>@endif
                @if ($company['postal_city']){{ $company['postal_city'] }}@endif
            </div>
        @endif
    </div>
    <div class="header-right">
        <div class="doc-title">Kaufbeleg</div>
        <div class="doc-meta">
            Bestellnummer: #{{ $order->id }}<br>
            Datum: {{ $order->created_at->locale('de')->isoFormat('D. MMMM YYYY') }}<br>
            @if ($order->expected_delivery_date)
                Lieferdatum: {{ $order->expected_delivery_date->locale('de')->isoFormat('D. MMMM YYYY') }}<br>
            @endif
            @php
                $statusLabel = match($order->status->value ?? '') {
                    'paid'    => 'Bezahlt',
                    'pending_payment' => 'Ausstehend',
                    'failed'  => 'Fehlgeschlagen',
                    default   => ucfirst($order->status->value ?? ''),
                };
            @endphp
            Status: {{ $statusLabel }}
        </div>
    </div>
</div>

{{-- ── Customer & fulfillment ───────────────────────────────── --}}
<div class="section clearfix">
    <div style="float:left; width:48%;">
        <div class="section-label">Käufer</div>
        <div class="customer-name">{{ $order->name }}</div>
        <div class="customer-detail">{{ $order->email }}</div>
        @if ($order->phone)
            <div class="customer-detail">{{ $order->phone }}</div>
        @endif
    </div>
    <div style="float:right; width:48%; text-align:right;">
        <div class="section-label">Abholung / Lieferung</div>
        @if ($order->fulfillment_mode === 'delivery')
            <div class="customer-detail">
                Lieferung<br>
                {{ $order->delivery_street }}<br>
                {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
            </div>
        @else
            @php
                $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location);
            @endphp
            <div class="customer-detail">
                Abholung<br>
                @if ($pickup)
                    {{ $pickup['street'] ?? '' }}<br>
                    {{ ($pickup['postal_code'] ?? '') . ' ' . ($pickup['city'] ?? '') }}
                @else
                    {{ $order->pickup_location }}
                @endif
            </div>
        @endif
    </div>
</div>

{{-- ── Line items ───────────────────────────────────────────── --}}
<table class="items">
    <thead>
        <tr>
            <th style="width:42%">Artikel</th>
            <th style="width:22%">Erzeuger</th>
            <th class="num" style="width:16%">Menge</th>
            <th class="num" style="width:10%">MwSt.</th>
            <th class="num" style="width:10%">Betrag</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            @php $product = $item->product; @endphp
            <tr>
                <td>{{ $product?->name ?? '—' }}@if ($item->variant_name_snapshot) · {{ $item->variant_name_snapshot }}@endif</td>
                <td class="producer-name">{{ $product?->producer?->name ?? '—' }}</td>
                <td class="num">{{ $product ? $product->displayQuantity((float) $item->quantity) : $item->quantity }}</td>
                <td class="rate">{{ $item->vat_rate_snapshot_percent }} %</td>
                <td class="num">{{ number_format((float) $item->line_total_eur, 2, ',', '.') }} €</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ── Totals ───────────────────────────────────────────────── --}}
<div style="float:right; width:52%;">
    <table class="totals">
        @if ((float) $order->delivery_fee_eur > 0 || (float) $order->discount_eur > 0)
            <tr>
                <td class="label">Zwischensumme</td>
                <td class="amount">{{ number_format((float) $order->items_total_eur, 2, ',', '.') }} €</td>
            </tr>
        @endif
        @if ((float) $order->discount_eur > 0)
            <tr>
                <td class="label">
                    Rabatt
                    @if ($order->coupon_code)
                        ({{ $order->coupon_code }})
                    @endif
                </td>
                <td class="amount">− {{ number_format((float) $order->discount_eur, 2, ',', '.') }} €</td>
            </tr>
        @endif
        @if ((float) $order->delivery_fee_eur > 0)
            <tr>
                <td class="label">Liefergebühr</td>
                <td class="amount">{{ number_format((float) $order->delivery_fee_eur, 2, ',', '.') }} €</td>
            </tr>
        @endif
        <tr class="total-row">
            <td class="label">Gesamtbetrag</td>
            <td class="amount">{{ number_format((float) $order->total_amount_eur, 2, ',', '.') }} €</td>
        </tr>
    </table>
    {{-- Per-rate VAT breakdown --}}
    @foreach ($vatByRate as $rate => $amount)
        <div class="vat-line">davon {{ $rate }} % MwSt.: {{ number_format($amount, 2, ',', '.') }} €</div>
    @endforeach
</div>
<div style="clear:both;"></div>

{{-- ── Footer ─────────────────────────────────────────────────  --}}
<div class="footer">
    <div class="footer-cols clearfix">
        <div class="footer-left">
            <strong>{{ $company['name'] }}</strong><br>
            @if ($company['street']){{ $company['street'] }}<br>@endif
            @if ($company['postal_city']){{ $company['postal_city'] }}<br>@endif
            @if ($company['phone'])Tel.: {{ $company['phone'] }}<br>@endif
            @if ($company['email']){{ $company['email'] }}<br>@endif
        </div>
        <div class="footer-right">
            @if ($company['vat_id'])
                Ust.-ID: {{ $company['vat_id'] }}<br>
            @endif
            Zahlung: {{ $order->payment_method === 'in_person' ? 'Barzahlung / Kartenzahlung vor Ort' : 'Online-Zahlung' }}
        </div>
    </div>
    <div class="legal-note">
        Alle Preise sind Bruttopreise und enthalten die gesetzliche Mehrwertsteuer.
        @if ($order->total_amount_eur <= 250)
            Dieser Beleg gilt als Kleinbetragsrechnung gem. § 33 UStDV.
        @endif
    </div>
</div>

</body>
</html>
