<?php

namespace App\Http\Controllers;

use App\Support\SumUpWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SumUpWebhookController extends Controller
{
    public function __construct(
        private readonly SumUpWebhookHandler $handler
    ) {}

    public function handle(Request $request): Response
    {
        try {
            $payload = $request->json()->all();

            if (($payload['event_type'] ?? '') === 'CHECKOUT_STATUS_CHANGED') {
                $this->handler->handleCheckoutStatusChanged($payload);
            }
        } catch (Throwable $e) {
            Log::error('SumUp webhook processing failed.', [
                'error' => $e->getMessage(),
            ]);
        }

        return response('', Response::HTTP_OK);
    }
}
