@extends('emails.layout')

@php
    // Shop orders are confirmed instantly; requests are received + reviewed.
    $confirmed = ! $order->isRequest();
    $headingKey = $confirmed ? 'restaurant.order_email.confirmed_heading' : 'restaurant.order_email.submitted_heading';
    $bodyKey = $confirmed ? 'restaurant.order_email.confirmed_body' : 'restaurant.order_email.submitted_body';
@endphp
@section('content')
    <h2 style="margin: 0 0 12px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        {{ __($headingKey) }}
    </h2>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #2c241c;">
        {{ __($bodyKey, ['date' => $order->expected_delivery_date->translatedFormat('d.m.Y')]) }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 8px 0 16px 0; border-collapse: collapse; font-size: 14px; color: #2c241c;">
        @foreach ($order->items as $item)
            <tr>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee;">
                    {{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}
                    @if ($item->isReserved())
                        <span style="color: #2f7a3f;">({{ __('restaurant.in_stock') }})</span>
                    @else
                        <span style="color: #9a6a12;">({{ __('restaurant.on_request') }})</span>
                    @endif
                </td>
                <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right;">
                    @if ($item->isCustom()){{ __('restaurant.price_on_request') }}@else{{ \App\Support\Money::formatEur($item->lineNetEur(), $order->locale) }}@endif
                </td>
            </tr>
        @endforeach
        <tr>
            <td style="padding: 8px 0; font-weight: 600;">{{ __('restaurant.order_net_total') }}</td>
            <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ \App\Support\Money::formatEur((float) $order->items_total_net_eur, $order->locale) }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 16px 0; font-size: 13px; color: #6b5b4c;">{{ __('restaurant.order_email.submitted_followup') }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 8px 0;">
        <tr>
            <td style="background-color: #21341d; border-radius: 4px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; color: #faf5ec; font-size: 14px; font-weight: 600; text-decoration: none;">
                    {{ __('restaurant.order_email.submitted_cta') }} &rarr;
                </a>
            </td>
        </tr>
    </table>
@endsection
