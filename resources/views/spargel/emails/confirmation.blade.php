<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('spargel.email_heading') }}</title>
</head>
{{-- Standalone confirmation email — inline styles only, no shared
     layout, so the spargel trial-shop can be ripped out cleanly when
     the proper shop launches. --}}
<body style="margin: 0; padding: 0; background-color: #f1e7d6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #2c241c;">
    @php
        $isInPerson = $order->payment_method === 'in_person';
        $deliveryDate = $order->expected_delivery_date
            ? $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY')
            : null;
        $totalLabel = number_format((float) $order->total_amount_eur, 2, ',', '.').' €';
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f1e7d6;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="540" cellpadding="0" cellspacing="0" border="0" style="max-width: 540px; width: 100%; background-color: #ffffff; border: 1px solid rgba(44, 36, 28, 0.15);">
                    <tr>
                        <td style="background-color: #709452; padding: 20px 28px;">
                            <span style="display: inline-block; font-family: Georgia, 'Times New Roman', serif; font-size: 20px; font-weight: 700; letter-spacing: 0.04em; color: #21341d;">ERDGUT</span>
                            <span style="float: right; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(33, 52, 29, 0.85);">{{ __('spargel.eyebrow_trial_shop') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 28px;">
                            <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">
                                {{ $isInPerson ? __('spargel.email_eyebrow_in_person') : __('spargel.email_eyebrow_paid') }}
                            </p>
                            <h2 style="margin: 0 0 16px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 24px; font-weight: 600; color: #2c241c;">
                                {{ __('spargel.email_heading', ['name' => $order->name]) }}
                            </h2>
                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.55; color: rgba(44, 36, 28, 0.85);">
                                @if ($isInPerson)
                                    {{ __('spargel.email_body_in_person', ['amount' => $totalLabel]) }}
                                @else
                                    {{ __('spargel.email_body_paid') }}
                                @endif
                            </p>

                            @if ($deliveryDate)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px 0; background-color: #f1e7d6; border: 1px solid rgba(112, 148, 82, 0.4);">
                                    <tr>
                                        <td style="padding: 14px 16px;">
                                            <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">
                                                @if ($order->fulfillment_mode === 'delivery')
                                                    {{ __('spargel.return_paid_delivery_eyebrow') }}
                                                @else
                                                    {{ __('spargel.return_paid_pickup_eyebrow') }}
                                                @endif
                                            </p>
                                            <p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 16px; color: #2c241c;">
                                                {{ __('spargel.delivery_window_main', ['date' => $deliveryDate]) }}
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; border: 1px solid rgba(44, 36, 28, 0.15); background-color: #faf5ec;">
                                <tr>
                                    <td colspan="2" style="padding: 14px 16px 6px; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(44, 36, 28, 0.6);">
                                        {{ __('spargel.summary_heading') }}
                                    </td>
                                </tr>
                                @if ((int) $order->quantity_green_grams > 0)
                                    <tr>
                                        <td style="padding: 6px 16px; font-size: 14px; color: rgba(44, 36, 28, 0.8);">
                                            {{ (int) $order->quantity_green_grams }} g · {{ __('spargel.type_green') }}
                                        </td>
                                        <td align="right" style="padding: 6px 16px; font-size: 14px; color: #2c241c;">
                                            {{ number_format(((int) $order->quantity_green_grams / 1000) * (float) $order->unit_price_eur, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endif
                                @if ((int) $order->quantity_white_grams > 0)
                                    <tr>
                                        <td style="padding: 6px 16px; font-size: 14px; color: rgba(44, 36, 28, 0.8);">
                                            {{ (int) $order->quantity_white_grams }} g · {{ __('spargel.type_white') }}
                                        </td>
                                        <td align="right" style="padding: 6px 16px; font-size: 14px; color: #2c241c;">
                                            {{ number_format(((int) $order->quantity_white_grams / 1000) * (float) $order->unit_price_eur, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding: 6px 16px; font-size: 14px; color: rgba(44, 36, 28, 0.8);">{{ __('spargel.summary_line_fulfillment') }}</td>
                                    <td align="right" style="padding: 6px 16px; font-size: 14px; color: #2c241c;">
                                        {{ $order->fulfillment_mode === 'delivery' ? __('spargel.fulfillment_delivery') : __('spargel.fulfillment_pickup') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 16px; font-size: 14px; color: rgba(44, 36, 28, 0.8);">{{ __('spargel.summary_line_payment_method') }}</td>
                                    <td align="right" style="padding: 6px 16px; font-size: 14px; color: #2c241c;">
                                        {{ $isInPerson ? __('spargel.payment_in_person') : __('spargel.payment_online') }}
                                    </td>
                                </tr>
                                @if ($order->fulfillment_mode === 'delivery')
                                    <tr>
                                        <td style="padding: 6px 16px; font-size: 14px; color: rgba(44, 36, 28, 0.8); vertical-align: top;">{{ __('spargel.summary_line_delivery_to') }}</td>
                                        <td align="right" style="padding: 6px 16px; font-size: 14px; color: #2c241c;">
                                            {{ $order->delivery_street }}<br>
                                            {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding: 12px 16px 14px; border-top: 1px solid rgba(44, 36, 28, 0.1); font-family: Georgia, 'Times New Roman', serif; font-size: 16px; font-weight: 600; color: #2c241c;">
                                        {{ __('spargel.summary_line_total') }}
                                    </td>
                                    <td align="right" style="padding: 12px 16px 14px; border-top: 1px solid rgba(44, 36, 28, 0.1); font-family: Georgia, 'Times New Roman', serif; font-size: 18px; font-weight: 600; color: #ac273b;">
                                        {{ $totalLabel }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; font-size: 13px; line-height: 1.55; color: rgba(44, 36, 28, 0.7);">
                                {{ __('spargel.email_followup') }}
                            </p>
                            <p style="margin: 12px 0 0 0; font-size: 13px; line-height: 1.55; color: rgba(44, 36, 28, 0.7);">
                                {{ __('spargel.email_signoff') }}<br>
                                <em style="color: rgba(44, 36, 28, 0.55);">— Erdgut</em>
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin: 16px 0 0; font-size: 11px; color: rgba(44, 36, 28, 0.55); font-family: Georgia, 'Times New Roman', serif; font-style: italic;">
                    {{ __('spargel.email_order_ref', ['id' => $order->id]) }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
