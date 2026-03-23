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

        $piToken = (string) (data_get($checkout, 'paymentInstrument.token') ?? data_get($checkout, 'payment_instrument.token', ''));
        $customerId = (string) (data_get($checkout, 'customerId') ?? data_get($checkout, 'customer_id', ''));

        return [
            'checkout_reference' => data_get($checkout, 'checkoutReference') ?? data_get($checkout, 'checkout_reference'),
            'checkout_status' => data_get($checkout, 'status'),
            'has_customer_id' => trim($customerId) !== '',
            'amount' => data_get($checkout, 'amount'),
            'currency' => data_get($checkout, 'currency'),
            'purpose' => data_get($checkout, 'purpose'),
            'has_payment_instrument_token' => trim($piToken) !== '',
            'transactions' => $transactionStatuses,
        ];
    }

    /**
     * Strip potentially sensitive data from exception messages (API responses, tokens, auth headers).
     */
    public static function sanitizeExceptionMessage(string $message): string
    {
        $message = preg_replace('/[A-Za-z0-9+\/=]{64,}/', '[REDACTED_BASE64]', $message);
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $message);
        $message = preg_replace('/\b(token|api[_-]?key|secret|password|authorization)\s*[:=]\s*\S+/i', '$1=[REDACTED]', $message);

        return $message;
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
