@extends('emails.layout')

@section('content')
    <h2 style="margin: 0 0 12px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        Neue Restaurant-Anfrage #{{ $order->id }}
    </h2>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #2c241c;">
        {{ $order->user?->name }} · Liefertermin {{ $order->expected_delivery_date->translatedFormat('d.m.Y') }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 8px 0 16px 0; border-collapse: collapse; font-size: 14px; color: #2c241c;">
        @foreach ($order->items as $item)
            <tr>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee;">
                    {{ $item->displayQuantityLabel('de') }} · {{ $item->displayName() }}
                    @if ($item->isReserved())
                        <span style="color: #2f7a3f;">(auf Lager / reserviert)</span>
                    @else
                        <span style="color: #9a6a12;">(auf Anfrage)</span>
                    @endif
                </td>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right;">
                    {{ \App\Support\Money::formatEur($item->lineNetEur(), 'de') }}
                </td>
            </tr>
        @endforeach
        <tr>
            <td style="padding: 8px 0; font-weight: 600;">Netto gesamt</td>
            <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ \App\Support\Money::formatEur((float) $order->items_total_net_eur, 'de') }}</td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13px; color: #6b5b4c;">
        Lieferadresse: {{ $order->delivery_street }}, {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
        @if ($order->delivery_notes)<br>Notiz: {{ $order->delivery_notes }}@endif
    </p>
@endsection
