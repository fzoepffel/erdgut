<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.5; color: #374151; background-color: #f9fafb;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; padding: 24px 0;">
    <tr>
        <td style="padding: 0 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); overflow: hidden;">
                <tr>
                    <td style="padding: 24px 32px; background-color: #047857; color: #ffffff;">
                        <h1 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.025em;">
                            {{ config('app.name') }}
                        </h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 32px;">
                        @yield('content')
                    </td>
                </tr>
                <tr>
                    <td style="padding: 24px 32px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #6b7280;">
                        <p style="margin: 0;">&copy; {{ date('Y') }} Erdgut. {{ __('emails.layout.tagline') }}</p>
                        <p style="margin: 8px 0 0 0;">{{ __('emails.layout.subtagline') }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
