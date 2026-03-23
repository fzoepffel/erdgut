@extends('emails.layout')

@section('content')
<p style="margin: 0 0 16px 0;">{{ __('Hallo,') }}</p>
<p style="margin: 0 0 16px 0;">{{ __('Ihre Zahlung wurde erfolgreich verbucht.') }}</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0;">
    <tr>
        <td style="padding: 4px 8px 4px 0; color: #6b7280;">{{ __('Betrag:') }}</td>
        <td style="padding: 4px 0;"><strong>{{ $amount }} {{ $currency }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 4px 8px 4px 0; color: #6b7280;">{{ __('Datum:') }}</td>
        <td style="padding: 4px 0;">{{ $date }}</td>
    </tr>
    <tr>
        <td style="padding: 4px 8px 4px 0; color: #6b7280;">{{ __('Abo-Referenz:') }}</td>
        <td style="padding: 4px 0;">#{{ $subscriptionReference }}</td>
    </tr>
</table>
<p style="margin: 0; color: #6b7280; font-size: 14px;">{{ __('Vielen Dank für Ihr Vertrauen.') }}</p>
@endsection
