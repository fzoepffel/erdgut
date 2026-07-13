{{--
    Rechnung (B2B) — German tax document, always rendered in German.
    Inline CSS only; dompdf does not support external stylesheets or Tailwind.

    Variables:
      $order     RestaurantOrder (items, items.product.producer eager-loaded; FROZEN)
      $profile   RestaurantProfile|null (buyer billing details)
      $vatByRate Collection keyed by int rate → float vat amount
      $seller    array { name, street, postal_city, phone, email, vat_id, tax_number, iban, bank, register }
--}}
@php
    $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rechnung {{ $order->invoice_number }} – Erdgut</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.45; padding: 36px 40px; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .header { width: 100%; border-bottom: 1.5px solid #c1b99a; padding-bottom: 14px; margin-bottom: 18px; }
    .header-left { float: left; width: 58%; }
    .header-right { float: right; width: 40%; text-align: right; }
    .brand { font-size: 22px; font-weight: bold; letter-spacing: -0.5px; color: #709452; }
    .brand-sub { font-size: 9px; color: #888; letter-spacing: 0.08em; text-transform: uppercase; margin-top: 1px; }
    .company-addr { font-size: 9px; color: #555; margin-top: 6px; }
    .doc-title { font-size: 16px; font-weight: bold; }
    .doc-meta { font-size: 9px; color: #555; margin-top: 4px; }
    .section-label { font-size: 8px; font-weight: bold; letter-spacing: 0.1em; text-transform: uppercase; color: #888; margin-bottom: 4px; }
    .party-name { font-size: 11px; font-weight: bold; }
    .party-detail { font-size: 9px; color: #444; margin-top: 1px; }
    table.items { width: 100%; border-collapse: collapse; margin: 16px 0 14px; }
    table.items th { font-size: 8px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; color: #888; border-bottom: 1px solid #ddd; padding: 4px 6px 4px 0; text-align: left; }
    table.items th.num, table.items td.num { text-align: right; }
    table.items td { font-size: 9.5px; padding: 5px 6px 5px 0; border-bottom: 1px solid #efefef; vertical-align: top; }
    .producer-name { font-size: 8px; color: #999; }
    table.totals { width: 100%; border-collapse: collapse; }
    table.totals td { font-size: 9.5px; padding: 3px 0; }
    table.totals td.label { color: #555; }
    table.totals td.amount { text-align: right; }
    .total-row td { font-size: 12px; font-weight: bold; padding-top: 6px; border-top: 1.5px solid #c1b99a; }
    .pay-note { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 9px; color: #444; }
    .footer { margin-top: 22px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8.5px; color: #888; }
    .footer-left { float: left; width: 60%; }
    .footer-right { float: right; width: 38%; text-align: right; }
</style>
</head>
<body>

<div class="header clearfix">
    <div class="header-left">
        <div class="brand">{{ $seller['name'] }}</div>
        <div class="brand-sub">Hofladen &amp; Lieferservice</div>
        @if ($seller['street'] || $seller['postal_city'])
            <div class="company-addr">
                @if ($seller['street']){{ $seller['street'] }} · @endif{{ $seller['postal_city'] }}
            </div>
        @endif
    </div>
    <div class="header-right">
        <div class="doc-title">Rechnung</div>
        <div class="doc-meta">
            Rechnungsnummer: {{ $order->invoice_number }}<br>
            Rechnungsdatum: {{ optional($order->invoiced_at)->locale('de')->isoFormat('D. MMMM YYYY') }}<br>
            Lieferdatum: {{ $order->expected_delivery_date->locale('de')->isoFormat('D. MMMM YYYY') }}<br>
            Fällig bis: {{ optional($order->invoice_due_date)->locale('de')->isoFormat('D. MMMM YYYY') }}
        </div>
    </div>
</div>

<div class="clearfix" style="margin-bottom: 8px;">
    <div style="float:left; width:48%;">
        <div class="section-label">Rechnungsempfänger</div>
        <div class="party-name">{{ $profile?->business_name ?? $order->user?->name }}</div>
        @if ($profile?->contact_name)<div class="party-detail">z. Hd. {{ $profile->contact_name }}</div>@endif
        @if ($profile)
            <div class="party-detail">{{ $profile->billing_street }}</div>
            <div class="party-detail">{{ $profile->billing_postal_code }} {{ $profile->billing_city }}</div>
            @if ($profile->ust_id_nr)<div class="party-detail">USt-IdNr.: {{ $profile->ust_id_nr }}</div>@endif
        @endif
    </div>
    <div style="float:right; width:48%; text-align:right;">
        <div class="section-label">Lieferadresse</div>
        <div class="party-detail">{{ $order->delivery_street }}</div>
        <div class="party-detail">{{ $order->delivery_postal_code }} {{ $order->delivery_city }}</div>
    </div>
</div>

<table class="items">
    <thead>
        <tr>
            <th style="width:46%">Bezeichnung</th>
            <th style="width:22%">Erzeuger</th>
            <th class="num" style="width:16%">Menge</th>
            <th class="num" style="width:8%">MwSt.</th>
            <th class="num" style="width:14%">Netto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->displayName() }}@if ($item->variant_name_snapshot) · {{ $item->variant_name_snapshot }}@endif</td>
                <td class="producer-name">{{ ($producerNames ?? [])[$item->product_id.'|'.(int) ($item->variant_id ?? 0)] ?? $item->product?->producer?->name ?? '—' }}</td>
                <td class="num">{{ $item->displayQuantityLabel('de') }}</td>
                <td class="num">{{ $item->vat_rate_snapshot_percent }} %</td>
                <td class="num">{{ $eur($item->lineNetEur()) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div style="float:right; width:52%;">
    <table class="totals">
        <tr>
            <td class="label">Nettosumme</td>
            <td class="amount">{{ $eur($order->items_total_net_eur) }}</td>
        </tr>
        @foreach ($vatByRate as $rate => $amount)
            <tr>
                <td class="label">zzgl. {{ $rate }} % MwSt.</td>
                <td class="amount">{{ $eur($amount) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td class="label">Gesamtbetrag</td>
            <td class="amount">{{ $eur($order->items_total_gross_eur) }}</td>
        </tr>
    </table>
</div>
<div style="clear:both;"></div>

@php
    $bankLine = '';
    if ($seller['iban']) {
        $bankLine = 'auf folgendes Konto: '.$seller['iban'].($seller['bank'] ? ' ('.$seller['bank'].')' : '');
    }
@endphp
<div class="pay-note">
    Zahlbar bis {{ optional($order->invoice_due_date)->locale('de')->isoFormat('D. MMMM YYYY') }} ohne Abzug {{ $bankLine }}.
    Bitte geben Sie die Rechnungsnummer {{ $order->invoice_number }} als Verwendungszweck an.<br>
    Leistungsdatum entspricht dem Lieferdatum.
</div>

<div class="footer clearfix">
    <div class="footer-left">
        <strong>{{ $seller['name'] }}</strong><br>
        @if ($seller['street']){{ $seller['street'] }}<br>@endif
        @if ($seller['postal_city']){{ $seller['postal_city'] }}<br>@endif
        @if ($seller['phone'])Tel.: {{ $seller['phone'] }}<br>@endif
        @if ($seller['email']){{ $seller['email'] }}@endif
    </div>
    <div class="footer-right">
        @if ($seller['vat_id'])USt-IdNr.: {{ $seller['vat_id'] }}<br>@endif
        @if ($seller['tax_number'])Steuernr.: {{ $seller['tax_number'] }}<br>@endif
        @if ($seller['register']){{ $seller['register'] }}<br>@endif
        @if ($seller['iban'])IBAN: {{ $seller['iban'] }}@endif
    </div>
</div>

</body>
</html>
