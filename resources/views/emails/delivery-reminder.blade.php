@extends('emails.layout')

@section('content')
<p style="margin: 0 0 16px 0;">{{ __('Hallo,') }}</p>
<p style="margin: 0 0 16px 0;">{{ __('Ihre Bestellung wird morgen geliefert.') }}</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0;">
    <tr>
        <td style="padding: 4px 8px 4px 0; color: #6b7280;">{{ __('Datum:') }}</td>
        <td style="padding: 4px 0;">{{ $date }}</td>
    </tr>
    <tr>
        <td style="padding: 4px 8px 4px 0; color: #6b7280;">{{ __('Zeitfenster:') }}</td>
        <td style="padding: 4px 0;">{{ $timeslot }}</td>
    </tr>
    <tr>
        <td style="padding: 4px 8px 4px 0; color: #6b7280;">{{ __('Lieferadresse:') }}</td>
        <td style="padding: 4px 0;">{{ $address }}</td>
    </tr>
    @if($instructions)
    <tr>
        <td style="padding: 4px 8px 4px 0; color: #6b7280;">{{ __('Anmerkungen:') }}</td>
        <td style="padding: 4px 0;">{{ $instructions }}</td>
    </tr>
    @endif
</table>
<p style="margin: 0; color: #6b7280; font-size: 14px;">{{ __('Bitte stellen Sie sicher, dass jemand die Lieferung entgegennehmen kann.') }}</p>
@endsection
