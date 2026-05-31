<?php

namespace App\Services;

use App\Enums\SpargelOrderStatus;
use App\Models\Product;
use App\Models\SpargelOrder;
use App\Services\Admin\InventoryService;
use App\Services\Onboarding\TrialOrderService;
use App\Support\PickupLocations;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * The pickup locations a customer can choose from. Sourced from the
     * shared {@see PickupLocations} registry so the spargel flow and the
     * general shop can never drift to different location lists. Each
     * entry's key is what we persist in `pickup_location`; the rest is
     * the surface info (name + address) shown on the confirmation screen,
     * the email, and the admin list.
     *
     * The order page (form) intentionally shows only the address — the
     * person's name appears later (post-submit) so the form stays
     * neutral about who the customer will physically meet.
     *
     * @var array<string, array{name:string, street:string, postal_code:string, city:string}>
     */
    public const PICKUP_LOCATIONS = PickupLocations::LOCATIONS;

    /**
     * Look up a pickup location by its key. Returns null if the key
     * isn't a known location (defensive against a stale DB row).
     *
     * @return array{name:string, street:string, postal_code:string, city:string}|null
     */
    public static function pickupLocation(?string $key): ?array
    {
        return PickupLocations::find($key);
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
     * Dates available for spargel orders.
     *
     * A date `D` is available iff BOTH spargel products have positive
     * `availableOnDate(D)` (= active inflows whose freshness window
     * covers `D`, minus already-booked grams). Dates in the past and
     * today are excluded — same-day orders aren't possible. Picker
     * walks `[today+1, today + $horizonWeeks weeks]`.
     *
     * No hardcoded weekday list — Mondays, Wednesdays, or any other
     * weekday appears purely because admin entered an inflow there
     * (directly or via a recurring schedule). Empty when spargel
     * config is unset.
     *
     * @return Collection<int, array{date: Carbon, remaining_white_g: int, remaining_green_g: int}>
     */
    public function availableDates(int $horizonWeeks = 8): Collection
    {
        $whiteId = config('spargel.white_product_id');
        $greenId = config('spargel.green_product_id');

        if ($whiteId === null || $greenId === null) {
            return collect();
        }

        $white = Product::query()->find((int) $whiteId);
        $green = Product::query()->find((int) $greenId);

        if ($white === null || $green === null) {
            return collect();
        }

        $inventory = app(InventoryService::class);
        $start = Carbon::today()->addDay();
        $end = Carbon::today()->addDays(7 * $horizonWeeks);

        $whiteWindow = $inventory->availabilityWindow($white, $start, $end);
        $greenWindow = $inventory->availabilityWindow($green, $start, $end);

        $rows = collect();
        foreach ($whiteWindow as $iso => $remainingWhite) {
            $remainingGreen = $greenWindow[$iso] ?? 0.0;
            if ($remainingWhite <= 0 && $remainingGreen <= 0) {
                continue;
            }
            // Both varieties must be available — order form requires
            // submitting a date that supports either one *and* lets
            // single-variety orders go through. Surface a date so long
            // as at least one variety is non-empty; the per-date cap
            // re-validates per variety at submit.
            $rows->push([
                'date' => Carbon::parse($iso)->startOfDay(),
                'remaining_white_g' => (int) round($remainingWhite),
                'remaining_green_g' => (int) round($remainingGreen),
            ]);
        }

        return $rows->values();
    }

    /**
     * Remaining grams for the given date, per variety. Used by the
     * order-create validation to reject overbookings race-tightly:
     * the Request layer calls this just before persisting. Returns
     * `null` for either side when the corresponding spargel product
     * isn't configured.
     *
     * @return array{white: ?int, green: ?int}
     */
    public function remainingForDate(Carbon $date): array
    {
        $whiteId = config('spargel.white_product_id');
        $greenId = config('spargel.green_product_id');

        return [
            'white' => $this->remainingForProductDate($whiteId !== null ? (int) $whiteId : null, $date),
            'green' => $this->remainingForProductDate($greenId !== null ? (int) $greenId : null, $date),
        ];
    }

    private function remainingForProductDate(?int $productId, Carbon $date): ?int
    {
        if ($productId === null) {
            return null;
        }

        $product = Product::query()->find($productId);
        if ($product === null) {
            return null;
        }

        return (int) round(app(InventoryService::class)->availableOnDate($product, $date));
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

        // Customer-picked delivery Monday (Phase 3, 2026-05-09). The
        // form constrains the picker to weeks with stock; the Request
        // layer re-validates the chosen date against availableWeeks()
        // immediately before this call so we never persist a Monday
        // that's no longer in the menu.
        $expectedDeliveryDate = $payload['expected_delivery_date'] ?? null;
        if ($expectedDeliveryDate === null) {
            throw new \InvalidArgumentException('expected_delivery_date is required.');
        }

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
            'expected_delivery_date' => Carbon::parse((string) $expectedDeliveryDate)->toDateString(),
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

        $transactionStatuses = array_map(
            fn ($tx) => strtoupper((string) data_get($tx, 'status', '')),
            $transactions,
        );

        // SumUp's checkout `status` can still read PENDING for a short window
        // after the browser is redirected back, even though the payment has
        // already settled — the authoritative "money moved" signal is a
        // SUCCESSFUL transaction in the checkout's `transactions[]`. Treat
        // either as paid so customers aren't stuck on "verifying" while the
        // SumUp dashboard already shows the payment.
        $hasSuccessfulTransaction = in_array('SUCCESSFUL', $transactionStatuses, true);

        if ($checkoutStatus === 'PAID' || $hasSuccessfulTransaction) {
            $paymentId = (string) (
                data_get($transactions, '0.id')
                ?? data_get($transactions, '0.transaction_id')
                ?? ''
            );

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
