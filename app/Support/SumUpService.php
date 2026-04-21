<?php

namespace App\Support;

use RuntimeException;
use SumUp\Exception\SDKException;
use SumUp\SumUp;

class SumUpService
{
    private SumUp $client;

    private string $merchantCode;

    private string $currency;

    public function __construct()
    {
        $apiKey = (string) config('sumup.api_key', '');
        $merchantCode = (string) config('sumup.merchant_code', '');

        if (trim($apiKey) === '') {
            throw new RuntimeException('Missing SumUp configuration: sumup.api_key');
        }

        if (trim($merchantCode) === '') {
            throw new RuntimeException('Missing SumUp configuration: sumup.merchant_code');
        }

        $this->merchantCode = $merchantCode;
        $this->currency = (string) config('sumup.currency', 'EUR');

        $this->client = new SumUp([
            'api_key' => $apiKey,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCustomer(array $data): object
    {
        return $this->client->customers()->create($data);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function createCheckout(array $params): object
    {
        $payload = array_merge($params, [
            'merchant_code' => $this->merchantCode,
        ]);

        // SumUp semantics (see https://developer.sumup.com/online-payments/webhooks):
        //   return_url   -> server-to-server webhook endpoint SumUp POSTs to
        //   redirect_url -> browser URL used by hosted checkout flows
        if (! isset($payload['return_url'])) {
            $payload['return_url'] = $this->webhookUrl();
        }

        if (! isset($payload['redirect_url'])) {
            $payload['redirect_url'] = $this->browserRedirectUrl();
        }

        $returnUrl = (string) ($payload['return_url'] ?? '');
        $returnHost = parse_url($returnUrl, PHP_URL_HOST) ?: null;

        try {
            $created = $this->client->checkouts()->create($payload);
        } catch (\Throwable $e) {
            SumUpCheckoutLogger::warning('SumUp checkouts.create failed.', [
                'checkout_reference' => $payload['checkout_reference'] ?? null,
                'purpose' => $payload['purpose'] ?? null,
                'customer_id' => $payload['customer_id'] ?? null,
                'amount' => $payload['amount'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'return_url_host' => $returnHost,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);

            throw $e;
        }

        SumUpCheckoutLogger::info('SumUp checkouts.create succeeded.', [
            'checkout_id' => data_get($created, 'id'),
            'checkout_reference' => data_get($created, 'checkoutReference') ?? data_get($created, 'checkout_reference', $payload['checkout_reference'] ?? null),
            'purpose' => $payload['purpose'] ?? null,
            'customer_id' => $payload['customer_id'] ?? null,
            'return_url_host' => $returnHost,
        ]);

        return $created;
    }

    /**
     * Server-to-server webhook endpoint SumUp POSTs checkout status updates to.
     * Per SumUp docs this is the `return_url` field on a checkout.
     */
    private function webhookUrl(): string
    {
        $configured = trim((string) config('sumup.webhook_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return route('sumup.webhook', absolute: true);
    }

    /**
     * URL SumUp opens in the customer's browser for hosted checkout flows.
     * Per SumUp docs this is the `redirect_url` field on a checkout.
     * Must not be the webhook endpoint (that is POST-only, server-to-server).
     */
    private function browserRedirectUrl(): string
    {
        $configured = trim((string) config('sumup.checkout_return_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        return route('app.payments.setup.complete', absolute: true);
    }

    public function createSetupCheckout(
        string $customerId,
        string $checkoutReference,
        string $description
    ): object {
        return $this->createCheckout([
            'checkout_reference' => $checkoutReference,
            'amount' => 1,
            'currency' => $this->currency,
            'description' => $description,
            'customer_id' => $customerId,
            'purpose' => 'SETUP_RECURRING_PAYMENT',
        ]);
    }

    public function getCheckout(string $id): object
    {
        return $this->client->checkouts()->get($id);
    }

    public function createRecurringCheckout(
        float $amount,
        string $customerId,
        string $checkoutReference,
        string $description
    ): object {
        return $this->createCheckout([
            'checkout_reference' => $checkoutReference,
            'amount' => $amount,
            'currency' => $this->currency,
            'description' => $description,
            'customer_id' => $customerId,
            'purpose' => 'CHECKOUT',
        ]);
    }

    /**
     * Create a one-time checkout (no stored mandate) suitable for guest or trial purchases.
     *
     * The customer enters card details on SumUp's hosted checkout; the returned checkout
     * object contains a `hosted_checkout_url` (or similar) that we redirect the browser to.
     *
     * @param  array<string, mixed>  $extraParams  optional fields like `return_url`, `redirect_url`
     */
    public function createOneTimeCheckout(
        float $amount,
        string $checkoutReference,
        string $description,
        ?string $customerId = null,
        array $extraParams = []
    ): object {
        $params = array_merge([
            'checkout_reference' => $checkoutReference,
            'amount' => $amount,
            'currency' => $this->currency,
            'description' => $description,
            'purpose' => 'CHECKOUT',
        ], $extraParams);

        if ($customerId !== null && $customerId !== '') {
            $params['customer_id'] = $customerId;
        }

        return $this->createCheckout($params);
    }

    public function processCheckout(string $checkoutId, string $token, string $customerId): object
    {
        try {
            $result = $this->client->checkouts()->process($checkoutId, [
                'payment_type' => 'card',
                'token' => $token,
                'customer_id' => $customerId,
            ]);
        } catch (\Throwable $e) {
            $responseBody = null;
            $statusCode = null;
            if ($e instanceof SDKException) {
                $statusCode = $e->getStatusCode();
                $raw = $e->getResponseBody();
                $responseBody = is_string($raw) ? mb_substr($raw, 0, 500) : $raw;
            }

            SumUpCheckoutLogger::warning('SumUp checkouts.process failed.', [
                'checkout_id' => $checkoutId,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                'sumup_status_code' => $statusCode,
                'sumup_response_body' => $responseBody,
            ]);

            throw $e;
        }

        SumUpCheckoutLogger::info('SumUp checkouts.process succeeded.', [
            'checkout_id' => $checkoutId,
            'status' => data_get($result, 'status'),
        ]);

        return $result;
    }

    /**
     * @return array<int, object>
     */
    public function listPaymentInstruments(string $customerId): array
    {
        return $this->client->customers()->listPaymentInstruments($customerId);
    }
}
