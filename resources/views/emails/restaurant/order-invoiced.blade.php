@extends('emails.layout')

@section('content')
    <h2 style="margin: 0 0 12px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">
        {{ __('restaurant.order_email.invoiced_heading') }}
    </h2>
    <p style="margin: 0 0 16px 0; font-size: 15px; color: #2c241c;">
        {{ __('restaurant.order_email.invoiced_body', [
            'number' => $order->invoice_number,
            'total' => \App\Support\Money::formatEur((float) $order->items_total_gross_eur, $order->locale),
            'due' => optional($order->invoice_due_date)->translatedFormat('d.m.Y'),
        ]) }}
    </p>
    <p style="margin: 0 0 16px 0; font-size: 14px; color: #6b5b4c;">{{ __('restaurant.order_email.invoiced_attachment_note') }}</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 8px 0;">
        <tr>
            <td style="background-color: #21341d; border-radius: 4px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; color: #faf5ec; font-size: 14px; font-weight: 600; text-decoration: none;">
                    {{ __('restaurant.order_email.invoiced_cta') }} &rarr;
                </a>
            </td>
        </tr>
    </table>
@endsection
