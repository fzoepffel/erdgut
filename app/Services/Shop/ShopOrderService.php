<?php

namespace App\Services\Shop;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStockAudience;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
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
use Illuminate\Support\Facades\URL;
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
    public function __construct(
        private readonly SumUpService $sumUpService,
        private readonly CouponService $couponService,
        private readonly PriceResolver $priceResolver,
    ) {}

    /**
     * Normalize the storefront's two quantity maps into clean cart lines —
     * the single shape {@see quote} and {@see createOrder} expect. The default
     * grade posts `quantities[productId]`; named grades post
     * `variant_quantities[variantId]` (product resolved from the variant).
     * Shared by the checkout request, the quote endpoint and the coupon
     * preview so they can never disagree on what's in the cart.
     *
     * @param  array<int|string, mixed>  $quantities
     * @param  array<int|string, mixed>  $variantQuantities
     * @return array<int, array{product_id: int, quantity: float, variant_id: int|null}>
     */
    public static function normalizeCartItems(array $quantities, array $variantQuantities): array
    {
        $items = [];

        foreach ($quantities as $productId => $quantity) {
            $qty = (float) $quantity;
            if ($qty > 0) {
                $items[] = ['product_id' => (int) $productId, 'quantity' => $qty, 'variant_id' => null];
            }
        }

        $variantQuantities = array_filter($variantQuantities, fn ($q): bool => (float) $q > 0);
        if ($variantQuantities !== []) {
            $variants = ProductVariant::query()
                ->whereIn('id', array_map('intval', array_keys($variantQuantities)))
                ->get(['id', 'product_id'])
                ->keyBy('id');

            foreach ($variantQuantities as $variantId => $quantity) {
                $variant = $variants->get((int) $variantId);
                if ($variant !== null) {
                    $items[] = [
                        'product_id' => (int) $variant->product_id,
                        'quantity' => (float) $quantity,
                        'variant_id' => (int) $variantId,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Whether the checkout is "done" from the customer's point of view —
     * the confirmation page's happy state. True for online orders that are
     * Paid, and for pay-in-person orders (which stay PendingPayment until
     * the cash/card hand-over). Shared so the box-waitlist prompt and the
     * confirmation screen never disagree on what counts as complete.
     */
    public static function isCompletedCheckout(ShopOrder $order): bool
    {
        return $order->status === ShopOrderStatus::Paid
            || ($order->payment_method === 'in_person' && $order->status === ShopOrderStatus::PendingPayment);
    }

    /**
     * A short, editorial pick of products that are "in season now" — i.e.
     * have stock on at least one date in a two-week window around today
     * (today−7 … today+7). Only products that carry both an image and a
     * sufficiently long description qualify, so the homepage feature never
     * renders a thin or image-less card. Ordered by how many days in the
     * window they're available (most-available first), capped at $limit.
     *
     * Returns an empty collection when nothing qualifies, so the caller can
     * hide the section cleanly.
     *
     * @return Collection<int, Product>
     */
    public function featuredInSeasonProducts(int $limit = 3, int $minDescriptionChars = 140): Collection
    {
        $products = Product::query()
            ->visibleInCatalog()
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->with(['translations', 'producer.translations'])
            ->get();

        if ($products->isEmpty()) {
            return collect();
        }

        $inventory = app(InventoryService::class);
        $from = Carbon::today()->subDays(7);
        $to = Carbon::today()->addDays(7);
        $matrix = $inventory->availabilityMatrix($products, $from, $to);

        $locale = app()->getLocale();

        $availableDays = static fn (Product $p): int => collect($matrix[$p->id] ?? [])
            ->filter(fn ($remaining): bool => $remaining > 0)
            ->count();

        return $products
            ->filter(function (Product $p) use ($availableDays, $minDescriptionChars, $locale): bool {
                if ($availableDays($p) === 0) {
                    return false;
                }

                return mb_strlen(trim($p->localizedTasteProfile($locale))) >= $minDescriptionChars;
            })
            ->sortByDesc($availableDays)
            ->take($limit)
            ->values();
    }

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
     * Signed, non-expiring tracking-page URL for an order. Shared by the
     * confirmation and window-set emails so both link to the same place.
     */
    public static function trackUrl(ShopOrder $order): string
    {
        return URL::signedRoute('public.shop.track', ['shopOrder' => $order->id]);
    }

    /**
     * Signed, non-expiring PDF-receipt download URL. The route is public but
     * the signature prevents guessing arbitrary order IDs.
     */
    public static function receiptUrl(ShopOrder $order): string
    {
        return URL::signedRoute('public.shop.receipt', ['shopOrder' => $order->id]);
    }

    /**
     * Compute the per-rate VAT breakdown for an order.
     *
     * Groups the VAT contained in each gross line item by rate, adds the
     * delivery-fee VAT (if any), and returns a Collection keyed by integer
     * rate percent (e.g. 7 → 0.79, 19 → 1.60), sorted ascending by rate.
     * All views that display VAT call this so the logic lives in one place.
     *
     * @return Collection<int, float>
     */
    public static function vatByRate(ShopOrder $order): Collection
    {
        $byRate = $order->items
            ->groupBy('vat_rate_snapshot_percent')
            ->map(fn ($group) => round($group->sum(fn ($item) => (float) $item->vat_amount_eur), 2));

        if ((float) $order->delivery_fee_vat_amount_eur > 0) {
            $r = (int) $order->delivery_fee_vat_rate_percent;
            $byRate[$r] = round(($byRate[$r] ?? 0) + (float) $order->delivery_fee_vat_amount_eur, 2);
        }

        return $byRate->sortKeys();
    }

    /**
     * Customer-facing contact phone, which differs per fulfillment context:
     * deliveries and Karl-Kunger-Straße pickups share one line, the
     * Gotenstraße pickup point has its own. Used on the tracking page and
     * in the confirmation / window-set emails.
     */
    public const CONTACT_PHONE_DEFAULT = '+33 6 36 31 55 15';

    /**
     * @var array<string, string>
     */
    private const CONTACT_PHONE_BY_PICKUP = [
        'karl_kunger' => '+33 6 36 31 55 15',
        'gotenstrasse' => '+33 7 83 38 85 93',
    ];

    public static function contactPhone(ShopOrder $order): string
    {
        if ($order->fulfillment_mode === 'pickup'
            && isset(self::CONTACT_PHONE_BY_PICKUP[$order->pickup_location])) {
            return self::CONTACT_PHONE_BY_PICKUP[$order->pickup_location];
        }

        return self::CONTACT_PHONE_DEFAULT;
    }

    /**
     * Full availability index in a single scan pass.
     *
     * Returns two collections built together so we only call
     * `availabilityWindow` once per product:
     *
     *  - `dates`:    every future date with ≥1 product in stock, each carrying
     *                `product_count` (total) and `product_ids` (int[]).
     *  - `products`: every catalog product that has at least one date in stock,
     *                carrying `id`, `name`, `producer` (string), `date_count`.
     *
     * @return array{
     *     dates: Collection<int, array{date: Carbon, product_count: int, product_ids: int[]}>,
     *     products: Collection<int, array{id: int, name: string, producer: string, date_count: int}>,
     * }
     */
    public function availabilityIndex(?int $horizonWeeks = null): array
    {
        $horizonWeeks ??= (int) config('shop.horizon_weeks', 8);

        $products = Product::query()->visibleInCatalog()->with(['translations', 'producer.translations'])->get();

        if ($products->isEmpty()) {
            return ['dates' => collect(), 'products' => collect()];
        }

        $inventory = app(InventoryService::class);
        $closures = app(ShopClosureService::class);
        $start = Carbon::today()->addDay();
        $end = Carbon::today()->addDays(7 * $horizonWeeks);

        // One bulk pass over all products × the whole horizon (a few queries),
        // then accumulate per-date product IDs and per-product date counts.
        $matrix = $inventory->availabilityMatrix($products, $start, $end);

        $dateProductIds = [];    // iso => int[]
        $productDateCount = [];  // product_id => int

        foreach ($products as $product) {
            $datesForProduct = 0;

            foreach ($matrix[$product->id] ?? [] as $iso => $remaining) {
                if ($remaining > 0 && ! $closures->isClosed(Carbon::parse($iso))) {
                    $dateProductIds[$iso][] = $product->id;
                    $datesForProduct++;
                }
            }

            $productDateCount[$product->id] = $datesForProduct;
        }

        ksort($dateProductIds);

        $dates = collect($dateProductIds)
            ->map(fn (array $ids, string $iso): array => [
                'date' => Carbon::parse($iso)->startOfDay(),
                'product_count' => count($ids),
                'product_ids' => $ids,
            ])
            ->values();

        $catalogProducts = $products
            ->filter(fn (Product $p): bool => ($productDateCount[$p->id] ?? 0) > 0)
            ->map(fn (Product $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'producer' => $p->producer?->name ?? '',
                'date_count' => $productDateCount[$p->id] ?? 0,
            ])
            ->values();

        return ['dates' => $dates, 'products' => $catalogProducts];
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
        $closures = app(ShopClosureService::class);
        $start = Carbon::today()->addDay();
        $end = Carbon::today()->addDays(7 * $horizonWeeks);

        $matrix = $inventory->availabilityMatrix($products, $start, $end);

        // Tally, per ISO date, how many products have positive stock.
        $counts = [];
        foreach ($products as $product) {
            foreach ($matrix[$product->id] ?? [] as $iso => $remaining) {
                if ($remaining > 0 && ! $closures->isClosed(Carbon::parse($iso))) {
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
    public function availableProductsForDate(
        Carbon $date,
        ShopStockAudience $audience = ShopStockAudience::Consumer,
    ): Collection {
        $inventory = app(InventoryService::class);

        $products = Product::query()
            ->visibleInCatalog()
            ->with(['translations', 'producer.translations', 'images'])
            ->get();

        $day = $date->copy()->startOfDay();
        $matrix = $inventory->availabilityMatrix($products, $day, $day, $audience);
        $iso = $day->toDateString();

        return $products
            ->map(fn (Product $product): array => [
                'product' => $product,
                'remaining' => (float) ($matrix[$product->id][$iso] ?? 0.0),
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
        $closures = app(ShopClosureService::class);

        $selectedIso = $date->copy()->startOfDay()->toDateString();
        $selectedClosed = $closures->isClosed($date);
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);

        // Never offer past days — clamp the window's start to tomorrow.
        $earliest = Carbon::today()->addDay();
        $from = $weekStart->lt($earliest) ? $earliest : $weekStart;

        if ($from->gt($weekEnd)) {
            return collect();
        }

        $products = Product::query()
            ->visibleInCatalog()
            ->with(['translations', 'producer.translations', 'images', 'priceRules', 'variants'])
            ->get();

        $matrix = $inventory->availabilityMatrix($products, $from, $weekEnd);

        return $products
            ->map(function (Product $product) use ($matrix, $selectedIso, $selectedClosed, $closures): array {
                $window = $matrix[$product->id] ?? [];
                ksort($window);

                $dates = [];
                foreach ($window as $iso => $remaining) {
                    if ($remaining > 0 && ! $closures->isClosed(Carbon::parse($iso))) {
                        $dates[] = [
                            'date' => Carbon::parse($iso)->startOfDay(),
                            'remaining' => $remaining,
                        ];
                    }
                }

                $selectedRemaining = $selectedClosed
                    ? 0.0
                    : (float) ($window[$selectedIso] ?? 0.0);

                return [
                    'product' => $product,
                    'remaining' => $selectedRemaining,
                    'available_on_selected' => ! $selectedClosed && $selectedRemaining > 0,
                    'available_dates' => $dates,
                ];
            })
            ->filter(fn (array $row): bool => $row['available_dates'] !== [])
            // Orderable-today products first; everything else keeps catalog order.
            ->sortByDesc(fn (array $row): bool => $row['available_on_selected'])
            ->values();
    }

    /**
     * Availability call-to-action for a single catalog product, used on the
     * shop's product- and producer-detail pages so a guest can jump straight
     * back into the storefront. Three mutually exclusive states:
     *
     *  - `this_week`: orderable somewhere in the current ISO week → link to
     *    the shop with the earliest in-week date preselected.
     *  - `future`   : not this week, but a later inflow exists within the
     *    horizon → "available again from {date}", linking to the shop with
     *    that date preselected.
     *  - `none`     : no upcoming inflow within the horizon → not available;
     *    no date, link falls back to the shop landing.
     *
     * Scans day-by-day from tomorrow and returns on the first date with
     * positive availability, so available products cost only a few day
     * lookups; only a genuinely unavailable product walks the full horizon.
     *
     * @return array{state: string, date: ?Carbon, url: string}
     */
    public function productAvailabilityCta(Product $product, ?int $horizonWeeks = null): array
    {
        $horizonWeeks ??= (int) config('shop.horizon_weeks', 8);

        $inventory = app(InventoryService::class);
        $closures = app(ShopClosureService::class);

        $cursor = Carbon::today()->addDay();
        $twoWeekEnd = Carbon::today()->endOfWeek(Carbon::SUNDAY)->addWeek();
        $horizonEnd = Carbon::today()->addDays(7 * $horizonWeeks);

        while ($cursor->lte($horizonEnd)) {
            if (! $closures->isClosed($cursor) && $inventory->availableOnDate($product, $cursor) > 0) {
                return [
                    'state' => $cursor->lte($twoWeekEnd) ? 'this_week' : 'future',
                    'date' => $cursor->copy()->startOfDay(),
                    'url' => route('public.shop.index', ['date' => $cursor->toDateString()]),
                ];
            }

            $cursor->addDay();
        }

        return [
            'state' => 'none',
            'date' => null,
            'url' => route('public.shop.index'),
        ];
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
     * A coupon code may be supplied to apply a discount to the *product
     * subtotal only* (never the delivery fee). The coupon is validated
     * against the resolved subtotal and the customer email; the resolved
     * coupon, granted discount, and any rejection reason are returned
     * alongside the totals so the preview, validation, and persist paths
     * all agree. An invalid coupon simply yields a zero discount plus a
     * `coupon_error` reason — it never blocks the quote.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|float}>  $cartLines
     * @return array{
     *     lines: array<int, array{product: Product, quantity: float, unit: string, unit_price_eur: float, line_total_eur: float, vat_rate_percent: int, vat_amount_eur: float}>,
     *     items_total_eur: float,
     *     delivery_fee_eur: float,
     *     delivery_fee_vat_rate_percent: int,
     *     delivery_fee_vat_amount_eur: float,
     *     discount_eur: float,
     *     coupon: Coupon|null,
     *     coupon_code: string|null,
     *     coupon_error: string|null,
     *     coupon_error_params: array<string, scalar>,
     *     total_amount_eur: float
     * }
     */
    public function quote(
        array $cartLines,
        string $fulfillmentMode,
        ?string $couponCode = null,
        ?string $email = null,
        ?Carbon $deliveryDate = null,
        CustomerType $customerType = CustomerType::Private,
    ): array {
        $productIds = collect($cartLines)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $products = Product::query()
            ->visibleInCatalog()
            ->with(['priceRules', 'variants'])
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

            // Optional grade (variant). A line referencing an unknown or
            // inactive grade is dropped — the Request layer rejects those
            // before persist, but quote() stays defensive for the preview.
            $variantId = isset($line['variant_id']) && $line['variant_id'] !== null
                ? (int) $line['variant_id']
                : null;
            $variant = $variantId !== null
                ? $product->variants->firstWhere('id', $variantId)
                : null;
            if ($variantId !== null && ($variant === null || ! $variant->is_active)) {
                continue;
            }

            // Effective price for this date / customer / quantity / grade.
            // Falls back to the grade's (or product's) base price when no rule
            // applies.
            $resolved = $this->priceResolver->resolve($product, $deliveryDate, $customerType, $quantity, $variantId, $variant);
            $unitPrice = $resolved->unitPriceEur;
            $lineTotal = round($unitPrice * $quantity, 2);
            $itemsTotal += $lineTotal;

            // VAT contained in the gross line total (prices are gross):
            // vat = gross * rate / (100 + rate). Snapshotted per line.
            $vatRate = (int) ($product->vat_rate_percent ?? 7);
            $vatAmount = round($lineTotal * $vatRate / (100 + $vatRate), 2);

            $lines[] = [
                'product' => $product,
                'variant_id' => $variantId,
                'variant_name_snapshot' => $variant?->localizedName(),
                'quantity' => $quantity,
                'unit' => $product->unit->value,
                'unit_price_eur' => $unitPrice,
                'price_rule_id' => $resolved->ruleId,
                'line_total_eur' => $lineTotal,
                'vat_rate_percent' => $vatRate,
                'vat_amount_eur' => $vatAmount,
            ];
        }

        $itemsTotal = round($itemsTotal, 2);
        $deliveryFee = $fulfillmentMode === 'delivery' ? $this->deliveryFee() : 0.0;

        // The delivery fee is a VAT-bearing service. It follows the dominant
        // rate of the basket (most line items by count), falling back to the
        // configured default for an empty cart.
        $deliveryVatRate = $lines !== []
            ? (int) collect($lines)
                ->groupBy('vat_rate_percent')
                ->sortByDesc(fn ($group) => $group->count())
                ->keys()
                ->first()
            : (int) config('shop.vat_rate_percent', 7);

        $deliveryVatAmount = $deliveryFee > 0
            ? round($deliveryFee * $deliveryVatRate / (100 + $deliveryVatRate), 2)
            : 0.0;

        // Resolve an optional coupon against the gross product subtotal (and
        // the delivery fee, which only a free-delivery coupon touches). An
        // invalid coupon yields zero discount + a reason.
        $discount = 0.0;
        $coupon = null;
        $couponError = null;
        $couponErrorParams = [];
        $normalizedCode = $couponCode !== null ? trim($couponCode) : '';

        if ($normalizedCode !== '') {
            $result = $this->couponService->validateForCheckout(
                $normalizedCode,
                (string) ($email ?? ''),
                $itemsTotal,
                $deliveryFee,
            );

            if ($result->accepted) {
                $discount = $result->discountEur;
                $coupon = $result->coupon;
            } else {
                $couponError = $result->reason;
                $couponErrorParams = $result->params;
            }
        }

        $total = round($itemsTotal - $discount + $deliveryFee, 2);

        return [
            'lines' => $lines,
            'items_total_eur' => $itemsTotal,
            'delivery_fee_eur' => $deliveryFee,
            'delivery_fee_vat_rate_percent' => $deliveryVatRate,
            'delivery_fee_vat_amount_eur' => $deliveryVatAmount,
            'discount_eur' => round($discount, 2),
            'coupon' => $coupon,
            'coupon_code' => $coupon?->code,
            'coupon_error' => $couponError,
            'coupon_error_params' => $couponErrorParams,
            'total_amount_eur' => $total,
        ];
    }

    /**
     * Apply admin-entered per-line gross totals and VAT rates, plus delivery
     * fee and delivery VAT. Only used for {@see OrderSource::Admin} orders.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyAdminLinePricing(array $quote, array $payload): array
    {
        $payloadItems = (array) ($payload['items'] ?? []);
        $lines = $quote['lines'];
        $itemsTotal = 0.0;

        foreach ($lines as $index => $line) {
            $payloadLine = $payloadItems[$index] ?? [];
            $lineTotal = round((float) ($payloadLine['line_total_eur'] ?? $line['line_total_eur']), 2);
            $vatRate = (int) ($payloadLine['vat_rate_percent'] ?? $line['vat_rate_percent']);
            $quantity = (float) $line['quantity'];

            $lines[$index] = array_merge($line, [
                'line_total_eur' => $lineTotal,
                'vat_rate_percent' => $vatRate,
                'unit_price_eur' => $quantity > 0 ? round($lineTotal / $quantity, 4) : 0.0,
                'vat_amount_eur' => self::vatAmountFromGross($lineTotal, $vatRate),
                // Manual admin pricing is authoritative; no rule provenance.
                'price_rule_id' => null,
            ]);
            $itemsTotal += $lineTotal;
        }

        $quote['lines'] = $lines;
        $quote['items_total_eur'] = round($itemsTotal, 2);

        $fulfillmentMode = (string) ($payload['fulfillment_mode'] ?? 'pickup');
        if ($fulfillmentMode === 'delivery') {
            $deliveryFee = round((float) ($payload['delivery_fee_eur'] ?? $quote['delivery_fee_eur']), 2);
            $deliveryVatRate = (int) ($payload['delivery_fee_vat_rate_percent'] ?? $quote['delivery_fee_vat_rate_percent']);
        } else {
            $deliveryFee = 0.0;
            $deliveryVatRate = (int) $quote['delivery_fee_vat_rate_percent'];
        }

        $quote['delivery_fee_eur'] = $deliveryFee;
        $quote['delivery_fee_vat_rate_percent'] = $deliveryVatRate;
        $quote['delivery_fee_vat_amount_eur'] = $deliveryFee > 0
            ? self::vatAmountFromGross($deliveryFee, $deliveryVatRate)
            : 0.0;

        $quote['total_amount_eur'] = round(
            (float) $quote['items_total_eur'] - (float) $quote['discount_eur'] + (float) $quote['delivery_fee_eur'],
            2
        );

        return $quote;
    }

    /**
     * VAT contained in a gross (tax-inclusive) amount.
     */
    public static function vatAmountFromGross(float $gross, int $ratePercent): float
    {
        return round($gross * $ratePercent / (100 + $ratePercent), 2);
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

        // Re-resolve the coupon at persist time against the live subtotal +
        // email so a stale preview can't lock in a bigger discount.
        $customerType = ($payload['customer_type'] ?? null) === CustomerType::Business->value
            ? CustomerType::Business
            : CustomerType::Private;

        $quote = $this->quote(
            $cartLines,
            $fulfillmentMode,
            isset($payload['coupon_code']) ? (string) $payload['coupon_code'] : null,
            isset($payload['email']) ? (string) $payload['email'] : null,
            $deliveryDate,
            $customerType,
        );
        if ($quote['lines'] === []) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        $orderSource = (string) ($payload['order_source'] ?? OrderSource::Storefront->value);
        if ($orderSource === OrderSource::Admin->value) {
            $quote = $this->applyAdminLinePricing($quote, $payload);
        }

        $audience = $this->stockAudienceForPayload($payload);

        return DB::transaction(function () use ($payload, $fulfillmentMode, $deliveryDate, $quote, $audience): ShopOrder {
            $inventory = app(InventoryService::class);

            // Re-check every line against live availability before we
            // commit. Lock the product rows so a concurrent order can't
            // read the same "remaining" and double-book.
            foreach ($quote['lines'] as $line) {
                /** @var Product $product */
                $product = $line['product'];
                Product::query()->lockForUpdate()->find($product->id);

                // Cap against the chosen grade's own stock (NULL line → the
                // default grade, key 0), so grades deplete independently.
                $remaining = $inventory->availableOnDate($product, $deliveryDate, $audience, $line['variant_id'] ?? 0);
                if ($line['quantity'] > $remaining + 1e-6) {
                    throw new InsufficientStockException(
                        $product,
                        requested: $line['quantity'],
                        remaining: max(0.0, $remaining),
                    );
                }
            }

            $order = ShopOrder::create([
                'name' => $payload['name'] ?? null,
                'email' => $payload['email'] ?? null,
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
                'delivery_fee_vat_rate_percent' => $quote['delivery_fee_vat_rate_percent'],
                'delivery_fee_vat_amount_eur' => $quote['delivery_fee_vat_amount_eur'],
                'total_amount_eur' => $quote['total_amount_eur'],
                'coupon_id' => $quote['coupon']?->id,
                'coupon_code' => $quote['coupon_code'],
                'discount_eur' => $quote['discount_eur'],
                'payment_method' => $payload['payment_method'] ?? 'online',
                'status' => ShopOrderStatus::PendingPayment,
                // Classification: defaults let the storefront path work without
                // sending these keys; the admin creation path passes them explicitly.
                'order_source' => $payload['order_source'] ?? OrderSource::Storefront->value,
                'customer_type' => $payload['customer_type'] ?? CustomerType::Private->value,
                // Pin the locale the order was placed in so customer documents
                // (receipt, confirmation email) stay in the customer's language
                // even when generated later from a different locale context.
                'locale' => $payload['locale'] ?? app()->getLocale(),
            ]);

            foreach ($quote['lines'] as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $line['variant_id'] ?? null,
                    'variant_name_snapshot' => $line['variant_name_snapshot'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price_snapshot_eur' => $line['unit_price_eur'],
                    'price_rule_id' => $line['price_rule_id'] ?? null,
                    'line_total_eur' => $line['line_total_eur'],
                    'vat_rate_snapshot_percent' => $line['vat_rate_percent'],
                    'vat_amount_eur' => $line['vat_amount_eur'],
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stockAudienceForPayload(array $payload): ShopStockAudience
    {
        $source = (string) ($payload['order_source'] ?? OrderSource::Storefront->value);
        $customerType = (string) ($payload['customer_type'] ?? CustomerType::Private->value);

        if ($source === OrderSource::Admin->value && $customerType === CustomerType::Business->value) {
            return ShopStockAudience::BusinessTrade;
        }

        return ShopStockAudience::Consumer;
    }
}
