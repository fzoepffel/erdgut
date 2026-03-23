<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Structured, non-sensitive logging for SumUp checkout flows.
 */
final class SumUpCheckoutLogger
{
    /**
     * Safe summary of a checkout API object for debugging (no tokens / PAN).
     *
     * @param  object|array<string, mixed>  $checkout
     * @return array<string, mixed>
     */
    public static function checkoutSnapshot(object|array $checkout): array
    {
        $transactions = data_get($checkout, 'transactions', []);
        $transactionStatuses = [];

        if (is_array($transactions)) {
            foreach ($transactions as $index => $t) {
                $transactionStatuses[] = [
                    'index' => $index,
                    'status' => data_get($t, 'status'),
                    'payment_type' => data_get($t, 'paymentType') ?? data_get($t, 'payment_type'),
                ];
            }
        }

        $piToken = (string) data_get($checkout, 'paymentInstrument.token', '');

        return [
            'checkout_reference' => data_get($checkout, 'checkoutReference'),
            'checkout_status' => data_get($checkout, 'status'),
            'customer_id' => data_get($checkout, 'customerId'),
            'amount' => data_get($checkout, 'amount'),
            'currency' => data_get($checkout, 'currency'),
            'purpose' => data_get($checkout, 'purpose'),
            'has_payment_instrument_token' => trim($piToken) !== '',
            'mandate' => data_get($checkout, 'mandate'),
            'transactions' => $transactionStatuses,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, array_merge(['sumup_flow' => true], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge(['sumup_flow' => true], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        Log::error($message, array_merge(['sumup_flow' => true], $context));
    }
}
