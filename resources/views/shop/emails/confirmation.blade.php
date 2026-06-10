<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('shop.email_heading', ['name' => $order->name]) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
</head>
{{-- Standalone confirmation email — inline styles only, no shared layout.
     Editorial Lovable-prototype palette (cream paper, serif headlines, mono
     eyebrows) with the website's light-green ERDGUT header band on top. --}}
<body style="margin: 0; padding: 0; background-color: #efe6d2; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #4a5147;">
    @php
        $serif = "'Instrument Serif', Georgia, 'Times New Roman', serif";
        $mono = "'JetBrains Mono', 'Courier New', monospace";
        $isInPerson = $order->payment_method === 'in_person';
        $isDelivery = $order->fulfillment_mode === 'delivery';
        $deliveryDate = $order->expected_delivery_date
            ? $order->expected_delivery_date->locale(app()->getLocale())->isoFormat('dddd, D. MMMM YYYY')
            : null;
        $totalLabel = number_format((float) $order->total_amount_eur, 2, ',', '.').' €';
        // Per-rate VAT breakdown (VAT contained in the gross totals).
        $vatByRate = $order->items
            ->groupBy('vat_rate_snapshot_percent')
            ->map(fn ($group) => round($group->sum(fn ($i) => (float) $i->vat_amount_eur), 2));
        if ((float) $order->delivery_fee_vat_amount_eur > 0) {
            $r = (int) $order->delivery_fee_vat_rate_percent;
            $vatByRate[$r] = round(($vatByRate[$r] ?? 0) + (float) $order->delivery_fee_vat_amount_eur, 2);
        }
        $vatByRate = $vatByRate->sortKeys();
        $pickup = \App\Services\Shop\ShopOrderService::pickupLocation($order->pickup_location);
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

                    <tr>
                        <td style="padding: 36px 36px 32px;">
                            <p style="margin: 0 0 14px 0; font-family: {{ $mono }}; font-size: 10px; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: #7a7766;">
                                {{ $isInPerson ? __('shop.email_eyebrow_in_person') : __('shop.email_eyebrow_paid') }}
                            </p>
                            <h1 style="margin: 0 0 18px 0; font-family: {{ $serif }}; font-size: 40px; font-weight: 400; line-height: 1.05; color: #1d2a1e;">
                                {{ __('shop.email_heading', ['name' => $order->name]) }}
                            </h1>
                            <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.6; color: #4a5147;">
                                @if ($isInPerson)
                                    {{ __('shop.email_body_in_person', ['amount' => $totalLabel]) }}
                                @else
                                    {{ __('shop.email_body_paid') }}
                                @endif
                            </p>

                            @if ($deliveryDate)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 28px 0; background-color: #f4ecd8; border: 1px solid #d9cfb6;">
                                    <tr>
                                        <td style="padding: 18px 20px;">
                                            <p style="margin: 0 0 6px 0; font-family: {{ $mono }}; font-size: 10px; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: #7a7766;">
                                                {{ $isDelivery ? __('shop.return_paid_delivery_eyebrow') : __('shop.return_paid_pickup_eyebrow') }}
                                            </p>
                                            <p style="margin: 0 0 10px 0; font-family: {{ $serif }}; font-size: 24px; line-height: 1.15; color: #1d2a1e;">
                                                {{ __('shop.delivery_window_main', ['date' => $deliveryDate]) }}
                                            </p>
                                            <p style="margin: 0; font-size: 13px; line-height: 1.55; color: #4a5147;">
                                                {{ $order->name }}<br>
                                                @if ($isDelivery)
                                                    {{ $order->delivery_street }}, {{ $order->delivery_postal_code }} {{ $order->delivery_city }}
                                                @elseif ($pickup)
                                                    {{ $pickup['name'] }} · {{ $pickup['street'] }}, {{ $pickup['postal_code'] }} {{ $pickup['city'] }}
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @endif

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
                                @if ((float) $order->discount_eur > 0)
                                    <tr>
                                        <td style="padding: 6px 18px; font-size: 14px; color: #4a5147;">{{ __('coupon.discount_line') }} {{ $order->coupon_code }}</td>
                                        <td align="right" style="padding: 6px 18px; font-size: 14px; color: #1d2a1e;">
                                            − {{ number_format((float) $order->discount_eur, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endif
                                @if ($isDelivery)
                                    <tr>
                                        <td style="padding: 6px 18px; font-size: 14px; color: #4a5147;">{{ __('shop.summary_line_delivery') }}</td>
                                        <td align="right" style="padding: 6px 18px; font-size: 14px; color: #1d2a1e;">
                                            {{ number_format((float) $order->delivery_fee_eur, 2, ',', '.') }} €
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding: 6px 18px; font-size: 14px; color: #4a5147;">{{ __('shop.summary_line_fulfillment') }}</td>
                                    <td align="right" style="padding: 6px 18px; font-size: 14px; color: #1d2a1e;">
                                        {{ $isDelivery ? __('shop.fulfillment_delivery') : __('shop.fulfillment_pickup') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 18px; font-size: 14px; color: #4a5147;">{{ __('shop.summary_line_payment_method') }}</td>
                                    <td align="right" style="padding: 6px 18px; font-size: 14px; color: #1d2a1e;">
                                        {{ $isInPerson ? __('shop.payment_in_person') : __('shop.payment_online') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 14px 18px 16px; border-top: 1px solid #d9cfb6; font-family: {{ $serif }}; font-size: 17px; color: #1d2a1e;">
                                        {{ __('shop.summary_line_total') }}
                                    </td>
                                    <td align="right" style="padding: 14px 18px 16px; border-top: 1px solid #d9cfb6; font-family: {{ $serif }}; font-size: 20px; color: #c1502e;">
                                        {{ $totalLabel }}
                                    </td>
                                </tr>
                                @foreach ($vatByRate as $rate => $amount)
                                    <tr>
                                        <td colspan="2" align="right" style="padding: 0 18px 4px; font-family: {{ $mono }}; font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase; color: #8a8f84;">
                                            {{ __('shop.summary_vat_detail', ['rate' => $rate, 'amount' => \App\Support\Money::formatEur($amount)]) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0 0 0;">
                                <tr>
                                    <td align="center" style="padding: 4px 0;">
                                        <a href="{{ $trackUrl }}" style="display: inline-block; background-color: #709452; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; padding: 13px 30px; border-radius: 4px;">
                                            {{ __('shop.track_button') }} →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; font-size: 13px; line-height: 1.6; color: #7a7766; border-top: 1px solid #d9cfb6; padding-top: 18px;">
                                {{ __('shop.contact_question') }}<br>
                                <a href="{{ $contactPhoneHref }}" style="color: #1d2a1e; text-decoration: underline;">{{ $contactPhone }}</a> ·
                                <a href="mailto:{{ $contactEmail }}" style="color: #1d2a1e; text-decoration: underline;">{{ $contactEmail }}</a>
                            </p>

                            <p style="margin: 16px 0 0 0; font-size: 13px; line-height: 1.55; color: #4a5147;">
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
