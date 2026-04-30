<?php

namespace App\Services;

use App\Enums\SpargelOrderStatus;
use App\Models\SpargelOrder;
use App\Services\Onboarding\TrialOrderService;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Trial-shop service for the standalone spargel sales test.
 *
 * Mirrors {@see TrialOrderService} structurally
 * (create row → create SumUp one-time checkout → confirm via return URL)
 * but stores everything on a single {@see SpargelOrder} row with no User
 * or Order linkage. Customer email is the canonical identifier.
 *
 * Pricing model: 20 €/kg flat, gross (consumer prices in DE include VAT).
 * Asparagus is fresh produce → 7% reduced VAT rate. Quantity is captured
 * in *grams* in the form because that's the natural unit for produce
 * portions; pricing math stays in euros.
 *
 * Fulfilment cutoff: orders placed Mon–Thu deliver the coming Monday;
 * orders placed Fri–Sun shift to the Monday after that. The cutoff
 * date is locked at order-create time (`expected_delivery_date`) so
 * downstream admin views don't have to recompute when the customer
 * checks back days later.
 */
class SpargelOrderService
{
    /** Pricing constants — single test channel, no admin override yet. */
    public const PRICE_PER_KG_EUR = 20.0;

    public const DELIVERY_FEE_EUR = 1.99;

    public const DELIVERY_MIN_GRAMS = 500;

    /** Reduced VAT rate for fresh produce in Germany (already in price). */
    public const VAT_RATE_PERCENT = 7;

    public function __construct(private readonly SumUpService $sumUpService) {}

    /**
     * Compute the total a customer will be charged for a hypothetical order.
     * Stays in one place so the form preview, the controller validation,
     * and the SumUp checkout can never disagree.
     *
     * @return array{quantity_grams:int, unit_price_eur:float, line_total_eur:float, delivery_fee_eur:float, total_amount_eur:float}
     */
    public function quote(int $quantityGrams, string $fulfillmentMode): array
    {
        $quantityGrams = max(0, $quantityGrams);
        $line = round(($quantityGrams / 1000) * self::PRICE_PER_KG_EUR, 2);
        $deliveryFee = $fulfillmentMode === 'delivery' ? self::DELIVERY_FEE_EUR : 0.0;

        return [
            'quantity_grams' => $quantityGrams,
            'unit_price_eur' => self::PRICE_PER_KG_EUR,
            'line_total_eur' => $line,
            'delivery_fee_eur' => $deliveryFee,
            'total_amount_eur' => round($line + $deliveryFee, 2),
        ];
    }

    /**
     * The Monday on which an order placed at $now will be delivered.
     *
     * Rule: Mon–Thu cutoff → coming Monday; Fri–Sun → following Monday.
     * Always returns a Carbon at start-of-day in the app timezone so the
     * date is formatter-friendly.
     */
    public function nextDeliveryMonday(?Carbon $now = null): Carbon
    {
        $now = $now ?? Carbon::now();

        // dayOfWeekIso: 1 = Mon, …, 7 = Sun.
        $iso = (int) $now->dayOfWeekIso;

        // Carbon's next() always returns a *future* date, never today —
        // exactly what we want (we can't deliver same-day).
        $monday = $now->copy()->next(Carbon::MONDAY);

        // Friday (5) onwards including Sunday (7) miss the Thursday cutoff
        // and shift to the Monday after.
        if ($iso >= 5) {
            $monday = $monday->copy()->addWeek();
        }

        return $monday->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOrder(array $payload): SpargelOrder
    {
        $quote = $this->quote(
            (int) ($payload['quantity_grams'] ?? 0),
            (string) ($payload['fulfillment_mode'] ?? 'pickup')
        );

        return DB::transaction(fn () => SpargelOrder::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'] ?? null,
            'asparagus_type' => $payload['asparagus_type'],
            'quantity_grams' => $quote['quantity_grams'],
            'unit_price_eur' => $quote['unit_price_eur'],
            'fulfillment_mode' => $payload['fulfillment_mode'],
            'delivery_fee_eur' => $quote['delivery_fee_eur'],
            'total_amount_eur' => $quote['total_amount_eur'],
            'expected_delivery_date' => $this->nextDeliveryMonday()->toDateString(),
            'delivery_street' => $payload['delivery_street'] ?? null,
            'delivery_postal_code' => $payload['delivery_postal_code'] ?? null,
            'delivery_city' => $payload['delivery_city'] ?? null,
            'delivery_notes' => $payload['delivery_notes'] ?? null,
            'status' => SpargelOrderStatus::PendingPayment,
        ]));
    }

    public function initiateCheckout(SpargelOrder $order, string $returnUrl): object
    {
        $reference = 'spargel-'.$order->id.'-'.now()->timestamp;

        $checkout = $this->sumUpService->createOneTimeCheckout(
            amount: (float) $order->total_amount_eur,
            checkoutReference: $reference,
            description: 'Erdgut Spargel-Bestellung',
            customerId: null,
            extraParams: ['redirect_url' => $returnUrl],
        );

        $checkoutId = (string) (data_get($checkout, 'id') ?? '');

        if ($checkoutId !== '') {
            $order->forceFill(['sumup_checkout_id' => $checkoutId])->save();
        }

        SumUpCheckoutLogger::info('Spargel order: SumUp one-time checkout created.', [
            'spargel_order_id' => $order->id,
            'checkout_id' => $checkoutId !== '' ? $checkoutId : null,
            'checkout_reference' => $reference,
        ]);

        return $checkout;
    }

    /**
     * Server-side verification — used after SumUp redirects the browser back
     * to our return URL. Never trust the redirect itself; ask SumUp.
     *
     * Returns true if the order is now paid.
     */
    public function confirmPaidFromProvider(SpargelOrder $order): bool
    {
        if ($order->status === SpargelOrderStatus::Paid) {
            return true;
        }

        if ($order->status !== SpargelOrderStatus::PendingPayment) {
            return false;
        }

        if ($order->sumup_checkout_id === null) {
            return false;
        }

        try {
            $checkout = $this->sumUpService->getCheckout($order->sumup_checkout_id);
        } catch (Throwable $e) {
            SumUpCheckoutLogger::warning('Spargel order: failed to verify checkout with SumUp.', [
                'spargel_order_id' => $order->id,
                'checkout_id' => $order->sumup_checkout_id,
                'exception_class' => $e::class,
                'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
            ]);

            return false;
        }

        $status = strtoupper((string) data_get($checkout, 'status', ''));

        if ($status !== 'PAID') {
            SumUpCheckoutLogger::info('Spargel order: return-url verification — not paid yet.', [
                'spargel_order_id' => $order->id,
                'checkout_id' => $order->sumup_checkout_id,
                'checkout_status' => $status ?: null,
            ]);

            return false;
        }

        $transactions = (array) (data_get($checkout, 'transactions', []) ?? []);
        $paymentId = (string) (data_get($transactions, '0.id') ?? '');

        $order->forceFill([
            'status' => SpargelOrderStatus::Paid,
            'paid_at' => now(),
            'sumup_payment_id' => $paymentId !== '' ? $paymentId : null,
            'provider_payload' => is_object($checkout) ? json_decode(json_encode($checkout), true) : null,
        ])->save();

        SumUpCheckoutLogger::info('Spargel order: return-url verification — marked paid.', [
            'spargel_order_id' => $order->id,
            'checkout_id' => $order->sumup_checkout_id,
            'sumup_payment_id' => $paymentId !== '' ? $paymentId : null,
        ]);

        return true;
    }
}
