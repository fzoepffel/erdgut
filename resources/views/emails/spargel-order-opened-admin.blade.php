@extends('emails.layout')

@php
    use App\Services\SpargelOrderService;
    $deliveryDateLabel = $order->expected_delivery_date
        ? $order->expected_delivery_date->locale('de')->isoFormat('dddd, D. MMMM YYYY')
        : null;
    $whiteG = (int) $order->quantity_white_grams;
    $greenG = (int) $order->quantity_green_grams;
    $unitEur = (float) $order->unit_price_eur;
    $deliveryFee = (float) $order->delivery_fee_eur;
    $total = (float) $order->total_amount_eur;
    $isDelivery = $order->fulfillment_mode === 'delivery';
    $isInPerson = $order->payment_method === 'in_person';
    $pickup = ! $isDelivery && $order->pickup_location
        ? SpargelOrderService::pickupLocation($order->pickup_location)
        : null;
    $detailRowStyle = 'padding: 6px 0; border-bottom: 1px solid rgba(44, 36, 28, 0.08); font-size: 14px;';
    $detailLabelStyle = 'color: #6b5b4c; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; padding-right: 12px; vertical-align: top; white-space: nowrap;';
    $detailValueStyle = 'color: #2c241c; vertical-align: top;';
    $sectionTitleStyle = 'margin: 24px 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #21341d;';
@endphp

@section('content')
    <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #e46f25;">
        Spargel — Admin-Benachrichtigung
    </p>
    <h2 style="margin: 0 0 8px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        Neue Bestellung #{{ $order->id }} eingegangen
    </h2>
    <p style="margin: 0 0 12px 0; font-size: 14px; color: #6b5b4c;">
        Eingegangen am
        {{ $order->created_at?->timezone(config('app.timezone'))->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm') }} Uhr.
        Gesamtbetrag: <strong>{{ number_format($total, 2, ',', '.') }} €</strong>
    </p>
    <p style="margin: 0 0 20px 0; padding: 12px 16px; background-color: rgba(228, 111, 37, 0.08); border-left: 3px solid #e46f25; font-size: 13px; line-height: 1.5; color: #2c241c;">
        @if ($isInPerson)
            <strong>Zahlungsmethode: Vor Ort.</strong>
            Der/die Kund:in zahlt bei der {{ $isDelivery ? 'Lieferung' : 'Abholung' }}.
            Diese Bestellung erscheint <strong>nicht</strong> als „bezahlt“ im System, bis sie manuell markiert wird.
        @else
            <strong>Zahlungsmethode: SumUp (online).</strong>
            Der/die Kund:in wurde zum Kartenformular weitergeleitet.
            Bei erfolgreicher Zahlung folgt eine zweite Benachrichtigung. Bleibt diese aus, ist die Bestellung
            wahrscheinlich im Bezahlfluss hängengeblieben — siehe Widget-Telemetrie im Admin.
        @endif
    </p>

    <h3 style="{{ $sectionTitleStyle }}">Kund:in</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Name</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->name }}</td></tr>
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">E-Mail</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}"><a href="mailto:{{ $order->email }}" style="color: #2c241c;">{{ $order->email }}</a></td></tr>
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Telefon</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->phone ?: '—' }}</td></tr>
    </table>

    <h3 style="{{ $sectionTitleStyle }}">Bestellung</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($whiteG > 0)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Weißer Spargel</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    {{ number_format($whiteG, 0, ',', '.') }} g
                    <span style="color: #6b5b4c;">·
                        {{ number_format(($whiteG / 1000) * $unitEur, 2, ',', '.') }} €
                        ({{ number_format($unitEur, 2, ',', '.') }} €/kg)
                    </span>
                </td>
            </tr>
        @endif
        @if ($greenG > 0)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Grüner Spargel</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    {{ number_format($greenG, 0, ',', '.') }} g
                    <span style="color: #6b5b4c;">·
                        {{ number_format(($greenG / 1000) * $unitEur, 2, ',', '.') }} €
                        ({{ number_format($unitEur, 2, ',', '.') }} €/kg)
                    </span>
                </td>
            </tr>
        @endif
        @if ($isDelivery && $deliveryFee > 0)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Lieferpauschale</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ number_format($deliveryFee, 2, ',', '.') }} €</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 10px 0 0 0; font-size: 13px; font-weight: 600; color: #2c241c; padding-right: 12px; white-space: nowrap;">Gesamt</td>
            <td style="padding: 10px 0 0 0; font-size: 16px; font-weight: 600; color: #2c241c;">{{ number_format($total, 2, ',', '.') }} €</td>
        </tr>
    </table>

    <h3 style="{{ $sectionTitleStyle }}">{{ $isDelivery ? 'Lieferung' : 'Abholung' }}</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Modus</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $isDelivery ? 'Lieferung' : 'Selbstabholung' }}</td></tr>
        @if ($deliveryDateLabel)
            <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">{{ $isDelivery ? 'Liefertermin' : 'Abholtermin' }}</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $deliveryDateLabel }}</td></tr>
        @endif
        @if ($isDelivery)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Adresse</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    {{ $order->delivery_street }}<br>
                    {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                </td>
            </tr>
            @if ($order->delivery_notes)
                <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Hinweis</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->delivery_notes }}</td></tr>
            @endif
        @elseif ($pickup)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Abholort</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    <strong>{{ $pickup['name'] }}</strong><br>
                    {{ $pickup['street'] }}<br>
                    {{ $pickup['postal_code'] }} {{ $pickup['city'] }}
                </td>
            </tr>
        @endif
    </table>

    <h3 style="{{ $sectionTitleStyle }}">Status</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Methode</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $isInPerson ? 'Vor Ort (bar/Karte)' : 'SumUp (online)' }}</td></tr>
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Status</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ ucfirst($order->status->value) }}</td></tr>
    </table>

    <p style="margin: 32px 0 0 0;">
        <a href="{{ route('spargel.admin.index', ['search' => $order->id]) }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">
            Im Admin öffnen
        </a>
    </p>

    <p style="margin: 24px 0 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 13px; color: #6b5b4c;">
        Diese Benachrichtigung geht an alle hinterlegten Admin-Konten und wird ausgelöst, sobald eine neue
        Spargel-Bestellung im System angelegt wurde. Eine zweite Benachrichtigung folgt, wenn die Zahlung
        bestätigt ist.
    </p>
@endsection
