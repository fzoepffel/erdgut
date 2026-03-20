<?php

namespace App\Support;

use RuntimeException;
use SumUp\SumUp;

class SumUpService
{
    private SumUp $client;

    private string $merchantCode;

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
        return $this->client->checkouts()->create(array_merge($params, [
            'merchant_code' => $this->merchantCode,
        ]));
    }

    public function getCheckout(string $id): object
    {
        return $this->client->checkouts()->get($id);
    }

    public function listPaymentInstruments(string $customerId): object
    {
        return $this->client->customers()->listPaymentInstruments($customerId);
    }
}
