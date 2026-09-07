<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine ?? __('mercurial.email.title') }}</title>
    <style>
        .mercurial-body a { color: #4b6b34; text-decoration: underline; }
        .mercurial-body h1 { font-size: 24px; line-height: 1.2; color: #1d2a1e; margin: 0 0 14px; }
        .mercurial-body h2 { font-size: 20px; line-height: 1.25; color: #1d2a1e; margin: 18px 0 10px; }
        .mercurial-body h3 { font-size: 17px; line-height: 1.3; color: #1d2a1e; margin: 16px 0 8px; }
        .mercurial-body p { margin: 0 0 14px; }
        .mercurial-body ul, .mercurial-body ol { margin: 0 0 14px; padding-left: 22px; }
        .mercurial-body li { margin: 0 0 6px; }
        .mercurial-body blockquote { margin: 0 0 14px; padding-left: 14px; border-left: 3px solid #d9cfb6; color: #6b6b5a; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #efe6d2; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #4a5147;">
    @php $mono = "'JetBrains Mono', 'Courier New', monospace"; @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #efe6d2;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; width: 100%; background-color: #ffffff; border: 1px solid #d9cfb6;">
                    <tr>
                        <td style="background-color: #709452; padding: 22px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" style="vertical-align: middle;">
                                        <img src="{{ asset('images/logo/erdgut-wordmark-dark.png') }}" alt="Erdgut" height="26" style="display: inline-block; height: 26px; width: auto; vertical-align: middle; border: 0;">
                                    </td>
                                    <td align="right" style="vertical-align: middle; font-family: {{ $mono }}; font-size: 10px; font-weight: 500; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(255, 255, 255, 0.85);">
                                        {{ __('mercurial.email.eyebrow') }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 34px 36px 30px;">
                            <div class="mercurial-body" style="font-size: 15px; line-height: 1.6; color: #4a5147;">
                                {!! $bodyHtml !!}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 22px 36px 30px; border-top: 1px solid #ece4d0;">
                            <p style="margin: 0; font-family: {{ $mono }}; font-size: 11px; line-height: 1.6; letter-spacing: 0.04em; color: #8a8574;">
                                {{ __('mercurial.email.footer_company') }}<br>
                                <a href="mailto:{{ __('shop.contact_email') }}" style="color: #4b6b34; text-decoration: none;">{{ __('shop.contact_email') }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
