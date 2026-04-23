@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">{{ __('Delivery') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('Your box arrives tomorrow.') }}</h2>
<p style="margin: 0 0 24px 0;">{{ __('Hallo,') }}</p>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px 0; border-top: 1px solid rgba(44, 36, 28, 0.15);">
    <tr>
        <td style="padding: 12px 12px 12px 0; color: #6b5b4c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(44, 36, 28, 0.08); width: 160px; vertical-align: top;">{{ __('Datum') }}</td>
        <td style="padding: 12px 0; color: #2c241c; border-bottom: 1px solid rgba(44, 36, 28, 0.08);">{{ $date }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 12px 12px 0; color: #6b5b4c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(44, 36, 28, 0.08); vertical-align: top;">{{ __('Zeitfenster') }}</td>
        <td style="padding: 12px 0; color: #2c241c; border-bottom: 1px solid rgba(44, 36, 28, 0.08);">{{ $timeslot }}</td>
    </tr>
    <tr>
        <td style="padding: 12px 12px 12px 0; color: #6b5b4c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(44, 36, 28, 0.08); vertical-align: top;">{{ __('Lieferadresse') }}</td>
        <td style="padding: 12px 0; color: #2c241c; border-bottom: 1px solid rgba(44, 36, 28, 0.08);">{{ $address }}</td>
    </tr>
    @if($instructions)
    <tr>
        <td style="padding: 12px 12px 12px 0; color: #6b5b4c; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; border-bottom: 1px solid rgba(44, 36, 28, 0.08); vertical-align: top;">{{ __('Anmerkungen') }}</td>
        <td style="padding: 12px 0; color: #2c241c; border-bottom: 1px solid rgba(44, 36, 28, 0.08);">{{ $instructions }}</td>
    </tr>
    @endif
</table>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('Bitte stellen Sie sicher, dass jemand die Lieferung entgegennehmen kann.') }}</p>
@endsection
