<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #2c241c; background-color: #f1e7d6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; padding: 32px 0;">
    <tr>
        <td style="padding: 0 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #faf5ec; border: 1px solid #2c241c;">
                <tr>
                    <td style="padding: 32px 32px 16px 32px; border-bottom: 1px solid rgba(44, 36, 28, 0.15); text-align: center;">
                        <p style="margin: 0 0 10px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #21341d;">
                            {{ __('emails.layout.eyebrow') }}
                        </p>
                        <h1 style="margin: 0 0 8px 0; font-family: Georgia, 'Times New Roman', serif; font-size: 32px; font-weight: 600; letter-spacing: -0.01em; color: #2c241c;">
                            {{ config('app.name') }}
                        </h1>
                        <p style="margin: 0; font-size: 14px; letter-spacing: 0.35em; color: #c53a2b;">&#x2731; &#x2731; &#x2731;</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 32px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 32px 24px 32px;">
                        <p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #6b5b4c;">
                            {{ __('emails.layout.signed_off') }}
                        </p>
                        <p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 18px; color: #2c241c;">
                            {{ __('emails.layout.signature') }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 24px 32px 32px 32px; border-top: 1px solid rgba(44, 36, 28, 0.15); text-align: center;">
                        <p style="margin: 0 0 12px 0; font-size: 16px; letter-spacing: 0.4em; color: #c53a2b;">&#x2731; &#x2731; &#x2731;</p>
                        <p style="margin: 0; font-family: Georgia, 'Times New Roman', serif; font-style: italic; font-size: 14px; color: #6b5b4c;">
                            {{ __('emails.layout.tagline') }}
                        </p>
                        <p style="margin: 8px 0 0 0; font-size: 12px; color: #6b5b4c;">
                            &copy; {{ date('Y') }} Erdgut &middot; {{ __('emails.layout.subtagline') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
