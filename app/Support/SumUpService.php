<?php

namespace App\Support;

use RuntimeException;
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

        if (! isset($payload['return_url'])) {
            $payload['return_url'] = $this->checkoutReturnUrl();
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
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // #region agent log
        @file_put_contents(base_path('.cursor/debug-07cfdd.log'), json_encode(['sessionId' => '07cfdd', 'location' => 'SumUpService:createCheckout', 'message' => 'Checkout created', 'data' => ['checkout_id' => data_get($created, 'id'), 'return_url' => $returnUrl, 'purpose' => $payload['purpose'] ?? null], 'timestamp' => round(microtime(true) * 1000), 'hypothesisId' => 'H1'])."\n", FILE_APPEND);
        // #endregion

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
     * URL SumUp may open in the customer's browser after 3DS or similar steps.
     * Must not be the API webhook endpoint (that is POST-only, server-to-server).
     */
    private function checkoutReturnUrl(): string
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

    public function processCheckout(string $checkoutId, string $token, string $customerId): object
    {
        return $this->client->checkouts()->process($checkoutId, [
            'payment_type' => 'card',
            'token' => $token,
            'customer_id' => $customerId,
        ]);
    }

    /**
     * @return array<int, object>
     */
    public function listPaymentInstruments(string $customerId): array
    {
        return $this->client->customers()->listPaymentInstruments($customerId);
    }
}
