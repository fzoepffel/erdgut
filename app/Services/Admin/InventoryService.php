<?php

namespace App\Services\Admin;

use App\Enums\CustomerType;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStockAudience;
use App\Enums\SpargelOrderStatus;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductStockInflowSchedule;
use App\Models\ShopOrderItem;
use App\Models\SpargelOrder;
use App\Support\BoxDeliveryDateResolver;
use App\Support\InventoryCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily inventory: inflows, outflows, and per-product freshness.
 *
 * Read paths ask `availableOnDate(Product, Carbon)`; write paths
 * record one-off inflows or recurring schedules; a weekly cron
 * extends the materialized window.
 *
 * Outflow is not stored as rows for v1 — it's computed on the fly
 * from `SpargelOrder` (when the product is one of the configured
 * spargel products) and from `box_items` aggregated by the box's
 * resolved delivery date (per {@see BoxDeliveryDateResolver}).
 */
class InventoryService
{
    /**
     * Default materialization horizon for `createSchedule` (weeks
     * ahead from the start date or today, whichever is later).
     */
    public const MATERIALIZE_WEEKS_AHEAD = 26;

    /**
     * Wider horizon used by the weekly cron — gives ~4 weeks of slack
     * over the on-save horizon so the window can't silently shrink
     * between cron runs.
     */
    public const CRON_MATERIALIZE_WEEKS_AHEAD = 30;

    /**
     * Days of slack added on each side of a query window when loading inflow
     * batches, so every batch that competes for the same commitments is
     * considered by the FEFO allocator. Comfortably exceeds any realistic
     * freshness window (which is measured in days).
     */
    private const BATCH_OVERLAP_BUFFER_DAYS = 60;

    /**
     * Remaining available quantity for delivery on `$date`, accounting for
     * batch depletion across each inflow's whole freshness window (FEFO).
     *
     * An inflow is a physical batch usable on any day in
     * `[received_on, received_on + freshness − 1]`; a commitment on any of
     * those days draws the batch down for *all* of them. Availability on a
     * day is therefore the sum of the *remaining* quantity of every batch
     * still live that day, after existing commitments have been allocated
     * to batches expiring-soonest-first. Never negative.
     */
    public function availableOnDate(
        Product $product,
        Carbon $date,
        ShopStockAudience $audience = ShopStockAudience::Consumer,
        ?int $variantId = null,
    ): float {
        $date = $date->copy()->startOfDay();
        $iso = $date->toDateString();

        $perVariant = $this->productAvailability([$product->id], $date, $date, $audience)[$product->id] ?? [];

        // NULL → product total across every grade (the "is there any stock"
        // contract relied on by the catalog). An int → that grade only
        // (0 = the default/"Standard" grade).
        $row = $variantId === null
            ? $this->sumVariantAvailability($perVariant)
            : ($perVariant[(int) $variantId] ?? []);

        return max(0.0, round($row[$iso] ?? 0.0, 4));
    }

    /**
     * Remaining stock for one grade on `$date`. NULL = the default/"Standard"
     * grade (the product itself); an int = a named variant. Unlike
     * {@see availableOnDate} with a NULL `$variantId`, this never sums grades.
     */
    public function remainingForGrade(
        Product $product,
        Carbon $date,
        ?int $variantId = null,
        ShopStockAudience $audience = ShopStockAudience::Consumer,
    ): float {
        return $this->availableOnDate($product, $date, $audience, $variantId ?? 0);
    }

    /**
     * Collapse a per-grade availability map (`[variantKey => [iso => qty]]`)
     * into a per-day product total (`[iso => qty]`).
     *
     * @param  array<int, array<string, float>>  $perVariant
     * @return array<string, float>
     */
    private function sumVariantAvailability(array $perVariant): array
    {
        $out = [];
        foreach ($perVariant as $row) {
            foreach ($row as $iso => $value) {
                $out[$iso] = ($out[$iso] ?? 0.0) + (float) $value;
            }
        }

        return $out;
    }

    /**
     * Sum of `quantity` across all active inflows for `$product` on
     * `$date`. Does NOT subtract outflows.
     */
    public function grossInflowOnDate(Product $product, Carbon $date): float
    {
        return (float) ProductStockInflow::query()
            ->where('product_id', $product->id)
            ->activeOn($date)
            ->sum('quantity');
    }

    /**
     * Map of `Y-m-d` → available quantity over the half-open window
     * `[$from, $to]` (both inclusive at day-granularity). One pass per
     * day; intended for the admin Inventar matrix where the admin
     * sees several products × several days at once.
     *
     * @return array<string, float>
     */
    public function availabilityWindow(Product $product, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $row = $this->sumVariantAvailability(
            $this->productAvailability([$product->id], $from, $to)[$product->id] ?? []
        );

        // Preserve the contract of a value for every day in [from, to].
        $out = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $iso = $cursor->toDateString();
            $out[$iso] = max(0.0, round($row[$iso] ?? 0.0, 4));
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * Bulk availability for many products over a date window in a constant,
     * tiny number of queries — the storefront's hot path.
     *
     * Batch-aware (FEFO): inflows are treated as physical batches whose
     * depletion carries across their whole freshness window, so a sale on one
     * day reduces every day that batch is live (see {@see productAvailability}
     * and {@see allocateRemainingByDay}). Loads batches + commitments in a few
     * windowed queries instead of `products × days × 3` per-day lookups.
     *
     * Days/products with no live batch are simply absent from the inner map
     * (the caller treats a missing key as 0.0), mirroring {@see availableOnDate}
     * which returns 0 for an empty day.
     *
     * @param  iterable<int, Product>  $products
     * @return array<int, array<string, float>> productId => (iso => remaining)
     */
    public function availabilityMatrix(
        iterable $products,
        Carbon $from,
        Carbon $to,
        ShopStockAudience $audience = ShopStockAudience::Consumer,
    ): array {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $productIds = collect($products)->map(fn (Product $p): int => (int) $p->id)->unique()->sort()->values()->all();
        if ($productIds === [] || $from->gt($to)) {
            return [];
        }

        // Cache the computed matrix (pure scalars, locale-independent) under a
        // version-scoped key. Model observers bump the version on any
        // inventory-affecting write; a short TTL bounds staleness otherwise.
        $suffix = sprintf(
            'matrix.%s.%s.%s.%s',
            $from->toDateString(),
            $to->toDateString(),
            $audience->value,
            md5(implode(',', $productIds)),
        );

        return InventoryCache::remember($suffix, fn (): array => $this->computeAvailabilityMatrix($productIds, $from, $to, $audience));
    }

    /**
     * Uncached core of {@see availabilityMatrix} — slices the shared
     * batch-aware availability to the requested `[from, to]` window.
     *
     * @param  list<int>  $productIds
     * @return array<int, array<string, float>>
     */
    private function computeAvailabilityMatrix(
        array $productIds,
        Carbon $from,
        Carbon $to,
        ShopStockAudience $audience = ShopStockAudience::Consumer,
    ): array {
        $fromIso = $from->toDateString();
        $toIso = $to->toDateString();

        $matrix = [];
        foreach ($this->productAvailability($productIds, $from, $to, $audience) as $pid => $perVariant) {
            foreach ($this->sumVariantAvailability($perVariant) as $iso => $value) {
                if ($iso >= $fromIso && $iso <= $toIso) {
                    $matrix[$pid][$iso] = max(0.0, round($value, 4));
                }
            }
        }

        return $matrix;
    }

    /**
     * Batch-aware available quantity per product per day (the single source
     * of truth behind {@see availableOnDate}, {@see availabilityWindow}, and
     * {@see computeAvailabilityMatrix}).
     *
     * Loads each product's inflow *batches* overlapping the window (widened by
     * {@see BATCH_OVERLAP_BUFFER_DAYS} so competing batches aren't missed),
     * loads all commitments over the batches' span, then runs the FEFO
     * allocator so a sale on any day draws its batch down across that batch's
     * entire freshness window. Returns `[productId => [variantKey => [iso =>
     * remaining]]]` for every day any batch is live (variantKey 0 = default
     * grade); callers slice to the days they need and sum grades for a total.
     *
     * @param  list<int>  $productIds
     * @return array<int, array<int, array<string, float>>>
     */
    private function productAvailability(
        array $productIds,
        Carbon $from,
        Carbon $to,
        ShopStockAudience $audience = ShopStockAudience::Consumer,
    ): array {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        if ($productIds === [] || $from->gt($to)) {
            return [];
        }

        // Load batches overlapping a buffered window so every batch that could
        // compete for the same commitments is present for the allocator.
        $loadFrom = $from->copy()->subDays(self::BATCH_OVERLAP_BUFFER_DAYS);
        $loadTo = $to->copy()->addDays(self::BATCH_OVERLAP_BUFFER_DAYS);
        $batchesByPid = $this->loadBatchesByProduct($productIds, $loadFrom, $loadTo);

        if ($batchesByPid === []) {
            return [];
        }

        // Commitments must cover the full span of the loaded batches: a
        // commitment outside [from, to] but inside a batch's window still
        // depletes that batch.
        $spanFrom = $from->copy();
        $spanTo = $to->copy();
        foreach ($batchesByPid as $variantBatches) {
            foreach ($variantBatches as $batches) {
                foreach ($batches as $b) {
                    if ($b['start']->lt($spanFrom)) {
                        $spanFrom = $b['start']->copy();
                    }
                    if ($b['end']->gt($spanTo)) {
                        $spanTo = $b['end']->copy();
                    }
                }
            }
        }

        $sharedCommitted = $this->sharedCommittedForProducts($productIds, $spanFrom, $spanTo);
        $businessShopCommitted = [];
        if ($audience === ShopStockAudience::BusinessTrade) {
            $this->accumulateShopCommitments(
                $businessShopCommitted,
                $productIds,
                $spanFrom->toDateString(),
                $spanTo->toDateString(),
                CustomerType::Business,
            );
        }

        $out = [];
        foreach ($batchesByPid as $pid => $variantBatches) {
            foreach ($variantBatches as $vkey => $batches) {
                $sharedBatches = array_values(array_filter(
                    $batches,
                    static fn (array $b): bool => ! ($b['b2b_only'] ?? false),
                ));

                if ($audience === ShopStockAudience::Consumer) {
                    $out[$pid][$vkey] = $this->allocateRemainingByDay($sharedBatches, $sharedCommitted[$pid][$vkey] ?? []);

                    continue;
                }

                $b2bBatches = array_values(array_filter(
                    $batches,
                    static fn (array $b): bool => (bool) ($b['b2b_only'] ?? false),
                ));

                $sharedAvail = $this->allocateRemainingByDay($sharedBatches, $sharedCommitted[$pid][$vkey] ?? []);
                $b2bAvail = $this->allocateRemainingByDay($b2bBatches, $businessShopCommitted[$pid][$vkey] ?? []);
                $out[$pid][$vkey] = $this->mergeDailyAvailability($sharedAvail, $b2bAvail);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, float>  ...$maps
     * @return array<string, float>
     */
    private function mergeDailyAvailability(array ...$maps): array
    {
        $out = [];
        foreach ($maps as $map) {
            foreach ($map as $iso => $value) {
                $out[$iso] = ($out[$iso] ?? 0.0) + (float) $value;
            }
        }

        return $out;
    }

    /**
     * FEFO allocation core: draw each day's committed demand from the batch
     * that expires soonest, then report each batch's *remaining* quantity
     * summed across the days it stays live.
     *
     * @param  list<array{start: Carbon, end: Carbon, qty: float}>  $batches
     * @param  array<string, float>  $committedByIso  iso => committed-for-delivery that day
     * @return array<string, float> iso => remaining available
     */
    private function allocateRemainingByDay(array $batches, array $committedByIso): array
    {
        if ($batches === []) {
            return [];
        }

        // Work on copies carrying a mutable remaining quantity.
        $batches = array_values(array_map(static fn (array $b): array => [
            'start' => $b['start'],
            'end' => $b['end'],
            'remaining' => (float) $b['qty'],
        ], $batches));

        // Allocate demand chronologically (iso strings sort by date).
        ksort($committedByIso);

        foreach ($committedByIso as $iso => $demand) {
            $demand = (float) $demand;
            if ($demand <= 1e-9) {
                continue;
            }
            $day = Carbon::parse($iso)->startOfDay();

            // Batches live on this day with stock left, expiring-soonest first.
            $candidates = [];
            foreach ($batches as $i => $b) {
                if ($b['remaining'] > 1e-9 && $b['start']->lte($day) && $b['end']->gte($day)) {
                    $candidates[] = $i;
                }
            }
            usort($candidates, function (int $a, int $b) use ($batches): int {
                return [$batches[$a]['end']->getTimestamp(), $batches[$a]['start']->getTimestamp(), $a]
                    <=> [$batches[$b]['end']->getTimestamp(), $batches[$b]['start']->getTimestamp(), $b];
            });

            foreach ($candidates as $i) {
                if ($demand <= 1e-9) {
                    break;
                }
                $take = min($demand, $batches[$i]['remaining']);
                $batches[$i]['remaining'] -= $take;
                $demand -= $take;
            }
            // Any leftover demand is over-commitment: the live batches are now
            // exhausted, which is physically correct — no further effect.
        }

        // Sum remaining per day across the union span of all batches.
        $spanStart = $batches[0]['start'];
        $spanEnd = $batches[0]['end'];
        foreach ($batches as $b) {
            if ($b['start']->lt($spanStart)) {
                $spanStart = $b['start'];
            }
            if ($b['end']->gt($spanEnd)) {
                $spanEnd = $b['end'];
            }
        }

        $out = [];
        $cursor = $spanStart->copy();
        while ($cursor->lte($spanEnd)) {
            $sum = 0.0;
            foreach ($batches as $b) {
                if ($b['start']->lte($cursor) && $b['end']->gte($cursor)) {
                    $sum += $b['remaining'];
                }
            }
            $out[$cursor->toDateString()] = round($sum, 4);
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * Load inflow batches overlapping `[from, to]`, grouped by product then by
     * grade (variantKey 0 = the default/"Standard" grade).
     *
     * @param  list<int>  $productIds
     * @return array<int, array<int, list<array{start: Carbon, end: Carbon, qty: float, b2b_only: bool}>>>
     */
    private function loadBatchesByProduct(array $productIds, Carbon $from, Carbon $to): array
    {
        $driver = DB::connection()->getDriverName();
        $expiresExpr = match ($driver) {
            'sqlite' => "date(received_on, '+' || (freshness_window_days - 1) || ' day')",
            'pgsql' => "received_on + (freshness_window_days - 1) * INTERVAL '1 day'",
            default => 'DATE_ADD(received_on, INTERVAL (freshness_window_days - 1) DAY)',
        };

        $byPid = [];
        ProductStockInflow::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('received_on', '<=', $to->toDateString())
            ->whereRaw($expiresExpr.' >= ?', [$from->toDateString()])
            ->get(['product_id', 'variant_id', 'received_on', 'quantity', 'freshness_window_days', 'b2b_only'])
            ->each(function (ProductStockInflow $inflow) use (&$byPid): void {
                $start = Carbon::parse($inflow->received_on)->startOfDay();
                $end = $start->copy()->addDays(max(1, (int) $inflow->freshness_window_days) - 1);
                $vkey = (int) ($inflow->variant_id ?? 0);
                $byPid[(int) $inflow->product_id][$vkey][] = [
                    'start' => $start,
                    'end' => $end,
                    'qty' => (float) $inflow->quantity,
                    'b2b_only' => (bool) $inflow->b2b_only,
                ];
            });

        return $byPid;
    }

    /**
     * Commitments that deplete shared (non-B2B-exclusive) stock: all shop
     * orders plus spargel and box channels. Keyed `[productId][variantKey][iso]`
     * (variantKey 0 = default grade; box/spargel always attribute to it).
     *
     * @param  list<int>  $productIds
     * @return array<int, array<int, array<string, float>>>
     */
    private function sharedCommittedForProducts(array $productIds, Carbon $from, Carbon $to): array
    {
        $committed = [];
        $this->accumulateShopCommitments($committed, $productIds, $from->toDateString(), $to->toDateString());
        $this->accumulateSpargelCommitments($committed, $productIds, $from->toDateString(), $to->toDateString());
        $this->accumulateBoxCommitments($committed, $productIds, $from, $to);

        return $committed;
    }

    /**
     * Add shop-order commitments to the per-product+grade+date bucket. Mirrors
     * {@see shopCommittedOnDate} but windowed and grouped in one query. A line's
     * `variant_id` (NULL = default grade) decides which grade it depletes.
     *
     * @param  array<int, array<int, array<string, float>>>  $committed
     * @param  list<int>  $productIds
     */
    private function accumulateShopCommitments(
        array &$committed,
        array $productIds,
        string $fromIso,
        string $toIso,
        ?CustomerType $customerType = null,
    ): void {
        ShopOrderItem::query()
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->whereIn('shop_order_items.product_id', $productIds)
            ->whereDate('shop_orders.expected_delivery_date', '>=', $fromIso)
            ->whereDate('shop_orders.expected_delivery_date', '<=', $toIso)
            ->when($customerType !== null, fn ($q) => $q->where('shop_orders.customer_type', $customerType->value))
            ->whereNotIn('shop_orders.status', [
                ShopOrderStatus::Cancelled->value,
                ShopOrderStatus::Refunded->value,
            ])
            ->groupBy('shop_order_items.product_id', 'shop_order_items.variant_id', 'shop_orders.expected_delivery_date')
            ->get([
                'shop_order_items.product_id as product_id',
                'shop_order_items.variant_id as variant_id',
                'shop_orders.expected_delivery_date as iso',
                DB::raw('SUM(shop_order_items.quantity) as total'),
            ])
            ->each(function ($row) use (&$committed): void {
                $iso = Carbon::parse($row->iso)->toDateString();
                $pid = (int) $row->product_id;
                $vkey = (int) ($row->variant_id ?? 0);
                $committed[$pid][$vkey][$iso] = ($committed[$pid][$vkey][$iso] ?? 0.0) + (float) $row->total;
            });
    }

    /**
     * Add spargel-order commitments. Only the two configured spargel product
     * IDs carry any; skips entirely when neither is configured. Spargel has no
     * grade concept, so commitments draw the default grade (variantKey 0).
     *
     * @param  array<int, array<int, array<string, float>>>  $committed
     * @param  list<int>  $productIds
     */
    private function accumulateSpargelCommitments(array &$committed, array $productIds, string $fromIso, string $toIso): void
    {
        $whiteId = config('spargel.white_product_id');
        $greenId = config('spargel.green_product_id');

        $columns = [];
        if ($whiteId !== null && in_array((int) $whiteId, $productIds, true)) {
            $columns[(int) $whiteId] = 'quantity_white_grams';
        }
        if ($greenId !== null && in_array((int) $greenId, $productIds, true)) {
            $columns[(int) $greenId] = 'quantity_green_grams';
        }
        if ($columns === []) {
            return;
        }

        $rows = SpargelOrder::query()
            ->whereDate('expected_delivery_date', '>=', $fromIso)
            ->whereDate('expected_delivery_date', '<=', $toIso)
            ->whereNotIn('status', [
                SpargelOrderStatus::Cancelled->value,
                SpargelOrderStatus::Refunded->value,
            ])
            ->get(['expected_delivery_date', 'quantity_white_grams', 'quantity_green_grams']);

        foreach ($rows as $row) {
            $iso = Carbon::parse($row->expected_delivery_date)->toDateString();
            foreach ($columns as $pid => $column) {
                $qty = (float) $row->{$column};
                if ($qty !== 0.0) {
                    $committed[$pid][0][$iso] = ($committed[$pid][0][$iso] ?? 0.0) + $qty;
                }
            }
        }
    }

    /**
     * Add box-item commitments, resolving each row's delivery date in PHP
     * exactly as {@see boxCommittedOnDate} does, but for all products at once.
     * Boxes have no grade concept, so they draw the default grade (variantKey 0).
     *
     * @param  array<int, array<int, array<string, float>>>  $committed
     * @param  list<int>  $productIds
     */
    private function accumulateBoxCommitments(array &$committed, array $productIds, Carbon $from, Carbon $to): void
    {
        $rows = DB::table('box_items')
            ->join('boxes', 'boxes.id', '=', 'box_items.box_id')
            ->leftJoin('subscriptions', 'subscriptions.id', '=', 'boxes.subscription_id')
            ->whereIn('box_items.product_id', $productIds)
            ->select([
                'box_items.product_id',
                'box_items.quantity',
                'boxes.week_start_date',
                'subscriptions.delivery_weekday',
            ])
            ->get();

        $defaultWeekday = $this->defaultWeekday();
        $fromIso = $from->toDateString();
        $toIso = $to->toDateString();

        foreach ($rows as $row) {
            $weekday = $row->delivery_weekday !== null
                ? (int) $row->delivery_weekday
                : $defaultWeekday;
            $deliveryDate = Carbon::parse($row->week_start_date)
                ->startOfDay()
                ->addDays(($weekday - 1 + 7) % 7);

            $iso = $deliveryDate->toDateString();
            if ($iso < $fromIso || $iso > $toIso) {
                continue;
            }

            $pid = (int) $row->product_id;
            $committed[$pid][0][$iso] = ($committed[$pid][0][$iso] ?? 0.0) + (float) $row->quantity;
        }
    }

    /**
     * Total committed quantity for `$product` on `$date`. Includes
     * spargel-order grams (when `$product` is one of the configured
     * spargel products) plus box-item quantities for boxes whose
     * resolved delivery date equals `$date`.
     */
    public function committedOnDate(Product $product, Carbon $date): float
    {
        $iso = $date->copy()->startOfDay()->toDateString();
        $committed = 0.0;

        $committed += $this->spargelCommittedOnDate($product, $iso);
        $committed += $this->boxCommittedOnDate($product, $iso);
        $committed += $this->shopCommittedOnDate($product, $iso);

        return round($committed, 4);
    }

    /**
     * Record one ad-hoc inflow (a manual receipt or a materialized
     * schedule occurrence).
     */
    public function recordInflow(
        Product $product,
        Carbon $receivedOn,
        float $quantity,
        ?int $freshnessWindowDays = null,
        ?int $sourceScheduleId = null,
        ?string $notes = null,
        ?int $createdByUserId = null,
        bool $b2bOnly = false,
        ?int $variantId = null,
    ): ProductStockInflow {
        $freshness = $freshnessWindowDays
            ?? (int) ($product->default_freshness_window_days ?? 1);

        $iso = $receivedOn->copy()->startOfDay()->toDateString();

        return ProductStockInflow::create([
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'received_on' => $iso,
            'original_received_on' => $iso,
            'quantity' => round($quantity, 4),
            'freshness_window_days' => max(1, $freshness),
            'b2b_only' => $b2bOnly,
            'source_schedule_id' => $sourceScheduleId,
            'notes' => $notes,
            'created_by_user_id' => $createdByUserId,
        ]);
    }

    /**
     * Create a recurring schedule and materialize its first window.
     */
    public function createSchedule(
        Product $product,
        int $weekday,
        float $plannedQuantity,
        Carbon $startsOn,
        ?Carbon $endsOn = null,
        ?int $freshnessWindowDays = null,
        ?string $notes = null,
        ?int $createdByUserId = null,
        bool $b2bOnly = false,
        ?int $variantId = null,
    ): ProductStockInflowSchedule {
        if ($endsOn !== null && $endsOn->lt($startsOn)) {
            throw new \InvalidArgumentException('ends_on must be >= starts_on.');
        }

        $freshness = $freshnessWindowDays
            ?? (int) ($product->default_freshness_window_days ?? 1);

        return DB::transaction(function () use ($product, $weekday, $plannedQuantity, $startsOn, $endsOn, $freshness, $notes, $createdByUserId, $b2bOnly, $variantId): ProductStockInflowSchedule {
            $schedule = ProductStockInflowSchedule::create([
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'weekday' => $weekday,
                'planned_quantity' => round($plannedQuantity, 4),
                'freshness_window_days' => max(1, $freshness),
                'b2b_only' => $b2bOnly,
                'starts_on' => $startsOn->copy()->startOfDay()->toDateString(),
                'ends_on' => $endsOn?->copy()->startOfDay()->toDateString(),
                'notes' => $notes,
                'created_by_user_id' => $createdByUserId,
            ]);

            $this->materializeSchedule($schedule, $this->defaultHorizon($startsOn));

            return $schedule;
        });
    }

    /**
     * End a schedule on the date before `$fromDate` and delete any
     * future inflows it materialized whose quantity still matches.
     * Manual overrides (rows with a different quantity) are left
     * intact — admin edited them on purpose.
     */
    public function cancelScheduleFrom(int $scheduleId, Carbon $fromDate): void
    {
        $from = $fromDate->copy()->startOfDay();

        DB::transaction(function () use ($scheduleId, $from): void {
            $schedule = ProductStockInflowSchedule::query()->lockForUpdate()->findOrFail($scheduleId);
            $newEnd = $from->copy()->subDay();

            if ($newEnd->lt($schedule->starts_on)) {
                // Cancel-before-start — nuke the schedule and any rows
                // it managed to write (only those whose quantity still
                // matches; manual edits preserved).
                ProductStockInflow::query()
                    ->where('source_schedule_id', $schedule->id)
                    ->where('quantity', $schedule->planned_quantity)
                    ->delete();
                $schedule->delete();

                return;
            }

            $schedule->forceFill(['ends_on' => $newEnd->toDateString()])->save();

            ProductStockInflow::query()
                ->where('source_schedule_id', $schedule->id)
                ->whereDate('received_on', '>=', $from->toDateString())
                ->where('quantity', $schedule->planned_quantity)
                ->delete();
        });
    }

    /**
     * Change a schedule's quantity from `$fromDate` onward. Implements
     * the same end-old-then-create-new dance as the legacy service so
     * manual overrides on past weeks survive.
     */
    public function adjustScheduleFrom(
        int $scheduleId,
        Carbon $fromDate,
        float $newQuantity,
        ?int $createdByUserId = null,
    ): ProductStockInflowSchedule {
        $from = $fromDate->copy()->startOfDay();

        return DB::transaction(function () use ($scheduleId, $from, $newQuantity, $createdByUserId): ProductStockInflowSchedule {
            $old = ProductStockInflowSchedule::query()->lockForUpdate()->findOrFail($scheduleId);
            $originalEnd = $old->ends_on;

            $this->cancelScheduleFrom($old->id, $from);

            return $this->createSchedule(
                product: $old->product,
                weekday: $old->weekday,
                plannedQuantity: $newQuantity,
                startsOn: $from,
                endsOn: $originalEnd,
                freshnessWindowDays: $old->freshness_window_days,
                notes: $old->notes,
                createdByUserId: $createdByUserId,
                b2bOnly: (bool) $old->b2b_only,
                variantId: $old->variant_id !== null ? (int) $old->variant_id : null,
            );
        });
    }

    /**
     * Idempotent sweep — for every currently-active schedule, ensure
     * inflow rows exist up to `$upTo` (inclusive). Returns the number
     * of rows written.
     */
    public function materializeWindow(Carbon $upTo): int
    {
        $upToDate = $upTo->copy()->startOfDay();
        $written = 0;

        ProductStockInflowSchedule::query()
            ->where(function ($q) use ($upToDate): void {
                $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $upToDate->toDateString());
            })
            ->orderBy('id')
            ->chunk(100, function ($schedules) use ($upToDate, &$written): void {
                foreach ($schedules as $schedule) {
                    $written += $this->materializeSchedule($schedule, $upToDate);
                }
            });

        return $written;
    }

    /**
     * Walk one schedule's weekday occurrences in `[start..horizon]`
     * and insert any missing inflow rows. Idempotent: skips dates
     * that already have a row pointing back at this schedule.
     */
    public function materializeSchedule(ProductStockInflowSchedule $schedule, Carbon $horizon): int
    {
        $start = Carbon::parse($schedule->starts_on)->startOfDay();
        $end = $schedule->ends_on !== null
            ? Carbon::parse($schedule->ends_on)->startOfDay()->min($horizon)
            : $horizon->copy();

        if ($start->gt($end)) {
            return 0;
        }

        $cursor = $this->advanceToWeekday($start, $schedule->weekday);

        $written = 0;
        while ($cursor->lte($end)) {
            // Match on `original_received_on` so admin-edited dates
            // don't trick the materializer into recreating the
            // originally-scheduled day. New rows set both fields equal.
            $exists = ProductStockInflow::query()
                ->where('source_schedule_id', $schedule->id)
                ->whereDate('original_received_on', $cursor->toDateString())
                ->exists();

            if (! $exists) {
                ProductStockInflow::create([
                    'product_id' => $schedule->product_id,
                    'variant_id' => $schedule->variant_id,
                    'received_on' => $cursor->toDateString(),
                    'original_received_on' => $cursor->toDateString(),
                    'quantity' => $schedule->planned_quantity,
                    'freshness_window_days' => $schedule->freshness_window_days,
                    'b2b_only' => (bool) $schedule->b2b_only,
                    'source_schedule_id' => $schedule->id,
                    'notes' => null,
                    'created_by_user_id' => null,
                ]);
                $written++;
            }

            $cursor->addWeek();
        }

        return $written;
    }

    private function defaultHorizon(Carbon $startsOn): Carbon
    {
        $now = Carbon::now()->startOfDay();

        return $now->max($startsOn)->copy()->addWeeks(self::MATERIALIZE_WEEKS_AHEAD);
    }

    /**
     * Move `$date` forward (or stay put) until it lands on
     * `$targetWeekday`. Uses Carbon `dayOfWeek` convention.
     */
    private function advanceToWeekday(Carbon $date, int $targetWeekday): Carbon
    {
        $delta = ($targetWeekday - $date->dayOfWeek + 7) % 7;

        return $date->copy()->addDays($delta);
    }

    private function spargelCommittedOnDate(Product $product, string $iso): float
    {
        $whiteId = config('spargel.white_product_id');
        $greenId = config('spargel.green_product_id');

        if ($whiteId === null && $greenId === null) {
            return 0.0;
        }

        $column = match ((int) $product->id) {
            (int) $whiteId => 'quantity_white_grams',
            (int) $greenId => 'quantity_green_grams',
            default => null,
        };

        if ($column === null) {
            return 0.0;
        }

        return (float) SpargelOrder::query()
            ->whereDate('expected_delivery_date', $iso)
            ->whereNotIn('status', [
                SpargelOrderStatus::Cancelled,
                SpargelOrderStatus::Refunded,
            ])
            ->sum($column);
    }

    private function shopCommittedOnDate(Product $product, string $iso): float
    {
        // `shop_order_items.quantity` is in canonical product units (same
        // as inflows), so it aggregates directly. Only orders still on the
        // hook for fulfilment commit stock — cancelled/refunded release it.
        return (float) ShopOrderItem::query()
            ->where('shop_order_items.product_id', $product->id)
            ->whereHas('shopOrder', function ($q) use ($iso): void {
                $q->whereDate('expected_delivery_date', $iso)
                    ->whereNotIn('status', [
                        ShopOrderStatus::Cancelled,
                        ShopOrderStatus::Refunded,
                    ]);
            })
            ->sum('shop_order_items.quantity');
    }

    private function boxCommittedOnDate(Product $product, string $iso): float
    {
        // `box_items.quantity` is in canonical product units, same as
        // `product_stock_inflows.quantity`. Aggregating across boxes
        // whose computed delivery date equals `$iso` keeps the math
        // unit-consistent.
        $rows = DB::table('box_items')
            ->join('boxes', 'boxes.id', '=', 'box_items.box_id')
            ->leftJoin('subscriptions', 'subscriptions.id', '=', 'boxes.subscription_id')
            ->where('box_items.product_id', $product->id)
            ->select([
                'box_items.quantity',
                'boxes.week_start_date',
                'subscriptions.delivery_weekday',
            ])
            ->get();

        $defaultWeekday = $this->defaultWeekday();
        $total = 0.0;

        foreach ($rows as $row) {
            $weekday = $row->delivery_weekday !== null
                ? (int) $row->delivery_weekday
                : $defaultWeekday;
            $deliveryDate = Carbon::parse($row->week_start_date)
                ->startOfDay()
                ->addDays(($weekday - 1 + 7) % 7);

            if ($deliveryDate->toDateString() === $iso) {
                $total += (float) $row->quantity;
            }
        }

        return $total;
    }

    private function defaultWeekday(): int
    {
        $zone = config('delivery.default_zone', 'berlin_core');
        $allowed = config('delivery.zones.'.$zone.'.allowed_weekdays', []);

        if (is_array($allowed) && isset($allowed[0]) && is_int($allowed[0])) {
            return $allowed[0];
        }

        return 3;
    }
}
