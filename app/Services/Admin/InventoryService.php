<?php

namespace App\Services\Admin;

use App\Enums\SpargelOrderStatus;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductStockInflowSchedule;
use App\Models\SpargelOrder;
use App\Support\BoxDeliveryDateResolver;
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

        return ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => $receivedOn->copy()->startOfDay()->toDateString(),
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
            $exists = ProductStockInflow::query()
                ->where('source_schedule_id', $schedule->id)
                ->whereDate('received_on', $cursor->toDateString())
                ->exists();

            if (! $exists) {
                ProductStockInflow::create([
                    'product_id' => $schedule->product_id,
                    'received_on' => $cursor->toDateString(),
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
