@props([
    // Allow SumUp's hosted gateway (script + frame + connect) — required
    // on layouts that render a SumUp checkout widget.
    'sumup' => false,
    // Allow self-hosted media (video). Marketing layout needs this for the
    // hero clip; everything else stays locked.
    'media' => false,
])

@php
    // Build the CSP per directive. Single source of truth for what every
    // layout's meta-equiv tag emits — extracted from six hardcoded strings
    // so the dev-environment Vite origin can be appended automatically
    // (W2-DEV-02). The previous setup forced developers to run
    // `npm run build` instead of `npm run dev` because Vite's HMR script
    // and HMR websocket weren't in `script-src` / `connect-src`.
    $scriptSrc = "'self' 'unsafe-inline' 'unsafe-eval'";
    $styleSrc = "'self' 'unsafe-inline' https://fonts.bunny.net";
    $fontSrc = "'self' https://fonts.bunny.net";
    $imgSrc = "'self' data: https:";
    $connectSrc = "'self'";
    $frameSrc = null;
    $mediaSrc = null;

    if ($sumup) {
        $scriptSrc .= ' https://gateway.sumup.com';
        $frameSrc = 'https://gateway.sumup.com';
        $connectSrc .= ' https://gateway.sumup.com https://api.sumup.com https://js.sumup.com';
    }

    if ($media) {
        $mediaSrc = "'self'";
    }

    // Local dev: allow the Vite dev server (default port 5173) so
    // `npm run dev` HMR works without the npm-run-build workaround.
    // Production / staging layouts have no exposure to 127.0.0.1.
    if (app()->environment('local')) {
        $viteOrigins = [
            'http://127.0.0.1:5173',
            'http://localhost:5173',
            'http://[::1]:5173',
        ];
        $viteWsOrigins = [
            'ws://127.0.0.1:5173',
            'ws://localhost:5173',
            'ws://[::1]:5173',
        ];
        $scriptSrc .= ' '.implode(' ', $viteOrigins);
        $styleSrc .= ' '.implode(' ', $viteOrigins);
        $connectSrc .= ' '.implode(' ', [...$viteOrigins, ...$viteWsOrigins]);
    }

    $directives = [
        "default-src 'self'",
        "script-src {$scriptSrc}",
        "style-src {$styleSrc}",
        "font-src {$fontSrc}",
        "img-src {$imgSrc}",
        "connect-src {$connectSrc}",
    ];
    if ($frameSrc !== null) {
        $directives[] = "frame-src {$frameSrc}";
    }
    if ($mediaSrc !== null) {
        $directives[] = "media-src {$mediaSrc}";
    }

    $csp = implode('; ', $directives);
@endphp

<meta http-equiv="Content-Security-Policy" content="{{ $csp }}">
