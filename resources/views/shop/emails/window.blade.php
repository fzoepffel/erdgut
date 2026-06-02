<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->fulfillment_mode === 'delivery' ? __('shop.window_email_heading_delivery') : __('shop.window_email_heading_pickup') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
</head>
{{-- Standalone "window is set" email — inline styles only. Editorial
     Lovable-prototype LiveStatus band (deep green, serif window, mono subline)
     under the website's light-green ERDGUT header band. --}}
<body style="margin: 0; padding: 0; background-color: #efe6d2; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #4a5147;">
    @php
        $serif = "'Instrument Serif', Georgia, 'Times New Roman', serif";
        $mono = "'JetBrains Mono', 'Courier New', monospace";
        $isDelivery = $order->fulfillment_mode === 'delivery';
        $deliveryDate = $order->expected_delivery_date
            ? $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM')
            : null;
        $windowLabel = $order->deliveryWindowLabel();
        $totalLabel = number_format((float) $order->total_amount_eur, 2, ',', '.').' €';
        $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location);
        $locationLine = $isDelivery
            ? trim($order->delivery_postal_code.' '.$order->delivery_city)
            : ($pickup['street'] ?? null);
        $subline = collect([$deliveryDate, $locationLine])->filter()->implode(' · ');
        $contactPhone = \App\Services\Shop\ShopOrderService::contactPhone($order);
        $contactPhoneHref = 'tel:'.preg_replace('/[^+0-9]/', '', $contactPhone);
        $contactEmail = __('shop.contact_email');
    @endphp

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #efe6d2;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; width: 100%; background-color: #ffffff; border: 1px solid #d9cfb6;">
                    {{-- Website-style header: light-green band + ERDGUT logo --}}
                    <tr>
                        <td style="background-color: #709452; padding: 22px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" style="vertical-align: middle;">
                                        <img src="{{ asset('images/logo/erdgut-wordmark-dark.png') }}" alt="Erdgut" height="26" style="display: inline-block; height: 26px; width: auto; vertical-align: middle; border: 0;">
                                    </td>
                                    <td align="right" style="vertical-align: middle; font-family: {{ $mono }}; font-size: 10px; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(255, 255, 255, 0.85);">
                                        {{ __('shop.eyebrow') }} · Berlin
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- LiveStatus band: the window, front and centre --}}
                    <tr>
                        <td style="background-color: #2b4a2e; padding: 32px 36px;">
                            <p style="margin: 0 0 14px 0; font-family: {{ $mono }}; font-size: 10px; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(244, 236, 216, 0.75);">
                                {{ $isDelivery ? __('shop.track_set_eyebrow_delivery') : __('shop.track_set_eyebrow_pickup') }}
                            </p>
                            <p style="margin: 0 0 10px 0; font-family: {{ $serif }}; font-size: 38px; font-weight: 400; line-height: 1.05; color: #f4ecd8;">
                                {{ __('shop.track_band_window', ['window' => $windowLabel]) }}
                            </p>
                            @if ($subline)
                                <p style="margin: 0 0 18px 0; font-family: {{ $mono }}; font-size: 11px; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(244, 236, 216, 0.7);">
                                    {{ $subline }}
                                </p>
                            @endif
                            <p style="margin: 0; font-size: 14px; line-height: 1.6; color: rgba(244, 236, 216, 0.88);">
                                @if ($isDelivery)
                                    {{ __('shop.track_set_helper_delivery') }}
                                @elseif ($pickup)
                                    {{ __('shop.track_set_helper_pickup', ['street' => $pickup['street']]) }}
                                @else
                                    {{ __('shop.track_set_helper_pickup_generic') }}
                                @endif
                            </p>
                            <p style="margin: 18px 0 0 0; font-size: 12px; line-height: 1.6; color: rgba(244, 236, 216, 0.7); border-top: 1px solid rgba(244, 236, 216, 0.18); padding-top: 14px;">
                                {{ __('shop.contact_question') }}
                                <a href="{{ $contactPhoneHref }}" style="color: #f4ecd8; text-decoration: underline;">{{ $contactPhone }}</a> ·
                                <a href="mailto:{{ $contactEmail }}" style="color: #f4ecd8; text-decoration: underline;">{{ $contactEmail }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 28px 36px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 28px 0;">
                                <tr>
                                    <td align="center" style="padding: 4px 0;">
                                        <a href="{{ $trackUrl }}" style="display: inline-block; background-color: #709452; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 13px 30px; border-radius: 4px;">
                                            {{ __('shop.track_button') }} →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; border: 1px solid #d9cfb6;">
                                <tr>
                                    <td colspan="2" style="padding: 16px 18px 8px; font-family: {{ $mono }}; font-size: 10px; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: #7a7766;">
                                        {{ __('shop.summary_heading') }}
                                    </td>
                                </tr>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td style="padding: 6px 18px; font-size: 14px; color: #4a5147;">
                                            {{ $item->product?->displayQuantity((float) $item->quantity) }} · {{ $item->product?->name }}
                                        </td>
                                        <td align="right" style="padding: 6px 18px; font-size: 14px; color: #1d2a1e;">
                                            {{ number_format((float) $item->line_total_eur, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td style="padding: 14px 18px 16px; border-top: 1px solid #d9cfb6; font-family: {{ $serif }}; font-size: 17px; color: #1d2a1e;">
                                        {{ __('shop.summary_line_total') }}
                                    </td>
                                    <td align="right" style="padding: 14px 18px 16px; border-top: 1px solid #d9cfb6; font-family: {{ $serif }}; font-size: 20px; color: #c1502e;">
                                        {{ $totalLabel }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; font-size: 13px; line-height: 1.55; color: #4a5147;">
                                {{ __('shop.email_signoff') }}<br>
                                <em style="font-family: {{ $serif }}; font-style: italic; color: #7a7766;">— Erdgut</em>
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin: 16px 0 0; font-family: {{ $mono }}; font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; color: #7a7766;">
                    {{ __('shop.email_order_ref', ['id' => $order->id]) }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
