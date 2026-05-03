@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #ac273b;">{{ __('Payment') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('A hiccup with your last payment.') }}</h2>
<p style="margin: 0 0 16px 0;">{{ __('Hallo,') }}</p>
<p style="margin: 0 0 16px 0;">{{ __('Your last payment of :amount :currency could not be processed.', ['amount' => $amount, 'currency' => $currency]) }}</p>
<p style="margin: 0 0 24px 0;">{{ __('Update your card so your subscription stays on schedule.') }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ $updateUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('Zahlungsmethode aktualisieren') }}</a>
</p>
<p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">{{ __('If you have questions, just write back to this email.') }}</p>
@endsection
