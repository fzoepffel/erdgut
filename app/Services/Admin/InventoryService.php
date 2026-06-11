<?php

namespace App\Services\Admin;

use App\Enums\ShopOrderStatus;
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
     * Gross active inflow on `$date` minus committed outflow.
     *
     * Never returns negative; over-commitments are surfaced separately
     * via `committedOnDate() > grossInflowOnDate()` on the admin matrix.
     */
    public function availableOnDate(Product $product, Carbon $date): float
    {
        $gross = $this->grossInflowOnDate($product, $date);
        $committed = $this->committedOnDate($product, $date);

        return max(0.0, round($gross - $committed, 4));
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

        $out = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $out[$cursor->toDateString()] = $this->availableOnDate($product, $cursor);
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * Bulk availability for many products over a date window in a constant,
     * tiny number of queries — the storefront's hot path.
     *
     * Computes the exact same value as looping `availableOnDate()` over every
     * (product, day) pair, but with ~4 aggregate queries total instead of
     * `products × days × 3`. Inflows are spread across their freshness window
     * and commitments (shop / spargel / box) are bucketed per product+date in
     * PHP from windowed result sets, then `max(0, gross − committed)` per cell.
     *
     * Days/products with no inflow are simply absent from the inner map (the
     * caller treats a missing key as 0.0), mirroring `availableOnDate()` which
     * returns 0 for an empty day.
     *
     * @param  iterable<int, Product>  $products
     * @return array<int, array<string, float>> productId => (iso => remaining)
     */
    public function availabilityMatrix(iterable $products, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $productIds = collect($products)->map(fn (Product $p): int => (int) $p->id)->unique()->sort()->values()->all();
        if ($productIds === [] || $from->gt($to)) {
            return [];
        }

        // Cache the computed matrix (pure scalars, locale-independent) under a
        // version-scoped key. Model observers bump the version on any
        // inventory-affecting write; a short TTL bounds staleness otherwise.
        $suffix = sprintf('matrix.%s.%s.%s', $from->toDateString(), $to->toDateString(), md5(implode(',', $productIds)));

        return InventoryCache::remember($suffix, fn (): array => $this->computeAvailabilityMatrix($productIds, $from, $to));
    }

    /**
     * Uncached core of {@see availabilityMatrix}.
     *
     * @param  list<int>  $productIds
     * @return array<int, array<string, float>>
     */
    private function computeAvailabilityMatrix(array $productIds, Carbon $from, Carbon $to): array
    {
        $fromIso = $from->toDateString();
        $toIso = $to->toDateString();

        // ── 1. Gross inflows, spread across each row's freshness window ──────
        $gross = [];   // [productId][iso] => float
        $driver = DB::connection()->getDriverName();
        $expiresExpr = match ($driver) {
            'sqlite' => "date(received_on, '+' || (freshness_window_days - 1) || ' day')",
            'pgsql' => "received_on + (freshness_window_days - 1) * INTERVAL '1 day'",
            default => 'DATE_ADD(received_on, INTERVAL (freshness_window_days - 1) DAY)',
        };

        ProductStockInflow::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('received_on', '<=', $toIso)
            ->whereRaw($expiresExpr.' >= ?', [$fromIso])
            ->get(['product_id', 'received_on', 'quantity', 'freshness_window_days'])
            ->each(function (ProductStockInflow $inflow) use (&$gross, $from, $to): void {
                $pid = (int) $inflow->product_id;
                $start = Carbon::parse($inflow->received_on)->startOfDay();
                $end = $start->copy()->addDays(max(1, (int) $inflow->freshness_window_days) - 1);

                $cursor = $start->lt($from) ? $from->copy() : $start;
                $last = $end->gt($to) ? $to : $end;
                $qty = (float) $inflow->quantity;

                while ($cursor->lte($last)) {
                    $iso = $cursor->toDateString();
                    $gross[$pid][$iso] = ($gross[$pid][$iso] ?? 0.0) + $qty;
                    $cursor->addDay();
                }
            });

        // ── 2. Committed: shop + spargel + box, bucketed per product+date ────
        $committed = [];   // [productId][iso] => float
        $this->accumulateShopCommitments($committed, $productIds, $fromIso, $toIso);
        $this->accumulateSpargelCommitments($committed, $productIds, $fromIso, $toIso);
        $this->accumulateBoxCommitments($committed, $productIds, $from, $to);

        // ── 3. remaining = max(0, gross − committed) per cell ────────────────
        $matrix = [];
        foreach ($productIds as $pid) {
            $isos = array_unique(array_merge(
                array_keys($gross[$pid] ?? []),
                array_keys($committed[$pid] ?? []),
            ));
            foreach ($isos as $iso) {
                $remaining = round(($gross[$pid][$iso] ?? 0.0) - ($committed[$pid][$iso] ?? 0.0), 4);
                $matrix[$pid][$iso] = max(0.0, $remaining);
            }
        }

        return $matrix;
    }

    /**
     * Add shop-order commitments to the per-product+date bucket. Mirrors
     * {@see shopCommittedOnDate} but windowed and grouped in one query.
     *
     * @param  array<int, array<string, float>>  $committed
     * @param  list<int>  $productIds
     */
    private function accumulateShopCommitments(array &$committed, array $productIds, string $fromIso, string $toIso): void
    {
        ShopOrderItem::query()
            ->join('shop_orders', 'shop_orders.id', '=', 'shop_order_items.shop_order_id')
            ->whereIn('shop_order_items.product_id', $productIds)
            ->whereDate('shop_orders.expected_delivery_date', '>=', $fromIso)
            ->whereDate('shop_orders.expected_delivery_date', '<=', $toIso)
            ->whereNotIn('shop_orders.status', [
                ShopOrderStatus::Cancelled->value,
                ShopOrderStatus::Refunded->value,
            ])
            ->groupBy('shop_order_items.product_id', 'shop_orders.expected_delivery_date')
            ->get([
                'shop_order_items.product_id as product_id',
                'shop_orders.expected_delivery_date as iso',
                DB::raw('SUM(shop_order_items.quantity) as total'),
            ])
            ->each(function ($row) use (&$committed): void {
                $iso = Carbon::parse($row->iso)->toDateString();
                $pid = (int) $row->product_id;
                $committed[$pid][$iso] = ($committed[$pid][$iso] ?? 0.0) + (float) $row->total;
            });
    }

    /**
     * Add spargel-order commitments. Only the two configured spargel product
     * IDs carry any; skips entirely when neither is configured.
     *
     * @param  array<int, array<string, float>>  $committed
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
                    $committed[$pid][$iso] = ($committed[$pid][$iso] ?? 0.0) + $qty;
                }
            }
        }
    }

    /**
     * Add box-item commitments, resolving each row's delivery date in PHP
     * exactly as {@see boxCommittedOnDate} does, but for all products at once.
     *
     * @param  array<int, array<string, float>>  $committed
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
            $committed[$pid][$iso] = ($committed[$pid][$iso] ?? 0.0) + (float) $row->quantity;
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
    ): ProductStockInflow {
        $freshness = $freshnessWindowDays
            ?? (int) ($product->default_freshness_window_days ?? 1);

        $iso = $receivedOn->copy()->startOfDay()->toDateString();

        return ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $iso,
            'original_received_on' => $iso,
            'quantity' => round($quantity, 4),
            'freshness_window_days' => max(1, $freshness),
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
    ): ProductStockInflowSchedule {
        if ($endsOn !== null && $endsOn->lt($startsOn)) {
            throw new \InvalidArgumentException('ends_on must be >= starts_on.');
        }

        $freshness = $freshnessWindowDays
            ?? (int) ($product->default_freshness_window_days ?? 1);

        return DB::transaction(function () use ($product, $weekday, $plannedQuantity, $startsOn, $endsOn, $freshness, $notes, $createdByUserId): ProductStockInflowSchedule {
            $schedule = ProductStockInflowSchedule::create([
                'product_id' => $product->id,
                'weekday' => $weekday,
                'planned_quantity' => round($plannedQuantity, 4),
                'freshness_window_days' => max(1, $freshness),
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
                    'received_on' => $cursor->toDateString(),
                    'original_received_on' => $cursor->toDateString(),
                    'quantity' => $schedule->planned_quantity,
                    'freshness_window_days' => $schedule->freshness_window_days,
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
