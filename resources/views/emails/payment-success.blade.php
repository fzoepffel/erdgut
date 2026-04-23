@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">{{ __('Payment') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('Thank you — your payment has been received.') }}</h2>
<p style="margin: 0 0 24px 0;">{{ __('Hallo,') }}</p>
<p style="margin: 0 0 24px 0;">{{ __('Ihre Zahlung wurde erfolgreich verbucht.') }}</p>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px 0; border-top: 1px solid rgba(44, 36, 28, 0.15);">
    <tr>
        <td style="padding: 12px 12px 12px 0; color: #6b5b4c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(44, 36, 28, 0.08); width: 160px; vertical-align: top;">{{ __('Betrag') }}</td>
        <td style="padding: 12px 0; color: #2c241c; border-bottom: 1px solid rgba(44, 36, 28, 0.08); font-family: Georgia, 'Times New Roman', serif; font-size: 18px;"><strong>{{ $amount }} {{ $currency }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 12px 12px 12px 0; color: #6b5b4c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(44, 36, 28, 0.08); vertical-align: top;">{{ __('Datum') }}</td>
        <td style="padding: 12px 0; color: #2c241c; border-bottom: 1px solid rgba(44, 36, 28, 0.08);">{{ $date }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 12px 12px 0; color: #6b5b4c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(44, 36, 28, 0.08); vertical-align: top;">{{ __('Abo-Referenz') }}</td>
        <td style="padding: 12px 0; color: #2c241c; border-bottom: 1px solid rgba(44, 36, 28, 0.08);">#{{ $subscriptionReference }}</td>
    </tr>
</table>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('Vielen Dank für Ihr Vertrauen.') }}</p>
@endsection
