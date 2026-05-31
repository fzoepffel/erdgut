<?php

namespace App\Services\Shop;

use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Admin\InventoryService;
use App\Services\SpargelOrderService;
use App\Support\PickupLocations;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Service for the anonymous, multi-product general shop.
 *
 * The shop generalizes {@see SpargelOrderService} from the
 * two hard-coded asparagus varieties to the full admin-managed catalog:
 * a guest picks a delivery date, sees only the products the inventory
 * says are available that day, fills a cart, and pays via a one-time
 * SumUp checkout — no User / Order / Subscription linkage. Everything
 * lives on a {@see ShopOrder} header plus {@see ShopOrderItem}
 * cart lines; the customer's email is the canonical identifier.
 *
 * Reuses the same {@see InventoryService} (date availability) and
 * {@see SumUpService} (anonymous one-time checkout) the spargel flow is
 * built on, so availability math and payment verification stay identical
 * across channels. Pricing comes straight from each `Product.unit_price`
 * (gross consumer price per canonical unit); only the delivery fee is
 * added on top, and only for delivery orders.
 */
class ShopOrderService
{
    public function __construct(private readonly SumUpService $sumUpService) {}

    /**
     * The delivery fee (EUR), added once per order for delivery orders.
     */
    public function deliveryFee(): float
    {
        return (float) config('shop.delivery_fee_eur', 2.90);
    }

    /**
     * Look up a pickup location by its key. Shared registry with spargel
     * so the two channels never offer different pickup points.
     *
     * @return array{name:string, street:string, postal_code:string, city:string}|null
     */
    public static function pickupLocation(?string $key): ?array
    {
        return PickupLocations::find($key);
    }

    /**
     * Dates with stock for *any* catalog product, within the horizon.
     *
     * A date `D` is offered iff at least one `visibleInCatalog()` product
     * has positive `availableOnDate(D)` (= active inflows whose freshness
     * window covers `D`, minus already-committed quantity across all
     * channels). Today and the past are excluded — same-day orders aren't
     * possible. No hardcoded weekday list: a date appears purely because
     * admin entered an inflow (directly or via a recurring schedule).
     *
     * @return Collection<int, array{date: Carbon, product_count: int}>
     */
    public function availableDates(?int $horizonWeeks = null): Collection
    {
        $horizonWeeks ??= (int) config('shop.horizon_weeks', 8);

        $products = Product::query()->visibleInCatalog()->get();
        if ($products->isEmpty()) {
            return collect();
        }

        $inventory = app(InventoryService::class);
        $start = Carbon::today()->addDay();
        $end = Carbon::today()->addDays(7 * $horizonWeeks);

        // Tally, per ISO date, how many products have positive stock.
        $counts = [];
        foreach ($products as $product) {
            $window = $inventory->availabilityWindow($product, $start, $end);
            foreach ($window as $iso => $remaining) {
                if ($remaining > 0) {
                    $counts[$iso] = ($counts[$iso] ?? 0) + 1;
                }
            }
        }

        ksort($counts);

        return collect($counts)
            ->map(fn (int $count, string $iso): array => [
                'date' => Carbon::parse($iso)->startOfDay(),
                'product_count' => $count,
            ])
            ->values();
    }

    /**
     * Products available on `$date`, each annotated with remaining stock.
     *
     * Only `visibleInCatalog()` products with positive `availableOnDate`
     * are returned, eager-loading the translations / producer / images
     * the storefront card needs. `remaining` is in canonical units; the
     * UI caps the per-product quantity picker at this value.
     *
     * @return Collection<int, array{product: Product, remaining: float}>
     */
    public function availableProductsForDate(Carbon $date): Collection
    {
        $inventory = app(InventoryService::class);

        return Product::query()
            ->visibleInCatalog()
            ->with(['translations', 'producer.translations', 'images'])
            ->get()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'remaining' => $inventory->availableOnDate($product, $date),
            ])
            ->filter(fn (array $row): bool => $row['remaining'] > 0)
            ->values();
    }

    /**
     * Products available *anywhere in the ISO week* containing `$date`,
     * each annotated with its availability on the selected day plus the
     * concrete in-week dates it can be ordered for.
     *
     * The storefront shows the whole week's catalog so a guest isn't
     * surprised by produce disappearing day-to-day. A product in stock on
     * the selected day gets an active quantity picker (`available_on_selected`
     * = true, `remaining` > 0); one in stock only on *other* days of the
     * week is surfaced greyed-out with its concrete `available_dates`, so the
     * guest can switch the delivery date instead of wondering where it went.
     * Products with no stock anywhere in the week are omitted entirely.
     *
     * The week is Monday–Sunday around `$date`, clamped so past days are
     * never offered. `remaining` is in canonical units; the UI caps the
     * per-product picker at this value.
     *
     * @return Collection<int, array{
     *     product: Product,
     *     remaining: float,
     *     available_on_selected: bool,
     *     available_dates: array<int, array{date: Carbon, remaining: float}>
     * }>
     */
    public function weeklyProductsForDate(Carbon $date): Collection
    {
        $inventory = app(InventoryService::class);

        $selectedIso = $date->copy()->startOfDay()->toDateString();
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);

        // Never offer past days — clamp the window's start to tomorrow.
        $earliest = Carbon::today()->addDay();
        $from = $weekStart->lt($earliest) ? $earliest : $weekStart;

        if ($from->gt($weekEnd)) {
            return collect();
        }

        return Product::query()
            ->visibleInCatalog()
            ->with(['translations', 'producer.translations', 'images'])
            ->get()
            ->map(function (Product $product) use ($inventory, $from, $weekEnd, $selectedIso): array {
                $window = $inventory->availabilityWindow($product, $from, $weekEnd);

                $dates = [];
                foreach ($window as $iso => $remaining) {
                    if ($remaining > 0) {
                        $dates[] = [
                            'date' => Carbon::parse($iso)->startOfDay(),
                            'remaining' => $remaining,
                        ];
                    }
                }

                $selectedRemaining = (float) ($window[$selectedIso] ?? 0.0);

                return [
                    'product' => $product,
                    'remaining' => $selectedRemaining,
                    'available_on_selected' => $selectedRemaining > 0,
                    'available_dates' => $dates,
                ];
            })
            ->filter(fn (array $row): bool => $row['available_dates'] !== [])
            // Orderable-today products first; everything else keeps catalog order.
            ->sortByDesc(fn (array $row): bool => $row['available_on_selected'])
            ->values();
    }

    /**
     * Price a cart. Each line is `['product_id' => int, 'quantity' =>
     * float]` (quantity in canonical units). Resolves each product,
     * snapshots its unit + per-canonical-unit price, computes the line
     * total, and adds the delivery fee for delivery orders. Returns the
     * resolved lines plus the grand totals so the form preview, the
     * Request validation, the order create, and the SumUp checkout can
     * never disagree.
     *
     * Unknown / not-visible products and non-positive quantities are
     * dropped — the Request layer rejects those before this is trusted
     * for persistence, but quote() stays defensive for the live preview.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|float}>  $cartLines
     * @return array{
     *     lines: array<int, array{product: Product, quantity: float, unit: string, unit_price_eur: float, line_total_eur: float}>,
     *     items_total_eur: float,
     *     delivery_fee_eur: float,
     *     total_amount_eur: float
     * }
     */
    public function quote(array $cartLines, string $fulfillmentMode): array
    {
        $productIds = collect($cartLines)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $products = Product::query()
            ->visibleInCatalog()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $lines = [];
        $itemsTotal = 0.0;

        foreach ($cartLines as $line) {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $quantity = round((float) ($line['quantity'] ?? 0), 4);

            if ($product === null || $quantity <= 0) {
                continue;
            }

            $unitPrice = (float) $product->unit_price;
            $lineTotal = round($unitPrice * $quantity, 2);
            $itemsTotal += $lineTotal;

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit' => $product->unit->value,
                'unit_price_eur' => $unitPrice,
                'line_total_eur' => $lineTotal,
            ];
        }

        $itemsTotal = round($itemsTotal, 2);
        $deliveryFee = $fulfillmentMode === 'delivery' ? $this->deliveryFee() : 0.0;

        return [
            'lines' => $lines,
            'items_total_eur' => $itemsTotal,
            'delivery_fee_eur' => $deliveryFee,
            'total_amount_eur' => round($itemsTotal + $deliveryFee, 2),
        ];
    }

    /**
     * Persist a shop order with its cart lines, race-tight against stock.
     *
     * Re-validates each line's quantity against
     * {@see InventoryService::availableOnDate} *inside* the transaction
     * (row-locked) so two near-simultaneous orders can't both succeed
     * past the remaining stock. Throws {@see InsufficientStockException}
     * naming the first over-booked product; the controller surfaces it.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InsufficientStockException
     */
    public function createOrder(array $payload): ShopOrder
    {
        $fulfillmentMode = (string) ($payload['fulfillment_mode'] ?? 'pickup');
        $cartLines = (array) ($payload['items'] ?? []);

        $expectedDeliveryDate = $payload['expected_delivery_date'] ?? null;
        if ($expectedDeliveryDate === null) {
            throw new \InvalidArgumentException('expected_delivery_date is required.');
        }
        $deliveryDate = Carbon::parse((string) $expectedDeliveryDate)->startOfDay();

        $quote = $this->quote($cartLines, $fulfillmentMode);
        if ($quote['lines'] === []) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        return DB::transaction(function () use ($payload, $fulfillmentMode, $deliveryDate, $quote): ShopOrder {
            $inventory = app(InventoryService::class);

            // Re-check every line against live availability before we
            // commit. Lock the product rows so a concurrent order can't
            // read the same "remaining" and double-book.
            foreach ($quote['lines'] as $line) {
                /** @var Product $product */
                $product = $line['product'];
                Product::query()->lockForUpdate()->find($product->id);

                $remaining = $inventory->availableOnDate($product, $deliveryDate);
                if ($line['quantity'] > $remaining + 1e-6) {
                    throw new InsufficientStockException(
                        $product,
                        requested: $line['quantity'],
                        remaining: max(0.0, $remaining),
                    );
                }
            }

            $order = ShopOrder::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'fulfillment_mode' => $fulfillmentMode,
                'expected_delivery_date' => $deliveryDate->toDateString(),
                'pickup_location' => $fulfillmentMode === 'pickup'
                    ? ($payload['pickup_location'] ?? null)
                    : null,
                'delivery_street' => $fulfillmentMode === 'delivery' ? ($payload['delivery_street'] ?? null) : null,
                'delivery_postal_code' => $fulfillmentMode === 'delivery' ? ($payload['delivery_postal_code'] ?? null) : null,
                'delivery_city' => $fulfillmentMode === 'delivery' ? ($payload['delivery_city'] ?? null) : null,
                'delivery_notes' => $payload['delivery_notes'] ?? null,
                'items_total_eur' => $quote['items_total_eur'],
                'delivery_fee_eur' => $quote['delivery_fee_eur'],
                'total_amount_eur' => $quote['total_amount_eur'],
                'payment_method' => $payload['payment_method'] ?? 'online',
                'status' => ShopOrderStatus::PendingPayment,
            ]);

            foreach ($quote['lines'] as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price_snapshot_eur' => $line['unit_price_eur'],
                    'line_total_eur' => $line['line_total_eur'],
                ]);
            }

            return $order;
        });
    }

    public function initiateCheckout(ShopOrder $order, string $returnUrl): object
    {
        $reference = 'shop-'.$order->id.'-'.now()->timestamp;

        $checkout = $this->sumUpService->createOneTimeCheckout(
            amount: (float) $order->total_amount_eur,
            checkoutReference: $reference,
            description: 'Erdgut Shop-Bestellung',
            customerId: null,
            extraParams: ['redirect_url' => $returnUrl],
        );

        $checkoutId = (string) (data_get($checkout, 'id') ?? '');

        if ($checkoutId !== '') {
            $order->forceFill(['sumup_checkout_id' => $checkoutId])->save();
        }

        SumUpCheckoutLogger::info('Shop order: SumUp one-time checkout created.', [
            'shop_order_id' => $order->id,
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
     * Server-side verification after SumUp redirects the browser back to
     * our return URL. Never trust the redirect itself; ask SumUp.
     *
     * Returns one of three outcomes:
     *  - VERIFY_PAID    → checkout PAID; order moved to Paid.
     *  - VERIFY_FAILED  → checkout/transaction FAILED or EXPIRED; order
     *                     moved to Failed (customer can retry).
     *  - VERIFY_PENDING → still pending OR verification itself failed
     *                     (network etc.); order untouched.
     *
     * Idempotent — already-Paid / already-Failed return directly without
     * hitting SumUp. Mirrors the proven spargel verification exactly.
     */
    public function verifyPaymentWithProvider(ShopOrder $order): string
    {
        if ($order->status === ShopOrderStatus::Paid) {
            return self::VERIFY_PAID;
        }
        if ($order->status === ShopOrderStatus::Failed) {
            return self::VERIFY_FAILED;
        }
        if ($order->status !== ShopOrderStatus::PendingPayment) {
            return self::VERIFY_PENDING;
        }
        if ($order->sumup_checkout_id === null) {
            return self::VERIFY_PENDING;
        }

        try {
            $checkout = $this->sumUpService->getCheckout($order->sumup_checkout_id);
        } catch (Throwable $e) {
            SumUpCheckoutLogger::warning('Shop order: failed to verify checkout with SumUp.', [
                'shop_order_id' => $order->id,
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
                'status' => ShopOrderStatus::Paid,
                'paid_at' => now(),
                'sumup_payment_id' => $paymentId !== '' ? $paymentId : null,
                'provider_payload' => is_object($checkout) ? json_decode(json_encode($checkout), true) : null,
            ])->save();

            SumUpCheckoutLogger::info('Shop order: return-url verification — marked paid.', [
                'shop_order_id' => $order->id,
                'checkout_id' => $order->sumup_checkout_id,
                'sumup_payment_id' => $paymentId !== '' ? $paymentId : null,
            ]);

            return self::VERIFY_PAID;
        }

        $transactionStatuses = array_map(
            fn ($tx) => strtoupper((string) data_get($tx, 'status', '')),
            $transactions,
        );
        $hasFailedTransaction = in_array('FAILED', $transactionStatuses, true);
        $checkoutFailed = in_array($checkoutStatus, ['FAILED', 'EXPIRED'], true);

        if ($checkoutFailed || $hasFailedTransaction) {
            $order->forceFill([
                'status' => ShopOrderStatus::Failed,
                'payment_attempts' => (int) $order->payment_attempts + 1,
                'last_payment_failed_at' => now(),
                'provider_payload' => is_object($checkout) ? json_decode(json_encode($checkout), true) : null,
            ])->save();

            SumUpCheckoutLogger::info('Shop order: return-url verification — marked failed.', [
                'shop_order_id' => $order->id,
                'checkout_id' => $order->sumup_checkout_id,
                'checkout_status' => $checkoutStatus ?: null,
                'has_failed_transaction' => $hasFailedTransaction,
                'payment_attempts' => (int) $order->payment_attempts,
            ]);

            return self::VERIFY_FAILED;
        }

        SumUpCheckoutLogger::info('Shop order: return-url verification — still pending.', [
            'shop_order_id' => $order->id,
            'checkout_id' => $order->sumup_checkout_id,
            'checkout_status' => $checkoutStatus ?: null,
        ]);

        return self::VERIFY_PENDING;
    }
}
