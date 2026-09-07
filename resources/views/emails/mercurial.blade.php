<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine ?? __('mercurial.email.title') }}</title>
    <style>
        /* Kept deliberately plain: a message that reads like a typed personal
           email lands in the Primary inbox, not Gmail's Promotions tab. No
           banner, no logo image, no card — white background, black text. */
        .mercurial-body a { color: #1a5f2a; }
        .mercurial-body h1 { font-size: 20px; line-height: 1.3; margin: 0 0 12px; }
        .mercurial-body h2 { font-size: 18px; line-height: 1.3; margin: 16px 0 10px; }
        .mercurial-body h3 { font-size: 16px; line-height: 1.3; margin: 14px 0 8px; }
        .mercurial-body p { margin: 0 0 14px; }
        .mercurial-body ul, .mercurial-body ol { margin: 0 0 14px; padding-left: 22px; }
        .mercurial-body li { margin: 0 0 6px; }
        .mercurial-body blockquote { margin: 0 0 14px; padding-left: 14px; border-left: 2px solid #cccccc; color: #555555; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #222222;">
    <div style="max-width: 600px; margin: 0; padding: 20px 24px; font-size: 15px; line-height: 1.6; color: #222222;">
        <div class="mercurial-body">
            {!! $bodyHtml !!}
        </div>

        <p style="margin: 22px 0 0; font-size: 13px; line-height: 1.6; color: #888888;">
            {{ __('mercurial.email.footer_company') }} ·
            <a href="mailto:{{ __('shop.contact_email') }}" style="color: #888888;">{{ __('shop.contact_email') }}</a>
        </p>
    </div>
</body>
</html>
