@extends('emails.layout')

@section('content')
    <h2 style="margin: 0 0 12px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        {{ __('restaurant.order_email.confirmed_heading') }}
    </h2>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #2c241c;">
        {{ __('restaurant.order_email.confirmed_body', ['date' => $order->expected_delivery_date->translatedFormat('d.m.Y')]) }}
    </p>

    @if ($note)
        <p style="margin: 0 0 16px 0; padding: 12px; background: #f6f2ea; font-size: 14px; color: #2c241c;">{{ $note }}</p>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 8px 0 16px 0; border-collapse: collapse; font-size: 14px; color: #2c241c;">
        @foreach ($order->items as $item)
            <tr>
                @if ($item->isDeclined())
                    {{-- Declined line: named explicitly so a partial decision
                         ("2 of 3 accepted") is unambiguous at a glance. --}}
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee; color: #8a8378; text-decoration: line-through;">
                        {{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}
                    </td>
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right; color: #b0483a; text-decoration: none;">
                        {{ __('restaurant.line_declined') }}
                    </td>
                @else
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee;">
                        {{ $item->displayQuantityLabel($order->locale) }} · {{ $item->displayName() }}
                        <span style="color: #2f7a3f;">({{ __('restaurant.line_accepted') }})</span>
                    </td>
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right;">
                        @if ($item->isCustom() && $item->lineNetEur() <= 0){{ __('restaurant.price_on_request') }}@else{{ \App\Support\Money::formatEur($item->lineNetEur(), $order->locale) }}@endif
                    </td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td style="padding: 8px 0; font-weight: 600;">{{ __('restaurant.order_net_total') }}</td>
            <td style="padding: 8px 0; font-weight: 600; text-align: right;">{{ \App\Support\Money::formatEur((float) $order->items_total_net_eur, $order->locale) }}</td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 8px 0;">
        <tr>
            <td style="background-color: #21341d; border-radius: 4px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; color: #faf5ec; font-size: 14px; font-weight: 600; text-decoration: none;">
                    {{ __('restaurant.order_email.confirmed_cta') }} &rarr;
                </a>
            </td>
        </tr>
    </table>
@endsection
