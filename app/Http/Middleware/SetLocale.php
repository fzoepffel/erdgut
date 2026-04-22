<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['en']);
        $fallback = (string) config('app.fallback_locale', 'en');

        $locale = $this->resolveLocale($request, $supported, $fallback);

        App::setLocale($locale);

        return $next($request);
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function resolveLocale(Request $request, array $supported, string $fallback): string
    {
        $fromSession = $request->hasSession() ? (string) $request->session()->get('locale', '') : '';
        if ($fromSession !== '' && in_array($fromSession, $supported, true)) {
            return $fromSession;
        }

        $fromHeader = $this->matchAcceptLanguage($request->headers->get('Accept-Language', ''), $supported);
        if ($fromHeader !== null) {
            return $fromHeader;
        }

        return in_array($fallback, $supported, true) ? $fallback : ($supported[0] ?? 'en');
    }

    /**
     * Parse an Accept-Language header and return the highest-priority supported locale, if any.
     *
     * @param  array<int, string>  $supported
     */
    private function matchAcceptLanguage(string $header, array $supported): ?string
    {
        if ($header === '') {
            return null;
        }

        $entries = [];
        foreach (explode(',', $header) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $quality = 1.0;
            $tag = $chunk;
            if (str_contains($chunk, ';')) {
                [$tag, $params] = array_map('trim', explode(';', $chunk, 2));
                if (preg_match('/q\s*=\s*([0-9.]+)/', $params, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }

            $primary = strtolower(explode('-', $tag)[0]);
            if ($primary === '' || $primary === '*') {
                continue;
            }

            $entries[] = ['locale' => $primary, 'quality' => $quality];
        }

        usort($entries, static fn ($a, $b) => $b['quality'] <=> $a['quality']);

        foreach ($entries as $entry) {
            if (in_array($entry['locale'], $supported, true)) {
                return $entry['locale'];
            }
        }

        return null;
    }
}
