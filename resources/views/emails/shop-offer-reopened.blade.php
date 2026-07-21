@extends('emails.layout')

@section('content')
<p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #e46f25;">{{ __('emails.shop_offer_reopened.eyebrow') }}</p>
<h2 style="margin: 0 0 20px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 22px; font-weight: 600; color: #2c241c;">{{ __('emails.shop_offer_reopened.heading') }}</h2>
<p style="margin: 0 0 20px 0;">{{ __('emails.shop_offer_reopened.intro') }}</p>
<p style="margin: 0 0 32px 0;">
    <a href="{{ $shopUrl }}" style="display: inline-block; padding: 12px 24px; background-color: #2c241c; color: #f1e7d6; text-decoration: none; font-weight: 600; font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;">{{ __('emails.shop_offer_reopened.cta') }}</a>
</p>
<p style="margin: 0; font-size: 13px; color: #6b5b4c;">
    {{ __('emails.shop_offer_reopened.unsubscribe_prefix') }}
    <a href="{{ $unsubscribeUrl }}" style="color: #6b5b4c;">{{ __('emails.shop_offer_reopened.unsubscribe_link') }}</a>
</p>
@endsection
