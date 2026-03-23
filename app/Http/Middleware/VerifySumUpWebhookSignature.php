<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySumUpWebhookSignature
{
    private const MAX_AGE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('sumup.webhook_secret', '');
        $signature = (string) $request->header('x-payload-signature', '');

        if (trim($secret) === '') {
            return $next($request);
        }

        if (trim($signature) === '') {
            Log::warning('SumUp webhook rejected due to missing signature header.');

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        $computedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($computedSignature, $signature)) {
            $checkoutId = data_get($request->json()->all(), 'id');

            Log::warning('SumUp webhook signature verification failed.', [
                'event_type' => data_get($request->json()->all(), 'event_type'),
                'checkout_id' => is_scalar($checkoutId) ? (string) $checkoutId : null,
            ]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        if ($this->isStaleRequest($request)) {
            Log::warning('SumUp webhook rejected: request too old.', [
                'date_header' => $request->header('Date'),
                'max_age_seconds' => self::MAX_AGE_SECONDS,
            ]);

            return response('', Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    private function isStaleRequest(Request $request): bool
    {
        $dateHeader = $request->header('Date');
        if ($dateHeader === null || trim($dateHeader) === '') {
            return false;
        }

        try {
            $requestTime = Carbon::parse($dateHeader);
        } catch (\Throwable) {
            return false;
        }

        return $requestTime->diffInSeconds(now(), absolute: true) > self::MAX_AGE_SECONDS;
    }
}
