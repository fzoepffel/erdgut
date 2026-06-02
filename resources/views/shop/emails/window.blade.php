<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->fulfillment_mode === 'delivery' ? __('shop.window_email_heading_delivery') : __('shop.window_email_heading_pickup') }}</title>
</head>
{{-- Standalone window-set email — inline styles only, matching the shop
     confirmation email so the anonymous channel stays self-contained. --}}
<body style="margin: 0; padding: 0; background-color: #f1e7d6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #2c241c;">
    @php
        $isDelivery = $order->fulfillment_mode === 'delivery';
        $deliveryDate = $order->expected_delivery_date
            ? $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY')
            : null;
        $windowLabel = $order->deliveryWindowLabel();
        $contactPhone = \App\Services\Shop\ShopOrderService::contactPhone($order);
        $contactPhoneHref = 'tel:'.preg_replace('/[^+0-9]/', '', $contactPhone);
        $contactEmail = __('shop.contact_email');
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f1e7d6;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="540" cellpadding="0" cellspacing="0" border="0" style="max-width: 540px; width: 100%; background-color: #ffffff; border: 1px solid rgba(44, 36, 28, 0.15);">
                    <tr>
                        <td style="background-color: #709452; padding: 20px 28px;">
                            <span style="display: inline-block; font-family: Georgia, 'Times New Roman', serif; font-size: 20px; font-weight: 700; letter-spacing: 0.04em; color: #21341d;">ERDGUT</span>
                            <span style="float: right; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(33, 52, 29, 0.85);">{{ __('shop.eyebrow') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 28px;">
                            <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">
                                {{ $isDelivery ? __('shop.return_paid_delivery_eyebrow') : __('shop.return_paid_pickup_eyebrow') }}
                            </p>
                            <h2 style="margin: 0 0 16px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 24px; font-weight: 600; color: #2c241c;">
                                {{ $isDelivery ? __('shop.window_email_heading_delivery') : __('shop.window_email_heading_pickup') }}
                            </h2>
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.55; color: rgba(44, 36, 28, 0.85);">
                                @if ($isDelivery)
                                    {{ __('shop.window_email_body_delivery', ['date' => $deliveryDate, 'window' => $windowLabel]) }}
                                @else
                                    {{ __('shop.window_email_body_pickup', ['date' => $deliveryDate, 'window' => $windowLabel]) }}
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px 0; background-color: #f1e7d6; border: 1px solid rgba(112, 148, 82, 0.4);">
                                <tr>
                                    <td style="padding: 14px 16px;">
                                        <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">
                                            {{ $isDelivery ? __('shop.track_set_eyebrow_delivery') : __('shop.track_set_eyebrow_pickup') }}
                                        </p>
                                        <p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 18px; color: #2c241c;">
                                            @if ($deliveryDate)
                                                {{ __('shop.track_window_headline', ['date' => $deliveryDate, 'window' => $windowLabel]) }}
                                            @else
                                                {{ __('shop.track_band_window', ['window' => $windowLabel]) }}
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 8px 0;">
                                <tr>
                                    <td align="center" style="padding: 4px 0 20px;">
                                        <a href="{{ $trackUrl }}" style="display: inline-block; background-color: #709452; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 12px 28px; border-radius: 4px;">
                                            {{ __('shop.track_button') }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 8px 0 0 0; font-size: 13px; line-height: 1.6; color: rgba(44, 36, 28, 0.7); border-top: 1px solid rgba(44, 36, 28, 0.1); padding-top: 16px;">
                                {{ __('shop.contact_question') }}<br>
                                <a href="{{ $contactPhoneHref }}" style="color: #21341d;">{{ $contactPhone }}</a> ·
                                <a href="mailto:{{ $contactEmail }}" style="color: #21341d;">{{ $contactEmail }}</a>
                            </p>
                            <p style="margin: 12px 0 0 0; font-size: 13px; line-height: 1.55; color: rgba(44, 36, 28, 0.7);">
                                {{ __('shop.email_signoff') }}<br>
                                <em style="color: rgba(44, 36, 28, 0.55);">— Erdgut</em>
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin: 16px 0 0; font-size: 11px; color: rgba(44, 36, 28, 0.55); font-family: Georgia, 'Times New Roman', serif; font-style: italic;">
                    {{ __('shop.email_order_ref', ['id' => $order->id]) }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
