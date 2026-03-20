<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySumUpWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('sumup.webhook_secret', '');
        $signature = (string) $request->header('x-payload-signature', '');

        if (trim($secret) === '') {
            Log::warning('SumUp webhook rejected because webhook secret is missing.');

            return response('', Response::HTTP_UNAUTHORIZED);
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

        return $next($request);
    }
}
