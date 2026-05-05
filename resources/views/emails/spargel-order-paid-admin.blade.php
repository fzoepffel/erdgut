@extends('emails.layout')

@php
    use App\Services\SpargelOrderService;
    $deliveryDateLabel = $order->expected_delivery_date
        ? $order->expected_delivery_date->locale('en')->isoFormat('dddd, D MMMM YYYY')
        : null;
    $whiteG = (int) $order->quantity_white_grams;
    $greenG = (int) $order->quantity_green_grams;
    $unitEur = (float) $order->unit_price_eur;
    $deliveryFee = (float) $order->delivery_fee_eur;
    $total = (float) $order->total_amount_eur;
    $isDelivery = $order->fulfillment_mode === 'delivery';
    $pickup = ! $isDelivery && $order->pickup_location
        ? SpargelOrderService::pickupLocation($order->pickup_location)
        : null;
    $detailRowStyle = 'padding: 6px 0; border-bottom: 1px solid rgba(44, 36, 28, 0.08); font-size: 14px;';
    $detailLabelStyle = 'color: #6b5b4c; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; padding-right: 12px; vertical-align: top; white-space: nowrap;';
    $detailValueStyle = 'color: #2c241c; vertical-align: top;';
    $sectionTitleStyle = 'margin: 24px 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #21341d;';
    $eur = fn (float $v) => '€' . number_format($v, 2, '.', ',');
@endphp

@section('content')
    <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #c53a2b;">
        Spargel — admin notification
    </p>
    <h2 style="margin: 0 0 8px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        Order #{{ $order->id }} paid
    </h2>
    <p style="margin: 0 0 20px 0; font-size: 14px; color: #6b5b4c;">
        Paid on
        {{ $order->paid_at?->timezone(config('app.timezone'))->locale('en')->isoFormat('dddd, D MMMM YYYY [at] HH:mm') }}.
        Total: <strong>{{ $eur($total) }}</strong>
    </p>

    <h3 style="{{ $sectionTitleStyle }}">Customer</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Name</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->name }}</td></tr>
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Email</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}"><a href="mailto:{{ $order->email }}" style="color: #2c241c;">{{ $order->email }}</a></td></tr>
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Phone</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->phone ?: '—' }}</td></tr>
    </table>

    <h3 style="{{ $sectionTitleStyle }}">Order</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($whiteG > 0)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">White spargel</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    {{ number_format($whiteG, 0, '.', ',') }} g
                    <span style="color: #6b5b4c;">·
                        {{ $eur(($whiteG / 1000) * $unitEur) }}
                        ({{ $eur($unitEur) }}/kg)
                    </span>
                </td>
            </tr>
        @endif
        @if ($greenG > 0)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Green spargel</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    {{ number_format($greenG, 0, '.', ',') }} g
                    <span style="color: #6b5b4c;">·
                        {{ $eur(($greenG / 1000) * $unitEur) }}
                        ({{ $eur($unitEur) }}/kg)
                    </span>
                </td>
            </tr>
        @endif
        @if ($isDelivery && $deliveryFee > 0)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Delivery fee</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $eur($deliveryFee) }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 10px 0 0 0; font-size: 13px; font-weight: 600; color: #2c241c; padding-right: 12px; white-space: nowrap;">Total</td>
            <td style="padding: 10px 0 0 0; font-size: 16px; font-weight: 600; color: #2c241c;">{{ $eur($total) }}</td>
        </tr>
    </table>

    <h3 style="{{ $sectionTitleStyle }}">{{ $isDelivery ? 'Delivery' : 'Pickup' }}</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Mode</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $isDelivery ? 'Delivery' : 'Self-pickup' }}</td></tr>
        @if ($deliveryDateLabel)
            <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">{{ $isDelivery ? 'Delivery date' : 'Pickup date' }}</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $deliveryDateLabel }}</td></tr>
        @endif
        @if ($isDelivery)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Address</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    {{ $order->delivery_street }}<br>
                    {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                </td>
            </tr>
            @if ($order->delivery_notes)
                <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Note</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->delivery_notes }}</td></tr>
            @endif
        @elseif ($pickup)
            <tr>
                <td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Pickup point</td>
                <td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">
                    <strong>{{ $pickup['name'] }}</strong><br>
                    {{ $pickup['street'] }}<br>
                    {{ $pickup['postal_code'] }} {{ $pickup['city'] }}
                </td>
            </tr>
        @endif
    </table>

    <h3 style="{{ $sectionTitleStyle }}">Payment</h3>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Method</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->payment_method === 'in_person' ? 'In person (cash/card)' : 'SumUp (online)' }}</td></tr>
        <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Status</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</td></tr>
        @if ($order->paid_at)
            <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Paid at</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->paid_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td></tr>
        @endif
        @if ($order->marked_paid_by_admin_at)
            <tr><td style="{{ $detailRowStyle }} {{ $detailLabelStyle }}">Marked paid manually</td><td style="{{ $detailRowStyle }} {{ $detailValueStyle }}">{{ $order->marked_paid_by_admin_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td></tr>
        @endif
    </table>

    <p style="margin: 32px 0 0 0;">
        <a href="{{ route('spargel.admin.index', ['search' => $order->id]) }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">
            Open in admin
        </a>
    </p>

    <p style="margin: 24px 0 0 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 13px; color: #6b5b4c;">
        This notification is sent to every admin account when a payment is confirmed by SumUp
        (or the order is manually marked as paid).
    </p>
@endsection
