{{-- Deliberately lean and self-contained (does NOT extend emails.layout).
     A plain, personal, conversational note with a single inline text link
     reads as transactional to Gmail and tends to land in the Primary tab,
     unlike the branded newsletter template which gets filed under Promotions. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('emails.shop_offer_reopened.subject') }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #ffffff;">
    <div style="max-width: 520px; margin: 0 auto; padding: 24px 22px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #2c241c;">
        <p style="margin: 0 0 16px 0;">{{ __('emails.shop_offer_reopened.greeting') }}</p>
        <p style="margin: 0 0 16px 0;">{{ __('emails.shop_offer_reopened.intro') }}</p>
        <p style="margin: 0 0 20px 0;">
            <a href="{{ $shopUrl }}" style="color: #8a4b1f;">{{ __('emails.shop_offer_reopened.cta') }}</a>
        </p>
        <p style="margin: 0 0 2px 0;">{{ __('emails.shop_offer_reopened.signoff') }}</p>
        <p style="margin: 0 0 28px 0;">{{ __('emails.shop_offer_reopened.signoff_name') }}</p>
        <p style="margin: 0; font-size: 13px; color: #8a7a6b;">
            {{ __('emails.shop_offer_reopened.unsubscribe_prefix') }}
            <a href="{{ $unsubscribeUrl }}" style="color: #8a7a6b;">{{ __('emails.shop_offer_reopened.unsubscribe_link') }}</a>
        </p>
    </div>
</body>
</html>
