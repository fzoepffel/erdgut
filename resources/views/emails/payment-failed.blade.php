@extends('emails.layout')

@section('content')
<p style="margin: 0 0 16px 0;">{{ __('Hallo,') }}</p>
<p style="margin: 0 0 16px 0;">{{ __('Ihre letzte Zahlung von :amount :currency konnte leider nicht durchgeführt werden.', ['amount' => $amount, 'currency' => $currency]) }}</p>
<p style="margin: 0 0 24px 0;">{{ __('Bitte aktualisieren Sie Ihre Zahlungsmethode, damit Ihre Abo-Lieferung weiterhin erfolgen kann.') }}</p>
<p style="margin: 0 0 24px 0;">
    <a href="{{ $updateUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #047857; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600;">{{ __('Zahlungsmethode aktualisieren') }}</a>
</p>
<p style="margin: 0; color: #6b7280; font-size: 14px;">{{ __('Bei Fragen wenden Sie sich gern an uns.') }}</p>
@endsection
