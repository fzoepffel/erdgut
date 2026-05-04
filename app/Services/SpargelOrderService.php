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
    public const PRICE_PER_KG_EUR = 21.50;

    public const DELIVERY_FEE_EUR = 2.90;

    public const DELIVERY_MIN_GRAMS = 500;

    /** Reduced VAT rate for fresh produce in Germany (already in price). */
    public const VAT_RATE_PERCENT = 7;

    /**
     * The two pickup locations a customer can choose from. Each entry's
     * key is what we persist in `pickup_location`; the rest is the
     * surface info (name + address) shown on the confirmation screen,
     * the email, and the admin list.
     *
     * The order page (form) intentionally shows only the address — the
     * person's name appears later (post-submit) so the form stays
     * neutral about who the customer will physically meet.
     *
     * @var array<string, array{name:string, street:string, postal_code:string, city:string}>
     */
    public const PICKUP_LOCATIONS = [
        'karl_kunger' => [
            'name' => 'Arthur Hamcha',
            'street' => 'Karl-Kunger-Straße 9',
            'postal_code' => '12435',
            'city' => 'Berlin',
        ],
        'gotenstrasse' => [
            'name' => 'Milan Ross',
            'street' => 'Gotenstrasse 19',
            'postal_code' => '10829',
            'city' => 'Berlin',
        ],
    ];

    /**
     * Look up a pickup location by its key. Returns null if the key
     * isn't a known location (defensive against a stale DB row).
     *
     * @return array{name:string, street:string, postal_code:string, city:string}|null
     */
    public static function pickupLocation(?string $key): ?array
    {
        if ($key === null || ! isset(self::PICKUP_LOCATIONS[$key])) {
            return null;
        }

        return self::PICKUP_LOCATIONS[$key];
    }

    /** Customers buy in 50 g increments. Same constant guards the form
     *  dropdown options, the FormRequest validator, and the quote()
     *  internals so all three agree. */
    public const QUANTITY_STEP_GRAMS = 50;

    /** Smallest *positive* per-variety amount. The dropdown also offers
     *  0 (= "none of this variety"); the validator allows 0 OR
     *  multiples of QUANTITY_STEP_GRAMS at or above this floor. */
    public const QUANTITY_PER_VARIETY_MIN_GRAMS = 50;

    /** Largest amount we'll accept for a single variety on a single
     *  order — same as the combined ceiling. */
    public const QUANTITY_PER_VARIETY_MAX_GRAMS = 3000;

    /** Smallest combined order. Below this is too little for a
     *  meaningful portion (we'd lose money on the handling). */
    public const QUANTITY_COMBINED_MIN_GRAMS = 100;

    /** Sanity ceiling on the combined order. Customers ordering more
     *  than 3 kg get in touch directly. */
    public const QUANTITY_COMBINED_MAX_GRAMS = 3000;

    /**
     * The list of values each per-variety dropdown offers — 0 (none of
     * this variety) plus every step between MIN and MAX, inclusive.
     * Pre-rendered once instead of computed in Blade.
     *
     * @return array<int, int>
     */
    public static function quantityOptions(): array
    {
        $options = [0];
        for ($g = self::QUANTITY_PER_VARIETY_MIN_GRAMS; $g <= self::QUANTITY_PER_VARIETY_MAX_GRAMS; $g += self::QUANTITY_STEP_GRAMS) {
            $options[] = $g;
        }

        return $options;
    }

    public function __construct(private readonly SumUpService $sumUpService) {}

    /**
     * Compute the total a customer will be charged for a hypothetical order.
     * Stays in one place so the form preview, the controller validation,
     * and the SumUp checkout can never disagree. Both varieties share the
     * same per-kg price.
     *
     * @return array{quantity_green_grams:int, quantity_white_grams:int, total_grams:int, unit_price_eur:float, line_total_eur:float, delivery_fee_eur:float, total_amount_eur:float}
     */
    public function quote(int $greenGrams, int $whiteGrams, string $fulfillmentMode): array
    {
        $green = max(0, $greenGrams);
        $white = max(0, $whiteGrams);
        $totalGrams = $green + $white;
        $line = round(($totalGrams / 1000) * self::PRICE_PER_KG_EUR, 2);
        $deliveryFee = $fulfillmentMode === 'delivery' ? self::DELIVERY_FEE_EUR : 0.0;

        return [
            'quantity_green_grams' => $green,
            'quantity_white_grams' => $white,
            'total_grams' => $totalGrams,
            'unit_price_eur' => self::PRICE_PER_KG_EUR,
            'line_total_eur' => $line,
            'delivery_fee_eur' => $deliveryFee,
            'total_amount_eur' => round($line + $deliveryFee, 2),
        ];
    }

    /**
     * Per-variety order cutoff (ISO weekday: Mon=1, …, Sun=7). Anything
     * ordered up to and including the cutoff day ships the coming Monday;
     * anything ordered later shifts to the Monday after.
     *
     * Green needs more lead time in the field, so it cuts off on
     * Thursday. White can be picked at very short notice — Max harvests
     * Sunday for Monday — so we accept white right up to Sunday.
     */
    public const CUTOFF_ISO_GREEN = 4; // Thursday

    public const CUTOFF_ISO_WHITE = 7; // Sunday

    /**
     * The Monday on which an order placed at $now will be delivered.
     *
     * The cutoff depends on the varieties in the cart: green-only or
     * mixed orders use the green (Thursday) cutoff because the same
     * delivery day has to wait for the slower-to-prepare variety.
     * White-only orders may use the Sunday cutoff. With nothing
     * selected we fall back to the green cutoff (the safe default for
     * the form's initial banner before the customer picks a variety).
     *
     * Always returns a Carbon at start-of-day in the app timezone so the
     * date is formatter-friendly.
     */
    public function nextDeliveryMonday(
        ?Carbon $now = null,
        int $greenGrams = 0,
        int $whiteGrams = 0,
    ): Carbon {
        $now = $now ?? Carbon::now();

        $cutoffIso = $this->cutoffIsoForCart($greenGrams, $whiteGrams);

        // dayOfWeekIso: 1 = Mon, …, 7 = Sun.
        $iso = (int) $now->dayOfWeekIso;

        // Carbon's next() always returns a *future* date, never today —
        // exactly what we want (we can't deliver same-day).
        $monday = $now->copy()->next(Carbon::MONDAY);

        // Past the cutoff for the varieties in the cart → shift one more
        // week so Max has enough lead time to prepare.
        if ($iso > $cutoffIso) {
            $monday = $monday->copy()->addWeek();
        }

        return $monday->startOfDay();
    }

    /**
     * Pick the strictest cutoff that applies to the varieties in the cart.
     * Green wins over white when both are present; an empty cart defaults
     * to the green cutoff so the form's initial banner stays conservative.
     */
    private function cutoffIsoForCart(int $greenGrams, int $whiteGrams): int
    {
        if ($greenGrams > 0) {
            return self::CUTOFF_ISO_GREEN;
        }

        if ($whiteGrams > 0) {
            return self::CUTOFF_ISO_WHITE;
        }

        return self::CUTOFF_ISO_GREEN;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOrder(array $payload): SpargelOrder
    {
        $quote = $this->quote(
            (int) ($payload['quantity_green_grams'] ?? 0),
            (int) ($payload['quantity_white_grams'] ?? 0),
            (string) ($payload['fulfillment_mode'] ?? 'pickup')
        );

        return DB::transaction(fn () => SpargelOrder::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'] ?? null,
            'quantity_green_grams' => $quote['quantity_green_grams'],
            'quantity_white_grams' => $quote['quantity_white_grams'],
            'unit_price_eur' => $quote['unit_price_eur'],
            'fulfillment_mode' => $payload['fulfillment_mode'],
            'delivery_fee_eur' => $quote['delivery_fee_eur'],
            'total_amount_eur' => $quote['total_amount_eur'],
            'payment_method' => $payload['payment_method'] ?? 'online',
            'expected_delivery_date' => $this->nextDeliveryMonday(
                null,
                $quote['quantity_green_grams'],
                $quote['quantity_white_grams'],
            )->toDateString(),
            'delivery_street' => $payload['delivery_street'] ?? null,
            'delivery_postal_code' => $payload['delivery_postal_code'] ?? null,
            'delivery_city' => $payload['delivery_city'] ?? null,
            'delivery_notes' => $payload['delivery_notes'] ?? null,
            // Only persist a pickup_location for pickup orders.
            'pickup_location' => ($payload['fulfillment_mode'] ?? null) === 'pickup'
                ? ($payload['pickup_location'] ?? null)
                : null,
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

    /** Verification outcomes returned by {@see verifyPaymentWithProvider}. */
    public const VERIFY_PAID = 'paid';

    public const VERIFY_FAILED = 'failed';

    public const VERIFY_PENDING = 'pending';

    /**
     * Server-side verification — used after SumUp redirects the browser back
     * to our return URL. Never trust the redirect itself; ask SumUp.
     *
     * Returns one of three string outcomes:
     *  - VERIFY_PAID    → checkout is PAID; order has been moved to Paid.
     *  - VERIFY_FAILED  → checkout (or its latest transaction) is FAILED /
     *                     EXPIRED; order has been moved to Failed.
     *  - VERIFY_PENDING → checkout still pending OR the verification
     *                     itself failed (network error etc.); order
     *                     status untouched.
     *
     * Idempotent — if the order is already Paid or Failed we return that
     * directly without hitting SumUp.
     */
    public function verifyPaymentWithProvider(SpargelOrder $order): string
    {
        if ($order->status === SpargelOrderStatus::Paid) {
            return self::VERIFY_PAID;
        }
        if ($order->status === SpargelOrderStatus::Failed) {
            return self::VERIFY_FAILED;
        }
        if ($order->status !== SpargelOrderStatus::PendingPayment) {
            return self::VERIFY_PENDING;
        }
        if ($order->sumup_checkout_id === null) {
            return self::VERIFY_PENDING;
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

            return self::VERIFY_PENDING;
        }

        $checkoutStatus = strtoupper((string) data_get($checkout, 'status', ''));
        $transactions = (array) (data_get($checkout, 'transactions', []) ?? []);

        if ($checkoutStatus === 'PAID') {
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

            return self::VERIFY_PAID;
        }

        // The checkout itself can be FAILED / EXPIRED, or the checkout can
        // still be PENDING while a 3DS attempt has flipped the most recent
        // transaction to FAILED. Treat both as "this attempt failed" — the
        // customer can retry, which spawns a fresh checkout (see
        // SpargelOrderController::pay).
        $transactionStatuses = array_map(
            fn ($tx) => strtoupper((string) data_get($tx, 'status', '')),
            $transactions,
        );
        $hasFailedTransaction = in_array('FAILED', $transactionStatuses, true);
        $checkoutFailed = in_array($checkoutStatus, ['FAILED', 'EXPIRED'], true);

        if ($checkoutFailed || $hasFailedTransaction) {
            // Bump the failed-attempts counter so the admin row preserves the
            // history even when the customer clicks "Try again" (which resets
            // status back to PendingPayment in SpargelOrderController::pay).
            // Without this, repeated failed attempts leave no visible trace.
            $order->forceFill([
                'status' => SpargelOrderStatus::Failed,
                'payment_attempts' => (int) $order->payment_attempts + 1,
                'last_payment_failed_at' => now(),
                'provider_payload' => is_object($checkout) ? json_decode(json_encode($checkout), true) : null,
            ])->save();

            SumUpCheckoutLogger::info('Spargel order: return-url verification — marked failed.', [
                'spargel_order_id' => $order->id,
                'checkout_id' => $order->sumup_checkout_id,
                'checkout_status' => $checkoutStatus ?: null,
                'has_failed_transaction' => $hasFailedTransaction,
                'payment_attempts' => (int) $order->payment_attempts,
            ]);

            return self::VERIFY_FAILED;
        }

        SumUpCheckoutLogger::info('Spargel order: return-url verification — still pending.', [
            'spargel_order_id' => $order->id,
            'checkout_id' => $order->sumup_checkout_id,
            'checkout_status' => $checkoutStatus ?: null,
        ]);

        return self::VERIFY_PENDING;
    }
}
