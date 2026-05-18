<?php

namespace App\Services;

use App\Enums\SpargelOrderStatus;
use App\Models\ProductWeekStock;
use App\Models\SpargelOrder;
use App\Services\Onboarding\TrialOrderService;
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
            'street' => 'Gotenstraße 19',
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
     * Weeks available for spargel orders.
     *
     * A Monday is "available" iff BOTH spargel products (white + green)
     * have a `ProductWeekStock` row for that week with non-zero remaining
     * grams (= planned − already-booked). Mondays already in the past
     * are excluded; same for the current Monday after morning cutoff
     * (kept until the day before by querying `>= today + 1 day`).
     *
     * Returns Mondays in ascending order. Empty when spargel config is
     * unset or the products aren't found — the form then renders the
     * empty-state hint and disables the submit button.
     *
     * @return Collection<int, array{monday: Carbon, remaining_white_g: int, remaining_green_g: int}>
     */
    public function availableWeeks(): Collection
    {
        $whiteId = config('spargel.white_product_id');
        $greenId = config('spargel.green_product_id');

        if ($whiteId === null || $greenId === null) {
            return collect();
        }

        // Tomorrow's date — keeps today's Monday in the list if today *is*
        // a Monday and there's still stock; excludes Mondays already gone.
        $earliestMonday = Carbon::tomorrow()->startOfWeek(Carbon::MONDAY);

        // Pull planned quantities + already-booked for both products in
        // one shot per side. ProductWeekStock is indexed by week_start_date.
        $whiteStocks = ProductWeekStock::query()
            ->where('product_id', (int) $whiteId)
            ->whereDate('week_start_date', '>=', $earliestMonday->toDateString())
            ->where('planned_quantity', '>', 0)
            ->orderBy('week_start_date')
            ->get(['week_start_date', 'planned_quantity']);

        $greenStocks = ProductWeekStock::query()
            ->where('product_id', (int) $greenId)
            ->whereDate('week_start_date', '>=', $earliestMonday->toDateString())
            ->where('planned_quantity', '>', 0)
            ->orderBy('week_start_date')
            ->get(['week_start_date', 'planned_quantity']);

        $whiteByMonday = $whiteStocks->keyBy(fn ($r) => $r->week_start_date->toDateString());
        $greenByMonday = $greenStocks->keyBy(fn ($r) => $r->week_start_date->toDateString());

        $mondays = $whiteByMonday->keys()->merge($greenByMonday->keys())->unique()->sort()->values();

        // Bulk-load existing booked grams per Monday so we don't N+1 in the
        // loop below.
        $bookedWhite = SpargelOrder::query()
            ->whereIn('expected_delivery_date', $mondays->all())
            ->whereNotIn('status', [SpargelOrderStatus::Cancelled, SpargelOrderStatus::Refunded])
            ->groupBy('expected_delivery_date')
            ->selectRaw('expected_delivery_date, SUM(quantity_white_grams) AS sum_white')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse((string) $r->expected_delivery_date)->toDateString());

        $bookedGreen = SpargelOrder::query()
            ->whereIn('expected_delivery_date', $mondays->all())
            ->whereNotIn('status', [SpargelOrderStatus::Cancelled, SpargelOrderStatus::Refunded])
            ->groupBy('expected_delivery_date')
            ->selectRaw('expected_delivery_date, SUM(quantity_green_grams) AS sum_green')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse((string) $r->expected_delivery_date)->toDateString());

        return $mondays
            ->map(function (string $monday) use ($whiteByMonday, $greenByMonday, $bookedWhite, $bookedGreen): array {
                $plannedWhite = (int) round((float) ($whiteByMonday[$monday]?->planned_quantity ?? 0));
                $plannedGreen = (int) round((float) ($greenByMonday[$monday]?->planned_quantity ?? 0));
                $usedWhite = (int) ($bookedWhite[$monday]?->sum_white ?? 0);
                $usedGreen = (int) ($bookedGreen[$monday]?->sum_green ?? 0);

                return [
                    'monday' => Carbon::parse($monday)->startOfDay(),
                    'remaining_white_g' => max(0, $plannedWhite - $usedWhite),
                    'remaining_green_g' => max(0, $plannedGreen - $usedGreen),
                ];
            })
            ->filter(fn (array $row): bool => $row['remaining_white_g'] > 0 || $row['remaining_green_g'] > 0)
            ->values();
    }

    /**
     * Remaining grams for the given Monday, per variety. Used by the
     * order-create validation to reject overbookings race-tightly: the
     * Request layer calls this just before persisting. Returns `null`
     * for either side if no plan exists.
     *
     * @return array{white: ?int, green: ?int}
     */
    public function remainingForWeek(Carbon $monday): array
    {
        $whiteId = config('spargel.white_product_id');
        $greenId = config('spargel.green_product_id');

        $remaining = fn (?int $productId): ?int => $productId === null
            ? null
            : $this->remainingForProductWeek($productId, $monday);

        return [
            'white' => $remaining($whiteId !== null ? (int) $whiteId : null),
            'green' => $remaining($greenId !== null ? (int) $greenId : null),
        ];
    }

    private function remainingForProductWeek(int $productId, Carbon $monday): ?int
    {
        $stock = ProductWeekStock::query()
            ->where('product_id', $productId)
            ->whereDate('week_start_date', $monday->toDateString())
            ->first(['planned_quantity']);

        if ($stock === null) {
            return null;
        }

        $column = ($productId === (int) config('spargel.white_product_id'))
            ? 'quantity_white_grams'
            : 'quantity_green_grams';

        $used = (int) SpargelOrder::query()
            ->whereDate('expected_delivery_date', $monday->toDateString())
            ->whereNotIn('status', [SpargelOrderStatus::Cancelled, SpargelOrderStatus::Refunded])
            ->sum($column);

        return max(0, (int) round((float) $stock->planned_quantity) - $used);
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
